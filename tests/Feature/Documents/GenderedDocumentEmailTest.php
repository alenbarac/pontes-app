<?php

use App\Jobs\SendInvoiceMailing;
use App\Jobs\SendMemberDocumentMailing;
use App\Mail\MemberDocumentMailable;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\MemberDocumentMailing;
use App\Models\MemberGroup;
use App\Models\User;
use App\Models\Workshop;
use App\Services\DocumentGenerationService;
use App\Services\DocumentMailingBatch;
use App\Services\MemberDocumentEmailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    RateLimiter::clear(SendInvoiceMailing::SEND_SLOT);
    Cache::forget(SendInvoiceMailing::SEND_SLOT.':gate');
});

function genderedTemplate(string $content = 'Naša {{g:učenik|učenica}} {{member.full_name}}.'): DocumentTemplate
{
    return DocumentTemplate::query()->create([
        'type' => 'ispricnica',
        'name' => 'Ispričnica',
        'content' => $content,
        'is_active' => true,
    ]);
}

test('gender pairs follow the member and plain text stays as written', function () {
    $service = app(DocumentGenerationService::class);
    $template = genderedTemplate('{{g:polaznik|polaznica}} je {{g:sudjelovao|sudjelovala}}. Bok {{member.first_name}}.');
    $plain = genderedTemplate('Samo tekst.');

    $male = Member::factory()->create(['gender' => 'male', 'first_name' => 'Ivan']);
    $female = Member::factory()->create(['gender' => 'female', 'first_name' => 'Ivana']);

    expect($service->replacePlaceholders($template, $male))->toBe('polaznik je sudjelovao. Bok Ivan.')
        ->and($service->replacePlaceholders($template, $female))->toBe('polaznica je sudjelovala. Bok Ivana.')
        ->and($service->replacePlaceholders($plain, $female))->toBe('Samo tekst.');
});

test('generating asks for a missing gender and succeeds after it is saved', function () {
    $this->actingAs(User::factory()->create());

    $template = genderedTemplate();
    $member = Member::factory()->create([
        'gender' => null,
        'first_name' => 'Mia',
        'last_name' => 'Kovač',
    ]);

    $this->postJson(route('document-templates.generate-bulk', $template), [
        'member_ids' => [$member->id],
    ])->assertStatus(422)
        ->assertJsonPath('missing_gender.0.id', $member->id);

    expect(MemberDocument::query()->count())->toBe(0);

    $this->postJson(route('members.genders'), [
        'members' => [
            ['id' => $member->id, 'gender' => 'female'],
        ],
    ])->assertOk();

    expect($member->fresh()->gender)->toBe('female');

    $this->postJson(route('document-templates.generate-bulk', $template), [
        'member_ids' => [$member->id],
    ])->assertOk()
        ->assertJsonPath('count', 1);

    expect(MemberDocument::query()->where('member_id', $member->id)->count())->toBe(1);
});

test('document mail goes only to invoice_email and the preview matches what was queued', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());

    $template = genderedTemplate('Draga {{g:učenik|učenica}}.');
    $withEmail = Member::factory()->create([
        'gender' => 'female',
        'first_name' => 'Ana',
        'last_name' => 'Anić',
        'invoice_email' => 'ana@example.test',
    ]);
    $withoutEmail = Member::factory()->create([
        'gender' => 'male',
        'invoice_email' => null,
    ]);

    $group = MemberGroup::query()->create(['name' => 'Test grupa']);
    $workshop = Workshop::factory()->create();
    foreach ([$withEmail, $withoutEmail] as $member) {
        DB::table('member_workshop_group')->insert([
            'member_id' => $member->id,
            'workshop_id' => $workshop->id,
            'member_group_id' => $group->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $preview = $this->postJson(route('member-groups.document-emails.preview', $group), [
        'document_template_id' => $template->id,
        'send_scope' => 'all',
    ])->assertOk()
        ->assertJsonPath('deliverable_count', 1)
        ->assertJsonPath('skipped_no_email.0.id', $withoutEmail->id)
        ->assertJsonPath('already_sent', []);

    $this->postJson(route('member-groups.document-emails', $group), [
        'document_template_id' => $template->id,
        'send_scope' => 'all',
    ])->assertOk()
        ->assertJsonPath('queued', 1)
        ->assertJsonPath('skipped_no_email.0.id', $withoutEmail->id);

    $row = MemberDocumentMailing::query()->first();
    expect($row)->not->toBeNull();
    if ($row->status === MemberDocumentMailing::STATUS_QUEUED) {
        (new SendMemberDocumentMailing($row->id))->handle();
    }

    Mail::assertSent(MemberDocumentMailable::class, function (MemberDocumentMailable $mail) {
        return $mail->hasTo('ana@example.test');
    });

    expect(MemberDocumentMailing::query()->where('status', MemberDocumentMailing::STATUS_SENT)->count())->toBe(1)
        ->and(MemberDocument::query()->where('member_id', $withoutEmail->id)->count())->toBe(0);

    $this->postJson(route('member-groups.document-emails.preview', $group), [
        'document_template_id' => $template->id,
        'send_scope' => 'all',
    ])->assertOk()
        ->assertJsonPath('deliverable_count', 0)
        ->assertJsonPath('already_sent.0.id', $withEmail->id)
        ->assertJsonPath('skipped_no_email.0.id', $withoutEmail->id);

    expect($preview)->not->toBeNull();
});

test('the pdf attachment carries the gendered wording for that member', function () {
    $member = Member::factory()->create([
        'gender' => 'female',
        'first_name' => 'Ana',
        'last_name' => 'Anić',
    ]);
    $template = genderedTemplate('Draga {{g:učenik|učenica}}.');
    $wording = app(DocumentGenerationService::class)->replacePlaceholders($template, $member);
    expect($wording)->toBe('Draga učenica.');

    $document = MemberDocument::query()->create([
        'member_id' => $member->id,
        'document_template_id' => $template->id,
        'document_type' => 'ispricnica',
        'additional_data' => [],
    ]);
    $document->load(['member', 'documentTemplate']);

    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('output')->andReturn($wording);
    $this->mock(DocumentGenerationService::class, function ($mock) use ($pdf) {
        $mock->shouldReceive('generatePDF')->andReturn($pdf);
    });

    $mail = new MemberDocumentMailable($document);
    $mail->assertHasSubject('Ispričnica - Ana Anić');

    $attachment = $mail->attachments()[0];
    $bytes = $attachment->attachWith(
        fn () => null,
        fn ($data) => $data(),
    );

    expect($bytes)->toBe('Draga učenica.')
        ->and($attachment->as)->toBe('ispricnica-ana-anic.pdf');
});

test('a potvrda uses the ispricnica layout with the POTVRDA heading', function () {
    $service = app(DocumentGenerationService::class);
    $member = Member::factory()->create(['gender' => 'female', 'first_name' => 'Ana', 'last_name' => 'Anić']);

    $potvrda = genderedTemplate('Tekst potvrde.');
    $potvrda->update(['type' => 'potvrda', 'name' => 'Potvrda']);
    $ispricnica = genderedTemplate('Tekst ispričnice.');

    $captured = [];
    View::composer('documents.ispricnica', function ($view) use (&$captured) {
        $captured[] = $view->getData()['documentTitle'] ?? null;
    });

    $service->generatePDF($potvrda->fresh(), $member);
    $service->generatePDF($ispricnica, $member);

    expect($captured)->toBe(['POTVRDA', 'Ispričnica']);
});

test('a send without gender is refused and does not queue mail', function () {
    Mail::fake();

    $template = genderedTemplate();
    $member = Member::factory()->create([
        'gender' => null,
        'invoice_email' => 'ana@example.test',
    ]);

    $result = app(MemberDocumentEmailService::class)->send(
        collect([$member]),
        $template,
        [],
        false,
        new DocumentMailingBatch('members', null, $template->name, null),
    );

    expect($result['missing_gender'])->toHaveCount(1)
        ->and(MemberDocumentMailing::query()->count())->toBe(0);

    Mail::assertNothingSent();
});

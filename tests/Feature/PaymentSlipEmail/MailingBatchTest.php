<?php

use App\Jobs\SendInvoiceMailing;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Mailing;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupWorkshop;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Support\Facades\Queue;
use Inertia\Support\Header;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());
});

test('one send writes one mailing and one invoice mailing per queued slip', function () {
    $first = Member::factory()->create(['invoice_email' => 'one@example.test']);
    $second = Member::factory()->create(['invoice_email' => 'two@example.test']);

    Invoice::factory()->create([
        'member_id' => $first->id,
        'due_date' => '2026-09-15',
    ]);
    Invoice::factory()->create([
        'member_id' => $second->id,
        'due_date' => '2026-09-15',
    ]);

    $this->postJson(route('members.bulkSendSlipEmails'), [
        'member_ids' => [$first->id, $second->id],
        'month' => '2026-09',
    ])->assertOk()
        ->assertJsonPath('queued', 2)
        ->assertJsonPath('message', 'Slanje je pokrenuto. 2 uplatnice šalju se odabranim članovima.');

    expect(Mailing::query()->count())->toBe(1);

    $mailing = Mailing::query()->first();
    expect($mailing->type)->toBe(Mailing::TYPE_SLIP)
        ->and($mailing->source)->toBe(Mailing::SOURCE_MEMBERS)
        ->and($mailing->label)->toBe('Uplatnice 09/2026')
        ->and($mailing->month)->toBe('2026-09')
        ->and($mailing->member_group_id)->toBeNull()
        ->and($mailing->completed_at)->toBeNull()
        ->and($mailing->user_id)->toBe(auth()->id());

    expect(InvoiceMailing::query()->where('mailing_id', $mailing->id)->count())->toBe(2);
    Queue::assertPushed(SendInvoiceMailing::class, 2);
});

test('a group send stores the group on the mailing', function () {
    $workshop = Workshop::factory()->create();
    $group = MemberGroup::query()->create(['name' => 'Grupa 1']);
    $member = Member::factory()->create(['invoice_email' => 'parent@example.test']);

    MemberGroupWorkshop::query()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'member_group_id' => $group->id,
    ]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'due_date' => '2026-09-15',
    ]);

    $this->postJson(route('member-groups.bulk-send-slip-emails', $group), [
        'month' => '2026-09',
        'send_scope' => 'all',
    ])->assertOk()
        ->assertJsonPath('message', 'Slanje je pokrenuto. 1 uplatnica šalje se članovima grupe Grupa 1.');

    $mailing = Mailing::query()->first();
    expect($mailing->source)->toBe(Mailing::SOURCE_GROUP)
        ->and($mailing->member_group_id)->toBe($group->id)
        ->and($mailing->label)->toBe('Uplatnice 09/2026')
        ->and($mailing->user_id)->toBe(auth()->id());
});

test('retry failed requeues only the failed recipients on the same batch', function () {
    $mailing = Mailing::factory()->create([
        'source' => Mailing::SOURCE_GROUP,
        'label' => 'Uplatnice 09/2026',
        'month' => '2026-09',
        'completed_at' => now(),
    ]);

    $sentA = InvoiceMailing::factory()->create([
        'mailing_id' => $mailing->id,
        'status' => InvoiceMailing::STATUS_SENT,
    ]);
    $sentB = InvoiceMailing::factory()->create([
        'mailing_id' => $mailing->id,
        'status' => InvoiceMailing::STATUS_SENT,
    ]);
    $failed = InvoiceMailing::factory()->failed()->create([
        'mailing_id' => $mailing->id,
    ]);

    $this->post(route('mailings.retry-failed', $mailing))
        ->assertRedirect()
        ->assertSessionHas('success', 'Neuspjele uplatnice ponovno su stavljene u red.');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    Queue::assertPushed(SendInvoiceMailing::class, fn (SendInvoiceMailing $job) => $job->invoiceMailingId !== $sentA->id
        && $job->invoiceMailingId !== $sentB->id
        && $job->invoiceMailingId !== $failed->id);

    expect(InvoiceMailing::query()->where('mailing_id', $mailing->id)->where('status', InvoiceMailing::STATUS_SENT)->count())->toBe(2)
        ->and(InvoiceMailing::query()->where('mailing_id', $mailing->id)->where('status', InvoiceMailing::STATUS_QUEUED)->count())->toBe(1)
        ->and($mailing->fresh()->completed_at)->toBeNull();
});

test('a partial reload reports queued progress and drops the batch when it finishes', function () {
    $mailing = Mailing::factory()->create([
        'source' => Mailing::SOURCE_INVOICES,
        'label' => 'Uplatnice 09/2026',
        'month' => '2026-09',
    ]);

    InvoiceMailing::factory()->queued()->count(2)->create([
        'mailing_id' => $mailing->id,
    ]);

    $probe = $this->get(route('mailings.index'));
    $page = $probe->viewData('page');
    $version = is_array($page) ? (string) ($page['version'] ?? '') : (string) ($page->version ?? '');

    $this->withHeaders([
        Header::INERTIA => 'true',
        Header::VERSION => $version,
        Header::PARTIAL_COMPONENT => 'Mailings/Index',
        Header::PARTIAL_ONLY => 'mailingActivity',
    ])->getJson(route('mailings.index'))
        ->assertOk()
        ->assertJsonPath('props.mailingActivity.active.0.id', $mailing->id)
        ->assertJsonPath('props.mailingActivity.active.0.queued', 2)
        ->assertJsonPath('props.mailingActivity.active.0.sent', 0)
        ->assertJsonPath('props.mailingActivity.active.0.total', 2)
        ->assertJsonPath('props.mailingActivity.active.0.processed', 0)
        ->assertJsonPath('props.mailingActivity.active.0.group_name', 'Računi')
        ->assertJsonCount(1, 'props.mailingActivity.active');

    InvoiceMailing::query()->where('mailing_id', $mailing->id)->update([
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now(),
    ]);
    $mailing->refreshCompletion();

    $this->withHeaders([
        Header::INERTIA => 'true',
        Header::VERSION => $version,
        Header::PARTIAL_COMPONENT => 'Mailings/Index',
        Header::PARTIAL_ONLY => 'mailingActivity',
    ])->getJson(route('mailings.index'))
        ->assertOk()
        ->assertJsonCount(0, 'props.mailingActivity.active')
        ->assertJsonPath('props.mailingActivity.latest.id', $mailing->id)
        ->assertJsonPath('props.mailingActivity.latest.status', Mailing::STATUS_SENT)
        ->assertJsonPath('props.mailingActivity.recent_finished.0.id', $mailing->id)
        ->assertJsonPath('props.mailingActivity.recent.0.id', $mailing->id)
        ->assertJsonPath('props.mailingActivity.recent.0.label', 'Uplatnice 09/2026');
});

test('an older queued attempt does not keep a finished batch open', function () {
    $mailing = Mailing::factory()->create([
        'source' => Mailing::SOURCE_INVOICES,
        'completed_at' => null,
    ]);

    $older = InvoiceMailing::factory()->queued()->create([
        'mailing_id' => $mailing->id,
    ]);

    InvoiceMailing::factory()->create([
        'mailing_id' => $mailing->id,
        'invoice_id' => $older->invoice_id,
        'member_id' => $older->member_id,
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now(),
    ]);

    $mailing->refreshCompletion();

    expect($mailing->fresh()->completed_at)->not->toBeNull();
});

test('the mailing index lists the batch with derived counts', function () {
    $group = MemberGroup::query()->create(['name' => 'Grupa 1']);
    $mailing = Mailing::factory()->create([
        'source' => Mailing::SOURCE_GROUP,
        'member_group_id' => $group->id,
        'label' => 'Uplatnice 09/2026',
        'month' => '2026-09',
        'started_at' => '2026-09-28 10:00:00',
        'completed_at' => '2026-09-28 10:05:00',
    ]);

    InvoiceMailing::factory()->count(2)->create([
        'mailing_id' => $mailing->id,
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now(),
    ]);
    InvoiceMailing::factory()->failed()->create([
        'mailing_id' => $mailing->id,
    ]);

    $this->get(route('mailings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Mailings/Index')
            ->where('mailings.data.0.id', $mailing->id)
            ->where('mailings.data.0.label', 'Uplatnice 09/2026')
            ->where('mailings.data.0.group_name', 'Grupa 1')
            ->where('mailings.data.0.total', 3)
            ->where('mailings.data.0.sent', 2)
            ->where('mailings.data.0.failed', 1)
            ->where('mailings.data.0.status_label', '2 / 3')
        );
});

test('a finished batch with only successful slips is labelled as sent', function () {
    $mailing = Mailing::factory()->create([
        'source' => Mailing::SOURCE_MEMBERS,
        'label' => 'Uplatnice 09/2026',
        'completed_at' => now(),
    ]);

    InvoiceMailing::factory()->count(14)->create([
        'mailing_id' => $mailing->id,
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now(),
    ]);

    $this->get(route('mailings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mailings.data.0.group_name', 'Odabrani članovi')
            ->where('mailings.data.0.status_label', '14 poslano')
            ->where('mailings.data.0.total', 14)
        );
});

test('closing a mailing cancels queued recipients and drops it from the active list', function () {
    $mailing = Mailing::factory()->create([
        'label' => 'Uplatnice 09/2026',
        'completed_at' => null,
    ]);

    $queued = InvoiceMailing::factory()->queued()->create([
        'mailing_id' => $mailing->id,
    ]);
    $sent = InvoiceMailing::factory()->create([
        'mailing_id' => $mailing->id,
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now(),
    ]);

    $this->post(route('mailings.close', $mailing))
        ->assertRedirect()
        ->assertSessionHas('success', 'Slanje je označeno kao završeno.');

    expect($queued->fresh()->status)->toBe(InvoiceMailing::STATUS_CANCELLED)
        ->and($sent->fresh()->status)->toBe(InvoiceMailing::STATUS_SENT)
        ->and($mailing->fresh()->closed_at)->not->toBeNull()
        ->and($mailing->fresh()->completed_at)->not->toBeNull();

    $activeIds = collect(app(\App\Services\MailingActivitySnapshot::class)->toArray()['active'])->pluck('id');
    expect($activeIds)->not->toContain($mailing->id);

    $this->get(route('mailings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mailings.data.0.status_label', 'Zatvoreno')
            ->where('mailings.data.0.is_closed', true)
            ->where('mailings.data.0.queued', 0));
});

test('deleting a mailing removes the batch and its recipients', function () {
    $mailing = Mailing::factory()->create([
        'label' => 'Uplatnice 09/2026',
    ]);
    $recipient = InvoiceMailing::factory()->queued()->create([
        'mailing_id' => $mailing->id,
    ]);

    $this->delete(route('mailings.destroy', $mailing))
        ->assertRedirect(route('mailings.index'))
        ->assertSessionHas('success', 'Slanje je obrisano iz evidencije.');

    $this->assertDatabaseMissing('mailings', ['id' => $mailing->id]);
    $this->assertDatabaseMissing('invoice_mailings', ['id' => $recipient->id]);
});

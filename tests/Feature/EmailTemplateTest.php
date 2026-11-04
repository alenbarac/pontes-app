<?php

use App\Mail\PaymentSlipMailable;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\User;
use App\Models\Workshop;
use App\Services\PaymentSlipPdfService;
use Inertia\Testing\AssertableInertia as Assert;

test('placeholder text drops an empty name and studio', function () {
    expect(EmailTemplate::interpolate('Poštovani/na {ime},', ['ime' => '']))->toBe('Poštovani,');
    expect(EmailTemplate::interpolate('Poštovani/na {ime},', ['ime' => 'Ivana Anić']))->toBe('Poštovani/na Ivana Anić,');
    expect(EmailTemplate::interpolate('Uplatnica - {studio}', ['studio' => '']))->toBe('Uplatnica');
    expect(EmailTemplate::interpolate('Uplatnica - {studio}', ['studio' => 'Dramski studio Pontes']))
        ->toBe('Uplatnica - Dramski studio Pontes');
    expect(EmailTemplate::interpolate('Bok {ime}!', ['ime' => '']))->toBe('Bok!');
});

test('email templates are listed for staff', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('email-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EmailTemplates/Index')
            ->has('templates', 3)
            ->where('templates.0.name', 'Uplatnica')
            ->where('templates.0.subject', 'Uplatnica - {opis}')
            ->where('templates.1.name', 'Podsjetnik za uplatu')
            ->where('templates.1.subject', 'Podsjetnik za uplatu - {opis}')
            ->where('templates.2.name', 'Dokument člana')
            ->where('templates.2.subject', '{naziv_dokumenta} - {ime}')
        );
});

test('staff can change the slip email and the sent message uses that text', function () {
    config([
        'pontes.recipient_name' => 'Dramski studio Pontes',
        'pontes.currency' => 'EUR',
    ]);

    $this->mock(PaymentSlipPdfService::class, function ($mock) {
        $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('output')->andReturn('%PDF');
        $mock->shouldReceive('generate')->andReturn($pdf);
    });

    $this->actingAs(User::factory()->create())
        ->patch(route('email-templates.update', EmailTemplate::SLIP), [
            'subject' => 'Račun - {opis}',
            'heading' => 'Račun za {studio}',
            'greeting' => 'Bok {ime}!',
            'intro' => "Molimo uplatu.\nHvala.",
            'sign_off' => 'Srdačan pozdrav,',
        ])
        ->assertRedirect(route('email-templates.edit', EmailTemplate::SLIP));

    $template = EmailTemplate::forKey(EmailTemplate::SLIP);
    expect($template->subject)->toBe('Račun - {opis}')
        ->and($template->intro)->toBe("Molimo uplatu.\nHvala.");

    $member = Member::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Anić',
        'slip_payer_name' => 'Ivana Anić',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => Workshop::factory()->create()->id,
        'amount_due' => 45,
        'due_date' => '2026-10-15',
        'notes' => 'Članarina za 10/2026',
    ]);

    $mail = new PaymentSlipMailable($invoice, 'parent@example.test');

    $mail->assertHasSubject('Račun - Članarina za 10/2026');
    $mail->assertSeeInHtml('Račun za Dramski studio Pontes');
    $mail->assertSeeInHtml('Bok Ivana Anić!');
    $mail->assertSeeInHtml('Molimo uplatu.');
    $mail->assertSeeInHtml('Hvala.');
    $mail->assertSeeInText('Molimo uplatu.');
    $mail->assertSeeInHtml('Srdačan pozdrav,');
    $mail->assertDontSeeInHtml('U prilogu Vam šaljemo uplatnicu');

    $reminder = EmailTemplate::forKey(EmailTemplate::REMINDER);
    expect($reminder->subject)->toBe('Podsjetnik za uplatu - {opis}');
});

test('resetting a template restores the original wording', function () {
    $this->actingAs(User::factory()->create());

    EmailTemplate::forKey(EmailTemplate::REMINDER)->update([
        'subject' => 'Drugi predmet',
        'intro' => 'Drugi uvod',
    ]);

    $this->post(route('email-templates.reset', EmailTemplate::REMINDER))
        ->assertRedirect(route('email-templates.edit', EmailTemplate::REMINDER));

    $template = EmailTemplate::forKey(EmailTemplate::REMINDER);
    expect($template->subject)->toBe('Podsjetnik za uplatu - {opis}')
        ->and($template->intro)->toBe('Unutar naše evidencije ustanovili smo da nije podmiren račun. U prilogu ponovno šaljemo uplatnicu.');
});

test('an unknown template is not editable', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('email-templates.edit', 'nepostoji'))->assertNotFound();
});

<?php

use App\Jobs\SendInvoiceMailing;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\MailSetting;
use App\Models\Member;
use App\Models\User;
use App\Support\MailRecipientAllowlist;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the sending page shows test mode and the allowlist', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);
    MailSetting::current()->update(['test_sending' => true]);

    $this->get(route('email-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EmailSettings/Edit')
            ->where('setting.test_sending', true)
            ->where('allowlist.0', 'staff@example.test')
        );
});

test('turning off test mode requires confirmation and then ignores the allowlist', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);
    MailSetting::current()->update(['test_sending' => true]);
    Queue::fake();

    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $this->patch(route('email-settings.update'), [
        'test_sending' => false,
    ])->assertSessionHasErrors('confirm_live');

    expect(MailSetting::testSending())->toBeTrue();

    $this->postJson(route('invoices.sendEmail', $invoice))
        ->assertStatus(422)
        ->assertJsonPath('message', MailRecipientAllowlist::REFUSAL_MESSAGE);

    $this->patch(route('email-settings.update'), [
        'test_sending' => false,
        'confirm_live' => true,
    ])->assertRedirect();

    expect(MailSetting::testSending())->toBeFalse();

    $this->postJson(route('invoices.sendEmail', $invoice))
        ->assertOk();

    Queue::assertPushed(SendInvoiceMailing::class);
});

test('test mode cannot be turned on while the allowlist is empty', function () {
    config(['mail.recipient_allowlist' => '']);
    MailSetting::current()->update(['test_sending' => false]);

    $this->patch(route('email-settings.update'), [
        'test_sending' => true,
    ])->assertSessionHasErrors('test_sending');

    expect(MailSetting::testSending())->toBeFalse();
});

test('test mode with an empty allowlist sends nothing', function () {
    config(['mail.recipient_allowlist' => '']);
    MailSetting::current()->update(['test_sending' => true]);
    Queue::fake();

    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $this->postJson(route('invoices.sendEmail', $invoice))
        ->assertStatus(422)
        ->assertJsonPath('message', MailRecipientAllowlist::EMPTY_LIST_MESSAGE);

    Queue::assertNothingPushed();
});

test('a queued slip follows the mode at send time', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);
    Mail::fake();

    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);
    $mailing = InvoiceMailing::factory()->queued()->create([
        'invoice_id' => $invoice->id,
        'recipient' => 'parent@example.test',
    ]);

    MailSetting::current()->update(['test_sending' => true]);
    (new SendInvoiceMailing($mailing->id))->handle();

    expect($mailing->fresh()->status)->toBe(InvoiceMailing::STATUS_FAILED)
        ->and($mailing->fresh()->error)->toBe(MailRecipientAllowlist::BLOCKED_MESSAGE);
    Mail::assertNothingSent();

    $mailing->refresh();
    $mailing->update([
        'status' => InvoiceMailing::STATUS_QUEUED,
        'error' => null,
    ]);
    MailSetting::current()->update(['test_sending' => false]);
    (new SendInvoiceMailing($mailing->id))->handle();

    expect($mailing->fresh()->status)->toBe(InvoiceMailing::STATUS_SENT);
    Mail::assertSent(\App\Mail\PaymentSlipMailable::class, fn ($mail) => $mail->hasTo('parent@example.test'));
});

<?php

use App\Jobs\SendInvoiceMailing;
use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Mailing;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupWorkshop;
use App\Models\User;
use App\Models\Workshop;
use App\Support\MailRecipientAllowlist;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config(['mail.recipient_allowlist' => '']);
    Mail::fake();
    Queue::fake();
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    config(['mail.recipient_allowlist' => '']);
});

test('a single send is refused before a row is queued when the recipient is outside the allowlist', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);

    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $this->postJson(route('invoices.sendEmail', $invoice))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'recipient_not_allowed')
        ->assertJsonPath('message', MailRecipientAllowlist::REFUSAL_MESSAGE);

    Queue::assertNothingPushed();
    Mail::assertNothingSent();
    $this->assertDatabaseCount('invoice_mailings', 0);
    $this->assertDatabaseCount('mailings', 0);
});

test('a single send still queues when the allowlist matches the recipient ignoring case', function () {
    config(['mail.recipient_allowlist' => 'Parent@Example.test']);

    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $this->postJson(route('invoices.sendEmail', $invoice))
        ->assertOk()
        ->assertJsonPath('recipient', 'parent@example.test');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    $this->assertDatabaseCount('invoice_mailings', 1);
});

test('a bulk send queues nothing when any recipient is outside the allowlist', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);

    $staff = Member::factory()->create(['invoice_email' => 'staff@example.test']);
    $parent = Member::factory()->create(['invoice_email' => 'parent@example.test']);
    $invoices = collect([
        Invoice::factory()->create(['member_id' => $staff->id]),
        Invoice::factory()->create(['member_id' => $parent->id]),
    ]);

    $this->postJson(route('invoices.bulkSendSlipEmails'), [
        'invoice_ids' => $invoices->pluck('id')->all(),
    ])->assertStatus(422)
        ->assertJsonPath('message', MailRecipientAllowlist::REFUSAL_MESSAGE);

    Queue::assertNothingPushed();
    Mail::assertNothingSent();
    $this->assertDatabaseCount('invoice_mailings', 0);
    $this->assertDatabaseCount('mailings', 0);
});

test('member and group sends are refused before any row is queued', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);

    $workshop = Workshop::factory()->create();
    $group = MemberGroup::query()->create(['name' => 'Grupa A']);
    $staff = Member::factory()->create(['invoice_email' => 'staff@example.test']);
    $parent = Member::factory()->create(['invoice_email' => 'parent@example.test']);

    foreach ([$staff, $parent] as $member) {
        MemberGroupWorkshop::query()->create([
            'member_id' => $member->id,
            'workshop_id' => $workshop->id,
            'member_group_id' => $group->id,
        ]);
        Invoice::factory()->create([
            'member_id' => $member->id,
            'workshop_id' => $workshop->id,
            'due_date' => '2026-10-15',
        ]);
    }

    $this->postJson(route('members.bulkSendSlipEmails'), [
        'member_ids' => [$staff->id, $parent->id],
        'month' => '2026-10',
    ])->assertStatus(422)
        ->assertJsonPath('message', MailRecipientAllowlist::REFUSAL_MESSAGE);

    $this->postJson(route('member-groups.bulk-send-slip-emails', $group), [
        'month' => '2026-10',
        'send_scope' => 'all',
    ])->assertStatus(422)
        ->assertJsonPath('message', MailRecipientAllowlist::REFUSAL_MESSAGE);

    Queue::assertNothingPushed();
    $this->assertDatabaseCount('invoice_mailings', 0);
    $this->assertDatabaseCount('mailings', 0);
});

test('an already sent parent address does not block a new staff slip', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);

    $staff = Member::factory()->create(['invoice_email' => 'staff@example.test']);
    $parent = Member::factory()->create(['invoice_email' => 'parent@example.test']);
    $staffInvoice = Invoice::factory()->create([
        'member_id' => $staff->id,
        'due_date' => '2026-10-15',
    ]);
    $parentInvoice = Invoice::factory()->create([
        'member_id' => $parent->id,
        'due_date' => '2026-10-15',
    ]);
    InvoiceMailing::factory()->create([
        'invoice_id' => $parentInvoice->id,
        'recipient' => 'parent@example.test',
        'status' => InvoiceMailing::STATUS_SENT,
    ]);

    $this->postJson(route('members.bulkSendSlipEmails'), [
        'member_ids' => [$staff->id, $parent->id],
        'month' => '2026-10',
    ])->assertOk()
        ->assertJsonPath('queued', 1)
        ->assertJsonCount(1, 'skipped_already_sent');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    $this->assertDatabaseHas('invoice_mailings', [
        'invoice_id' => $staffInvoice->id,
        'recipient' => 'staff@example.test',
        'status' => InvoiceMailing::STATUS_QUEUED,
    ]);
});

test('resending a parent address refuses the whole send', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);

    $staff = Member::factory()->create(['invoice_email' => 'staff@example.test']);
    $parent = Member::factory()->create(['invoice_email' => 'parent@example.test']);
    Invoice::factory()->create([
        'member_id' => $staff->id,
        'due_date' => '2026-10-15',
    ]);
    $parentInvoice = Invoice::factory()->create([
        'member_id' => $parent->id,
        'due_date' => '2026-10-15',
    ]);
    InvoiceMailing::factory()->create([
        'invoice_id' => $parentInvoice->id,
        'recipient' => 'parent@example.test',
        'status' => InvoiceMailing::STATUS_SENT,
    ]);

    $this->postJson(route('members.bulkSendSlipEmails'), [
        'member_ids' => [$staff->id, $parent->id],
        'month' => '2026-10',
        'resend' => true,
    ])->assertStatus(422)
        ->assertJsonPath('message', MailRecipientAllowlist::REFUSAL_MESSAGE);

    Queue::assertNothingPushed();
    expect(InvoiceMailing::query()->count())->toBe(1);
});

test('retry refuses every failed row when one recipient is outside the allowlist', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);

    $mailing = Mailing::factory()->create([
        'completed_at' => now(),
    ]);
    $failedStaff = InvoiceMailing::factory()->failed()->create([
        'mailing_id' => $mailing->id,
        'recipient' => 'staff@example.test',
    ]);
    InvoiceMailing::factory()->failed()->create([
        'mailing_id' => $mailing->id,
        'recipient' => 'parent@example.test',
    ]);

    $this->post(route('mailings.retry-failed', $mailing))
        ->assertRedirect()
        ->assertSessionHas('error', MailRecipientAllowlist::REFUSAL_MESSAGE);

    Queue::assertNothingPushed();
    expect(InvoiceMailing::query()->where('mailing_id', $mailing->id)->count())->toBe(2)
        ->and($failedStaff->fresh()->status)->toBe(InvoiceMailing::STATUS_FAILED)
        ->and($mailing->fresh()->completed_at)->not->toBeNull();
});

test('the job does not open smtp for an address outside the allowlist', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);

    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);
    $batch = Mailing::factory()->create();
    $mailing = InvoiceMailing::factory()->queued()->create([
        'mailing_id' => $batch->id,
        'invoice_id' => $invoice->id,
        'recipient' => 'parent@example.test',
    ]);

    (new SendInvoiceMailing($mailing->id))->handle();

    Mail::assertNothingSent();

    $mailing->refresh();
    expect($mailing->status)->toBe(InvoiceMailing::STATUS_FAILED)
        ->and($mailing->error)->toBe(MailRecipientAllowlist::BLOCKED_MESSAGE)
        ->and($mailing->sent_at)->toBeNull()
        ->and($batch->fresh()->completed_at)->not->toBeNull();
});

test('the job still sends an allowlisted recipient', function () {
    config([
        'mail.recipient_allowlist' => 'staff@example.test',
        'mail.bulk_sends_per_second' => 1,
        'mail.bulk_throttle_seconds' => 0,
    ]);
    RateLimiter::clear(SendInvoiceMailing::SEND_SLOT);
    Cache::forget(SendInvoiceMailing::SEND_SLOT.':gate');

    $member = Member::factory()->create([
        'invoice_email' => 'staff@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);
    $mailing = InvoiceMailing::factory()->queued()->create([
        'invoice_id' => $invoice->id,
        'recipient' => 'Staff@Example.test',
    ]);

    (new SendInvoiceMailing($mailing->id))->handle();

    Mail::assertSent(PaymentSlipMailable::class, fn ($mail) => $mail->hasTo('Staff@Example.test'));

    expect($mailing->fresh()->status)->toBe(InvoiceMailing::STATUS_SENT);
});

test('mail test refuses an address outside the allowlist and sends one on the list', function () {
    config(['mail.recipient_allowlist' => 'staff@example.test']);

    $parent = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $parent->id,
    ]);

    $this->artisan('mail:test', ['--to' => 'parent@example.test'])
        ->expectsOutputToContain(MailRecipientAllowlist::BLOCKED_MESSAGE)
        ->assertExitCode(1);

    $this->artisan('mail:test', ['--invoice' => $invoice->id])
        ->expectsOutputToContain(MailRecipientAllowlist::BLOCKED_MESSAGE)
        ->assertExitCode(1);

    $this->artisan('mail:test', [
        '--invoice' => $invoice->id,
        '--to' => 'staff@example.test',
    ])->assertSuccessful();

    Mail::assertSent(PaymentSlipMailable::class, 1);
    Mail::assertSent(PaymentSlipMailable::class, fn ($mail) => $mail->hasTo('staff@example.test'));
});

test('mail test with an empty allowlist still sends', function () {
    Mail::swap(Mail::getFacadeRoot()->manager);

    Event::fake([MessageSent::class]);

    $this->artisan('mail:test', ['--to' => 'parent@example.test'])
        ->expectsOutputToContain('Test email sent successfully')
        ->assertSuccessful();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return collect($event->message->getTo())
            ->contains(fn ($address) => $address->getAddress() === 'parent@example.test');
    });
});

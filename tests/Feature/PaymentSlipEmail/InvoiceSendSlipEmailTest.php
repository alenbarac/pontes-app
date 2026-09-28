<?php

use App\Jobs\SendInvoiceMailing;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupWorkshop;
use App\Models\User;
use App\Models\Workshop;
use App\Services\PaymentSlipEmailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    $this->actingAs(User::factory()->create());
});

test('single send queues a slip mailing and dispatches SendInvoiceMailing', function () {
    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $response = $this->postJson(route('invoices.sendEmail', $invoice));

    $response->assertOk()
        ->assertJsonPath('recipient', 'parent@example.test');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    Mail::assertNothingSent();

    $this->assertDatabaseHas('invoice_mailings', [
        'invoice_id' => $invoice->id,
        'member_id' => $member->id,
        'type' => InvoiceMailing::TYPE_SLIP,
        'recipient' => 'parent@example.test',
        'status' => InvoiceMailing::STATUS_QUEUED,
        'error' => null,
        'sent_at' => null,
    ]);
});

test('single send returns 422 when invoice_email missing', function () {
    $member = Member::factory()->create([
        'invoice_email' => null,
        'email' => 'fallback@example.test', // intentionally not used
    ]);
    $invoice = Invoice::factory()->create(['member_id' => $member->id]);

    $response = $this->postJson(route('invoices.sendEmail', $invoice));

    $response->assertStatus(422)
        ->assertJsonPath('reason', 'no_email');
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
    $this->assertDatabaseCount('invoice_mailings', 0);
});

test('bulk send sends per invoice and skips members with no invoice_email', function () {
    $withEmail = Member::factory()->count(2)->create(['invoice_email' => fake()->unique()->safeEmail]);
    $withoutEmail = Member::factory()->create(['invoice_email' => null]);

    $invoices = collect()
        ->push(Invoice::factory()->create(['member_id' => $withEmail[0]->id]))
        ->push(Invoice::factory()->create(['member_id' => $withEmail[1]->id]))
        ->push(Invoice::factory()->create(['member_id' => $withoutEmail->id]));

    $response = $this->postJson(route('invoices.bulkSendSlipEmails'), [
        'invoice_ids' => $invoices->pluck('id')->all(),
    ]);

    $response->assertOk()
        ->assertJsonPath('queued', 2)
        ->assertJsonCount(1, 'skipped_no_email')
        ->assertJsonCount(0, 'failed');

    Queue::assertPushed(SendInvoiceMailing::class, 2);
    Mail::assertNothingSent();
});

test('bulk send rejects batches over the cap', function () {
    $invoices = Invoice::factory()->count(2)->create();
    $oversized = array_pad($invoices->pluck('id')->all(), 51, $invoices->first()->id);

    $response = $this->postJson(route('invoices.bulkSendSlipEmails'), [
        'invoice_ids' => $oversized,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_ids']);

    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('second send skips an invoice that already has a successful slip', function () {
    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $this->postJson(route('invoices.sendEmail', $invoice))->assertOk();

    InvoiceMailing::query()->where('invoice_id', $invoice->id)->update([
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now(),
    ]);

    $response = $this->postJson(route('invoices.sendEmail', $invoice));

    $response->assertStatus(422)
        ->assertJsonPath('reason', 'already_sent');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    Mail::assertNothingSent();
    expect(InvoiceMailing::query()->where('invoice_id', $invoice->id)->where('status', InvoiceMailing::STATUS_SENT)->count())->toBe(1);
});

test('resend confirmed sends again and appends another slip mailing', function () {
    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $this->postJson(route('invoices.sendEmail', $invoice))->assertOk();

    InvoiceMailing::query()->where('invoice_id', $invoice->id)->update([
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now()->subMinute(),
    ]);

    $this->postJson(route('invoices.sendEmail', $invoice), ['resend' => true])
        ->assertOk()
        ->assertJsonPath('recipient', 'parent@example.test');

    Queue::assertPushed(SendInvoiceMailing::class, 2);
    Mail::assertNothingSent();
    expect(InvoiceMailing::query()->where('invoice_id', $invoice->id)->where('status', InvoiceMailing::STATUS_QUEUED)->count())->toBe(1)
        ->and(InvoiceMailing::query()->where('invoice_id', $invoice->id)->where('status', InvoiceMailing::STATUS_SENT)->count())->toBe(1);
});

test('bulk send skips slips that were already sent unless resend is confirmed', function () {
    $already = Member::factory()->create(['invoice_email' => 'already@example.test']);
    $fresh = Member::factory()->create(['invoice_email' => 'fresh@example.test']);

    $sentInvoice = Invoice::factory()->create(['member_id' => $already->id]);
    $newInvoice = Invoice::factory()->create(['member_id' => $fresh->id]);

    InvoiceMailing::factory()->create([
        'invoice_id' => $sentInvoice->id,
        'recipient' => 'already@example.test',
        'type' => InvoiceMailing::TYPE_SLIP,
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now()->subDay(),
    ]);

    $skipped = $this->postJson(route('invoices.bulkSendSlipEmails'), [
        'invoice_ids' => [$sentInvoice->id, $newInvoice->id],
    ]);

    $skipped->assertOk()
        ->assertJsonPath('queued', 1)
        ->assertJsonCount(1, 'skipped_already_sent')
        ->assertJsonPath('skipped_already_sent.0.invoice_id', $sentInvoice->id)
        ->assertJsonPath('message', 'Pokrenuto je slanje 1 uplatnice. 1 uplatnica je već poslana i zato je preskočena.');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    Mail::assertNothingSent();

    $resent = $this->postJson(route('invoices.bulkSendSlipEmails'), [
        'invoice_ids' => [$sentInvoice->id, $newInvoice->id],
        'resend' => true,
    ]);

    $resent->assertOk()
        ->assertJsonPath('queued', 2)
        ->assertJsonCount(0, 'skipped_already_sent');

    Queue::assertPushed(SendInvoiceMailing::class, 3);
    Mail::assertNothingSent();
});

test('a failed slip mailing does not block the next send', function () {
    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    InvoiceMailing::factory()->failed()->create([
        'invoice_id' => $invoice->id,
        'recipient' => 'parent@example.test',
    ]);

    $this->postJson(route('invoices.sendEmail', $invoice))
        ->assertOk()
        ->assertJsonPath('recipient', 'parent@example.test');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    Mail::assertNothingSent();
    expect(InvoiceMailing::query()->where('invoice_id', $invoice->id)->where('status', InvoiceMailing::STATUS_QUEUED)->count())->toBe(1);
});

test('preview counts already sent slips and excludes them from deliverable', function () {
    $sentMember = Member::factory()->create(['invoice_email' => 'sent@example.test']);
    $openMember = Member::factory()->create(['invoice_email' => 'open@example.test']);
    $noEmail = Member::factory()->create(['invoice_email' => null]);

    $sentInvoice = Invoice::factory()->create([
        'member_id' => $sentMember->id,
        'due_date' => '2026-09-15',
    ]);
    Invoice::factory()->create([
        'member_id' => $openMember->id,
        'due_date' => '2026-09-15',
    ]);
    Invoice::factory()->create([
        'member_id' => $noEmail->id,
        'due_date' => '2026-09-15',
    ]);

    InvoiceMailing::factory()->create([
        'invoice_id' => $sentInvoice->id,
        'type' => InvoiceMailing::TYPE_SLIP,
        'status' => InvoiceMailing::STATUS_SENT,
    ]);
    InvoiceMailing::factory()->failed()->create([
        'invoice_id' => $sentInvoice->id,
        'type' => InvoiceMailing::TYPE_SLIP,
    ]);

    $this->postJson(route('members.bulkSendSlipEmails.preview'), [
        'member_ids' => [$sentMember->id, $openMember->id, $noEmail->id],
        'month' => '2026-09',
    ])->assertOk()
        ->assertJsonPath('invoices_count', 3)
        ->assertJsonPath('already_sent_count', 1)
        ->assertJsonPath('deliverable_count', 1)
        ->assertJsonPath('members_without_invoice_email', 1);

    $this->postJson(route('members.bulkSendSlipEmails'), [
        'member_ids' => [$sentMember->id, $openMember->id, $noEmail->id],
        'month' => '2026-09',
    ])->assertOk()
        ->assertJsonPath('queued', 1)
        ->assertJsonCount(1, 'skipped_already_sent')
        ->assertJsonCount(1, 'skipped_no_email');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    Mail::assertNothingSent();
});

test('only a successful slip mailing marks an invoice as already emailed', function () {
    $member = Member::factory()->create(['invoice_email' => 'parent@example.test']);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'due_date' => '2026-09-15',
    ]);

    InvoiceMailing::factory()->create([
        'invoice_id' => $invoice->id,
        'type' => InvoiceMailing::TYPE_REMINDER,
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => now()->subHour(),
    ]);
    InvoiceMailing::factory()->failed()->create([
        'invoice_id' => $invoice->id,
    ]);

    $invoice->load('latestSuccessfulSlipMailing');
    expect($invoice->latestSuccessfulSlipMailing)->toBeNull();

    $this->postJson(route('invoices.sendEmail', $invoice))->assertOk();

    $sentAt = now()->subMinutes(5);
    $older = InvoiceMailing::factory()->create([
        'invoice_id' => $invoice->id,
        'recipient' => 'older@example.test',
        'sent_at' => $sentAt,
    ]);
    $latest = InvoiceMailing::factory()->create([
        'invoice_id' => $invoice->id,
        'recipient' => 'latest@example.test',
        'sent_at' => now(),
    ]);

    $invoice->unsetRelation('latestSuccessfulSlipMailing');
    $invoice->load('latestSuccessfulSlipMailing');

    expect($invoice->latestSuccessfulSlipMailing?->is($latest))->toBeTrue()
        ->and($invoice->latestSuccessfulSlipMailing?->is($older))->toBeFalse();
});

test('invoice list exposes the latest successful slip mailing', function () {
    $member = Member::factory()->create(['invoice_email' => 'parent@example.test']);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'due_date' => '2026-09-15',
    ]);
    $mailing = InvoiceMailing::factory()->create([
        'invoice_id' => $invoice->id,
        'recipient' => 'parent@example.test',
        'sent_at' => '2026-09-20 10:15:00',
    ]);

    $this->get(route('invoices.index', ['filter' => $invoice->reference_code]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->where('invoices.data.0.id', $invoice->id)
            ->where('invoices.data.0.latest_successful_slip_mailing.id', $mailing->id)
            ->where('invoices.data.0.latest_successful_slip_mailing.status', InvoiceMailing::STATUS_SENT)
            ->where('invoices.data.0.latest_successful_slip_mailing.recipient', 'parent@example.test')
        );

    $this->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Members/Show')
            ->where('invoicesByWorkshop', function ($groups) use ($invoice) {
                $row = collect($groups)->flatten(1)->firstWhere('id', $invoice->id);

                return is_array($row)
                    && ($row['slip_sent_at'] ?? null) !== null
                    && str_contains((string) $row['slip_sent_at'], '2026-09-20');
            })
        );
});

test('group send all refuses more than 100 invoices', function () {
    $workshop = Workshop::factory()->create();
    $group = MemberGroup::query()->create(['name' => 'Velika grupa']);
    $member = Member::factory()->create(['invoice_email' => 'parent@example.test']);

    MemberGroupWorkshop::query()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'member_group_id' => $group->id,
    ]);

    Invoice::factory()->count(PaymentSlipEmailService::MAX_INVOICES_PER_SEND + 1)->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'due_date' => '2026-09-15',
    ]);

    $this->postJson(route('member-groups.bulk-send-slip-emails', $group), [
        'month' => '2026-09',
        'send_scope' => 'all',
    ])->assertStatus(422)
        ->assertJsonPath('message', PaymentSlipEmailService::maxInvoicesMessage());

    Queue::assertNothingPushed();
    $this->assertDatabaseCount('invoice_mailings', 0);
});

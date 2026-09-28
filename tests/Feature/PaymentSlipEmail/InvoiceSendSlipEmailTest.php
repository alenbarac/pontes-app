<?php

use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());
});

test('single send dispatches a PaymentSlipMailable to invoice_email', function () {
    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $response = $this->postJson(route('invoices.sendEmail', $invoice));

    $response->assertOk()
        ->assertJsonPath('recipient', 'parent@example.test');

    Mail::assertSent(PaymentSlipMailable::class, fn ($mail) => $mail->hasTo('parent@example.test')
        && $mail->invoice->is($invoice));

    $this->assertDatabaseHas('invoice_mailings', [
        'invoice_id' => $invoice->id,
        'member_id' => $member->id,
        'type' => InvoiceMailing::TYPE_SLIP,
        'recipient' => 'parent@example.test',
        'status' => InvoiceMailing::STATUS_SENT,
        'error' => null,
    ]);

    expect(InvoiceMailing::query()->where('invoice_id', $invoice->id)->first()?->sent_at)->not->toBeNull();
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
        ->assertJsonPath('sent', 2)
        ->assertJsonCount(1, 'skipped_no_email')
        ->assertJsonCount(0, 'failed');

    Mail::assertSent(PaymentSlipMailable::class, 2);
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
});

test('second send skips an invoice that already has a successful slip', function () {
    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    $this->postJson(route('invoices.sendEmail', $invoice))->assertOk();

    $response = $this->postJson(route('invoices.sendEmail', $invoice));

    $response->assertStatus(422)
        ->assertJsonPath('reason', 'already_sent');

    Mail::assertSent(PaymentSlipMailable::class, 1);
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

    $this->postJson(route('invoices.sendEmail', $invoice), ['resend' => true])
        ->assertOk()
        ->assertJsonPath('recipient', 'parent@example.test');

    Mail::assertSent(PaymentSlipMailable::class, 2);
    expect(InvoiceMailing::query()->where('invoice_id', $invoice->id)->where('status', InvoiceMailing::STATUS_SENT)->count())->toBe(2);
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
        ->assertJsonPath('sent', 1)
        ->assertJsonCount(1, 'skipped_already_sent')
        ->assertJsonPath('skipped_already_sent.0.invoice_id', $sentInvoice->id)
        ->assertJsonPath('message', 'Poslano: 1. Već poslano (preskočeno): 1.');

    Mail::assertSent(PaymentSlipMailable::class, 1);

    $resent = $this->postJson(route('invoices.bulkSendSlipEmails'), [
        'invoice_ids' => [$sentInvoice->id, $newInvoice->id],
        'resend' => true,
    ]);

    $resent->assertOk()
        ->assertJsonPath('sent', 2)
        ->assertJsonCount(0, 'skipped_already_sent');

    Mail::assertSent(PaymentSlipMailable::class, 3);
});

test('a failed slip mailing stores a short error and no smtp detail', function () {
    $member = Member::factory()->create([
        'invoice_email' => 'parent@example.test',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
    ]);

    Mail::shouldReceive('to')
        ->once()
        ->andReturn(new class
        {
            public function send(): void
            {
                throw new RuntimeException('535 5.7.8 authentication failed for secret-host');
            }
        });

    $this->postJson(route('invoices.sendEmail', $invoice))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'mail_error')
        ->assertJsonPath('message', 'Došlo je do greške pri slanju e-maila. Molimo pokušajte ponovno.');

    $mailing = InvoiceMailing::query()->where('invoice_id', $invoice->id)->first();
    expect($mailing)->not->toBeNull()
        ->and($mailing->status)->toBe(InvoiceMailing::STATUS_FAILED)
        ->and($mailing->error)->toBe('Slanje uplatnice nije uspjelo.')
        ->and($mailing->sent_at)->toBeNull()
        ->and($mailing->error)->not->toContain('secret-host');
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

    Mail::assertSent(PaymentSlipMailable::class, 1);
    expect(InvoiceMailing::query()->where('invoice_id', $invoice->id)->where('status', InvoiceMailing::STATUS_SENT)->count())->toBe(1);
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
        ->assertJsonPath('sent', 1)
        ->assertJsonCount(1, 'skipped_already_sent')
        ->assertJsonCount(1, 'skipped_no_email');

    Mail::assertSent(PaymentSlipMailable::class, 1);
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

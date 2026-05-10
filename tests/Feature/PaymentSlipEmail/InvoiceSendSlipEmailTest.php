<?php

use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

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
});

test('single send returns 422 when invoice_email missing', function () {
    $member = Member::factory()->create([
        'invoice_email' => null,
        'email' => 'fallback@example.test', // intentionally not used
    ]);
    $invoice = Invoice::factory()->create(['member_id' => $member->id]);

    $response = $this->postJson(route('invoices.sendEmail', $invoice));

    $response->assertStatus(422);
    Mail::assertNothingSent();
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

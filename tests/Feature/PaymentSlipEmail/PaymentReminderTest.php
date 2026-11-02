<?php

use App\Jobs\SendInvoiceMailing;
use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Mailing;
use App\Models\Member;
use App\Models\User;
use App\Models\Workshop;
use App\Services\DashboardFinanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

function invoiceReadyForReminder(array $invoice = [], array $member = []): Invoice
{
    $memberModel = Member::factory()->create(array_merge([
        'invoice_email' => 'parent@example.test',
    ], $member));

    $invoiceModel = Invoice::factory()->create(array_merge([
        'member_id' => $memberModel->id,
        'due_date' => '2026-09-01',
        'payment_status' => 'Otvoreno',
        'notes' => 'Članarina za 09/2026',
    ], $invoice));

    if (($member['invoice_email'] ?? 'parent@example.test') !== null) {
        InvoiceMailing::factory()->create([
            'invoice_id' => $invoiceModel->id,
            'member_id' => $memberModel->id,
            'type' => InvoiceMailing::TYPE_SLIP,
            'status' => InvoiceMailing::STATUS_SENT,
            'recipient' => $memberModel->invoice_email,
            'sent_at' => Carbon::parse('2026-09-02 10:00:00'),
        ]);
    }

    return $invoiceModel->fresh();
}

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    config(['mail.recipient_allowlist' => '']);
    Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00'));
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the suggested list includes every unpaid invoice past the grace period', function () {
    $overdue = invoiceReadyForReminder();
    $mismatch = invoiceReadyForReminder([
        'payment_status' => 'Neusklađeno',
        'due_date' => '2026-10-22',
    ], ['invoice_email' => 'mismatch@example.test']);
    $recent = invoiceReadyForReminder([
        'due_date' => '2026-10-23',
    ], ['invoice_email' => 'recent@example.test']);
    $paid = invoiceReadyForReminder([
        'payment_status' => 'Plaćeno',
        'due_date' => '2026-09-01',
    ], ['invoice_email' => 'paid@example.test']);
    $neverSent = Invoice::factory()->create([
        'member_id' => Member::factory()->create(['invoice_email' => 'new@example.test'])->id,
        'due_date' => '2026-09-01',
        'payment_status' => 'Otvoreno',
    ]);
    $reminded = invoiceReadyForReminder([
        'due_date' => '2026-08-01',
    ], ['invoice_email' => 'reminded@example.test']);
    InvoiceMailing::factory()->create([
        'invoice_id' => $reminded->id,
        'member_id' => $reminded->member_id,
        'type' => InvoiceMailing::TYPE_REMINDER,
        'status' => InvoiceMailing::STATUS_SENT,
        'recipient' => 'reminded@example.test',
        'sent_at' => Carbon::parse('2026-08-20 10:00:00'),
    ]);

    $this->get(route('invoices.reminders'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Reminders')
            ->where('graceDays', 10)
            ->where('invoices', function ($rows) use ($overdue, $mismatch, $recent, $paid, $neverSent, $reminded) {
                $rows = collect($rows);
                $ids = $rows->pluck('id');

                $remindedRow = $rows->firstWhere('id', $reminded->id);

                $neverSentRow = $rows->firstWhere('id', $neverSent->id);

                return $ids->contains($overdue->id)
                    && $ids->contains($mismatch->id)
                    && $ids->contains($reminded->id)
                    && $ids->contains($neverSent->id)
                    && ! $ids->contains($recent->id)
                    && ! $ids->contains($paid->id)
                    && $rows->firstWhere('id', $overdue->id)['selected'] === true
                    && $neverSentRow['selected'] === true
                    && $neverSentRow['slip_mailing'] === null
                    && $remindedRow['selected'] === false
                    && $remindedRow['selectable'] === true;
            }));
});

test('the dashboard count stays at zero until an invoice is past the grace period', function () {
    $this->mock(DashboardFinanceService::class, function ($mock) {
        $mock->shouldReceive('buildMetrics')->andReturn([
            'months' => [],
            'current_month_key' => '',
        ]);
    });

    invoiceReadyForReminder([
        'due_date' => '2026-10-23',
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('reminderNotice.count', 0)
            ->where('reminderNotice.graceDays', 10));

    invoiceReadyForReminder();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reminderNotice.count', 1));
});

test('a manual reminder inside the grace window queues a reminder and leaves the invoice unpaid', function () {
    $invoice = invoiceReadyForReminder([
        'due_date' => '2026-10-30',
        'payment_status' => 'Otvoreno',
    ]);

    $this->postJson(route('invoices.sendReminder', $invoice))
        ->assertOk()
        ->assertJsonPath('recipient', 'parent@example.test');

    Queue::assertPushed(SendInvoiceMailing::class, 1);

    $this->assertDatabaseHas('invoice_mailings', [
        'invoice_id' => $invoice->id,
        'type' => InvoiceMailing::TYPE_REMINDER,
        'recipient' => 'parent@example.test',
        'status' => InvoiceMailing::STATUS_QUEUED,
    ]);

    $this->assertDatabaseHas('mailings', [
        'type' => Mailing::TYPE_REMINDER,
        'label' => 'Podsjetnici 10/2026',
    ]);

    expect($invoice->fresh()->payment_status)->toBe('Otvoreno');
});

test('approved reminder ids are queued and paid or already reminded ids are skipped', function () {
    $ready = invoiceReadyForReminder([], ['invoice_email' => 'ready@example.test']);
    $alsoReady = invoiceReadyForReminder([
        'due_date' => '2026-08-15',
    ], ['invoice_email' => 'also@example.test']);
    $paid = invoiceReadyForReminder([
        'payment_status' => 'Plaćeno',
    ], ['invoice_email' => 'paid@example.test']);
    $reminded = invoiceReadyForReminder([
        'due_date' => '2026-08-01',
    ], ['invoice_email' => 'reminded@example.test']);
    InvoiceMailing::factory()->create([
        'invoice_id' => $reminded->id,
        'member_id' => $reminded->member_id,
        'type' => InvoiceMailing::TYPE_REMINDER,
        'status' => InvoiceMailing::STATUS_SENT,
        'recipient' => 'reminded@example.test',
        'sent_at' => now(),
    ]);

    $this->postJson(route('invoices.bulkSendReminderEmails'), [
        'invoice_ids' => [$ready->id, $alsoReady->id, $paid->id, $reminded->id],
    ])
        ->assertOk()
        ->assertJsonPath('queued', 2)
        ->assertJsonCount(1, 'skipped_not_eligible')
        ->assertJsonCount(1, 'skipped_already_sent');

    expect(InvoiceMailing::query()->where('type', InvoiceMailing::TYPE_REMINDER)->where('status', InvoiceMailing::STATUS_QUEUED)->count())->toBe(2);

    $this->postJson(route('invoices.bulkSendReminderEmails'), [
        'invoice_ids' => [$reminded->id],
        'resend' => true,
    ])->assertOk()->assertJsonPath('queued', 1);

    expect($paid->fresh()->payment_status)->toBe('Plaćeno');
});

test('a member without an invoice email is listed and is not queued', function () {
    $member = Member::factory()->create(['invoice_email' => null]);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'due_date' => '2026-09-01',
        'payment_status' => 'Otvoreno',
    ]);
    InvoiceMailing::factory()->create([
        'invoice_id' => $invoice->id,
        'member_id' => $member->id,
        'type' => InvoiceMailing::TYPE_SLIP,
        'status' => InvoiceMailing::STATUS_SENT,
        'recipient' => 'old@example.test',
        'sent_at' => now(),
    ]);

    $this->get(route('invoices.reminders'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices', function ($rows) use ($invoice) {
                $row = collect($rows)->firstWhere('id', $invoice->id);

                return $row
                    && $row['invoice_email'] === null
                    && $row['selectable'] === false
                    && $row['selected'] === false;
            }));

    $overdueWithoutSlip = Invoice::factory()->create([
        'member_id' => Member::factory()->create(['invoice_email' => 'noslip@example.test'])->id,
        'due_date' => '2026-09-01',
        'payment_status' => 'Otvoreno',
    ]);

    $this->postJson(route('invoices.sendReminder', $overdueWithoutSlip))
        ->assertOk()
        ->assertJsonPath('recipient', 'noslip@example.test');

    $this->assertDatabaseHas('invoice_mailings', [
        'invoice_id' => $overdueWithoutSlip->id,
        'type' => InvoiceMailing::TYPE_REMINDER,
        'status' => InvoiceMailing::STATUS_QUEUED,
    ]);

    $this->postJson(route('invoices.sendReminder', $invoice))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'no_email');

    Queue::assertPushed(SendInvoiceMailing::class, 1);
    $this->assertDatabaseMissing('invoice_mailings', [
        'invoice_id' => $invoice->id,
        'type' => InvoiceMailing::TYPE_REMINDER,
    ]);
});

test('the reminder email uses the reminder subject and intro without the due date', function () {
    config([
        'pontes.recipient_name' => 'Dramski studio Pontes',
        'pontes.email' => 'info@pontes.hr',
        'pontes.currency' => 'EUR',
    ]);

    $member = Member::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Anić',
        'slip_payer_name' => 'Ivana Anić',
        'invoice_email' => 'parent@example.test',
    ]);
    $workshop = Workshop::factory()->create(['name' => 'Dramska radionica']);
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 45,
        'due_date' => '2026-10-15',
        'notes' => 'Članarina za 10/2026',
    ]);

    $mail = new PaymentSlipMailable($invoice, 'parent@example.test', true);

    $mail->assertHasSubject('Podsjetnik za uplatu - Članarina za 10/2026');
    $mail->assertSeeInHtml('Podsjetnik za uplatu');
    $mail->assertSeeInHtml('Unutar naše evidencije ustanovili smo da nije podmiren račun. U prilogu ponovno šaljemo uplatnicu.');
    $mail->assertSeeInHtml('Poštovani/na Ivana Anić,');
    $mail->assertSeeInHtml('Dramska radionica');
    $mail->assertSeeInHtml('45,00 EUR');
    $mail->assertDontSeeInHtml('Rok plaćanja');
    $mail->assertDontSeeInHtml('15.10.2026.');
    $mail->assertDontSeeInText('Rok plaćanja');
    $mail->assertDontSeeInHtml('U prilogu Vam šaljemo uplatnicu');
});

test('the job sends a reminder mailable and a failure stores the reminder message', function () {
    RateLimiter::clear(SendInvoiceMailing::SEND_SLOT);
    Cache::forget(SendInvoiceMailing::SEND_SLOT.':gate');

    $invoice = invoiceReadyForReminder();
    $mailing = InvoiceMailing::factory()->queued()->create([
        'invoice_id' => $invoice->id,
        'member_id' => $invoice->member_id,
        'type' => InvoiceMailing::TYPE_REMINDER,
        'recipient' => 'parent@example.test',
    ]);

    (new SendInvoiceMailing($mailing->id))->handle();

    Mail::assertSent(PaymentSlipMailable::class, fn (PaymentSlipMailable $mail) => $mail->reminder === true
        && $mail->hasTo('parent@example.test')
        && $mail->invoice->is($invoice));

    $failed = InvoiceMailing::factory()->queued()->create([
        'invoice_id' => $invoice->id,
        'member_id' => $invoice->member_id,
        'type' => InvoiceMailing::TYPE_REMINDER,
        'recipient' => 'parent@example.test',
    ]);

    (new SendInvoiceMailing($failed->id))->failed(new RuntimeException('smtp down'));

    expect($failed->fresh()->error)->toBe(InvoiceMailing::REMINDER_FAILURE_MESSAGE)
        ->and($failed->fresh()->status)->toBe(InvoiceMailing::STATUS_FAILED);
});

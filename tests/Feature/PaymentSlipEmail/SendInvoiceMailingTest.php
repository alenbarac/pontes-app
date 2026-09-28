<?php

use App\Jobs\SendInvoiceMailing;
use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Member;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config([
        'mail.bulk_sends_per_second' => 1,
        'mail.bulk_throttle_seconds' => 0,
    ]);

    RateLimiter::clear(SendInvoiceMailing::SEND_SLOT);
    Cache::forget(SendInvoiceMailing::SEND_SLOT.':gate');
});

test('the job marks the mailing sent when the slip email is accepted', function () {
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

    (new SendInvoiceMailing($mailing->id))->handle();

    Mail::assertSent(PaymentSlipMailable::class, fn ($mail) => $mail->hasTo('parent@example.test')
        && $mail->invoice->is($invoice));

    $mailing->refresh();

    expect($mailing->status)->toBe(InvoiceMailing::STATUS_SENT)
        ->and($mailing->error)->toBeNull()
        ->and($mailing->sent_at)->not->toBeNull();
});

test('a second run does not send a slip that is already marked sent', function () {
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

    $job = new SendInvoiceMailing($mailing->id);
    $job->handle();
    $job->handle();

    Mail::assertSent(PaymentSlipMailable::class, 1);
});

test('a send exception leaves the mailing queued until retries are exhausted', function () {
    Mail::shouldReceive('to')
        ->once()
        ->andReturn(new class
        {
            public function send(): void
            {
                throw new RuntimeException('535 5.7.8 authentication failed for secret-host');
            }
        });

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

    $job = new SendInvoiceMailing($mailing->id);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);

    $mailing->refresh();
    expect($mailing->status)->toBe(InvoiceMailing::STATUS_QUEUED)
        ->and($mailing->error)->toBeNull()
        ->and($mailing->sent_at)->toBeNull();

    $job->failed(new RuntimeException('535 5.7.8 authentication failed for secret-host'));

    $mailing->refresh();
    expect($mailing->status)->toBe(InvoiceMailing::STATUS_FAILED)
        ->and($mailing->error)->toBe(InvoiceMailing::FAILURE_MESSAGE)
        ->and($mailing->sent_at)->toBeNull()
        ->and($mailing->error)->not->toContain('secret-host');
});

test('slip jobs retry three exceptions and stay under the flex time limit', function () {
    $job = new SendInvoiceMailing(1);

    expect($job->tries)->toBe(0)
        ->and($job->maxExceptions)->toBe(3)
        ->and($job->timeout)->toBeLessThan(90)
        ->and(SendInvoiceMailing::secondsUntilNextSend())->toBe(2);
});

test('a sandbox rate limit releases the job and leaves the slip queued', function () {
    Mail::shouldReceive('to')
        ->once()
        ->andReturn(new class
        {
            public function send(): void
            {
                throw new RuntimeException('Expected response code "354" but got code "550", with message "550 5.7.0 Too many emails per second. Please upgrade your plan https://mailtrap.io/billing/plans/testing".');
            }
        });

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

    $job = (new SendInvoiceMailing($mailing->id))->withFakeQueueInteractions();
    $job->handle();

    $job->assertReleased(2);

    $mailing->refresh();
    expect($mailing->status)->toBe(InvoiceMailing::STATUS_QUEUED)
        ->and($mailing->error)->toBeNull()
        ->and($mailing->sent_at)->toBeNull();
});

test('a slip waits when another send already holds the slot', function () {
    Mail::fake();

    RateLimiter::hit(SendInvoiceMailing::SEND_SLOT, SendInvoiceMailing::secondsUntilNextSend());

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

    $job = (new SendInvoiceMailing($mailing->id))->withFakeQueueInteractions();
    $job->handle();

    $job->assertReleased(2);
    Mail::assertNothingSent();

    $mailing->refresh();
    expect($mailing->status)->toBe(InvoiceMailing::STATUS_QUEUED);
});

test('repeated sandbox rate limits eventually mark the slip failed', function () {
    Mail::shouldReceive('to')
        ->once()
        ->andReturn(new class
        {
            public function send(): void
            {
                throw new RuntimeException('550 5.7.0 Too many emails per second');
            }
        });

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

    Cache::put(
        SendInvoiceMailing::SEND_SLOT.':provider-limit:'.$mailing->id,
        SendInvoiceMailing::MAX_PROVIDER_LIMIT_DELAYS - 1,
        3600,
    );

    $job = (new SendInvoiceMailing($mailing->id))->withFakeQueueInteractions();
    $job->handle();

    $job->assertFailed();

    $mailing->refresh();
    expect($mailing->status)->toBe(InvoiceMailing::STATUS_FAILED)
        ->and($mailing->error)->toBe(InvoiceMailing::FAILURE_MESSAGE)
        ->and($mailing->error)->not->toContain('550');
});

test('the slip rate limit follows sends per second and a legacy throttle interval', function () {
    config(['mail.bulk_sends_per_second' => 8]);

    expect(SendInvoiceMailing::sendsPerSecond())->toBe(8);

    config([
        'mail.bulk_sends_per_second' => null,
        'mail.bulk_throttle_seconds' => 1.1,
    ]);

    expect(SendInvoiceMailing::sendsPerSecond())->toBe(1);

    config([
        'mail.bulk_sends_per_second' => null,
        'mail.bulk_throttle_seconds' => 0.1,
    ]);

    expect(SendInvoiceMailing::sendsPerSecond())->toBe(10);
});

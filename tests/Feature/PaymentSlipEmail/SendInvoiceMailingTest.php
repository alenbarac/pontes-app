<?php

use App\Jobs\SendInvoiceMailing;
use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Member;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

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
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
});

test('the slip rate limit defaults to one send per second', function () {
    config([
        'mail.bulk_sends_per_second' => null,
        'mail.bulk_throttle_seconds' => 0,
    ]);

    $limit = RateLimiter::limiter(SendInvoiceMailing::RATE_LIMITER)(new stdClass);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(1)
        ->and($limit->decaySeconds)->toBe(1)
        ->and($limit->key)->toBe(SendInvoiceMailing::RATE_LIMITER);
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

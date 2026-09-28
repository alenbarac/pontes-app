<?php

namespace App\Jobs;

use App\Mail\PaymentSlipMailable;
use App\Models\InvoiceMailing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInvoiceMailing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RATE_LIMITER = 'invoice-mailings';

    /**
     * Rate-limit releases increment attempts. Zero means unlimited attempts
     * so a 1/sec sandbox cap cannot fail a job that is only waiting its turn.
     * Real send errors stop after {@see $maxExceptions}.
     */
    public int $tries = 0;

    public int $maxExceptions = 3;

    /**
     * Flex managed-queue workers stop a job after 90 seconds. One slip PDF
     * plus SMTP stays inside that window.
     */
    public int $timeout = 75;

    public function __construct(public int $invoiceMailingId) {}

    /**
     * Messages allowed per second. Mailtrap Sandbox is about 1/sec.
     * `MAIL_BULK_SENDS_PER_SECOND` wins; a legacy `MAIL_BULK_THROTTLE_SECONDS`
     * interval is converted when the newer value is unset. Otherwise 1.
     */
    public static function sendsPerSecond(): int
    {
        $configured = config('mail.bulk_sends_per_second');

        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        $secondsBetween = (float) config('mail.bulk_throttle_seconds', 0);

        if ($secondsBetween > 0) {
            return max(1, (int) floor(1 / $secondsBetween));
        }

        return 1;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited(self::RATE_LIMITER)];
    }

    public function handle(): void
    {
        $mailing = InvoiceMailing::query()
            ->with(['invoice.member', 'invoice.workshop', 'invoice.membershipPlan'])
            ->find($this->invoiceMailingId);

        if ($mailing === null || $mailing->status !== InvoiceMailing::STATUS_QUEUED) {
            return;
        }

        $invoice = $mailing->invoice;

        if ($invoice === null || $invoice->member === null) {
            $this->markFailed($mailing);

            return;
        }

        try {
            Mail::to($mailing->recipient)->send(new PaymentSlipMailable($invoice, $mailing->recipient));
        } catch (Throwable $e) {
            Log::error('Failed to send payment slip email', [
                'invoice_id' => $invoice->id,
                'invoice_mailing_id' => $mailing->id,
                'reference_code' => $invoice->reference_code,
                'recipient_email' => $mailing->recipient,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $mailing->update([
            'status' => InvoiceMailing::STATUS_SENT,
            'error' => null,
            'sent_at' => now(),
        ]);

        Log::info('Payment slip email sent', [
            'invoice_id' => $invoice->id,
            'reference_code' => $invoice->reference_code,
            'recipient_email' => $mailing->recipient,
            'invoice_mailing_id' => $mailing->id,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $mailing = InvoiceMailing::query()->find($this->invoiceMailingId);

        if ($mailing === null) {
            return;
        }

        $this->markFailed($mailing);
    }

    private function markFailed(InvoiceMailing $mailing): void
    {
        if ($mailing->status === InvoiceMailing::STATUS_SENT) {
            return;
        }

        $mailing->update([
            'status' => InvoiceMailing::STATUS_FAILED,
            'error' => InvoiceMailing::FAILURE_MESSAGE,
            'sent_at' => null,
        ]);
    }
}

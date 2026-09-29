<?php

namespace App\Jobs;

use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Mailing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Dispatched after the surrounding database transaction commits, so the
 * invoice_mailings row exists before a worker loads it.
 */
class SendInvoiceMailing implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SEND_SLOT = 'invoice-mailings';

    /**
     * A sandbox "too many emails per second" reply is delayed, not failed.
     * After this many delays the slip is marked failed.
     */
    public const MAX_PROVIDER_LIMIT_DELAYS = 30;

    /**
     * Slot releases do not count as attempts. Real send errors stop after
     * {@see $maxExceptions}.
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
     * Seconds until another slip may open an SMTP connection.
     * One per second is spaced to two seconds: the sandbox rejects a send
     * that starts as the previous one-second window is still open.
     */
    public static function secondsUntilNextSend(): int
    {
        if (self::sendsPerSecond() <= 1) {
            return 2;
        }

        return 1;
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

        if (! $this->claimSendSlot()) {
            $this->release(self::secondsUntilNextSend());

            return;
        }

        try {
            Mail::to($mailing->recipient)->send(new PaymentSlipMailable($invoice, $mailing->recipient));
        } catch (Throwable $e) {
            if ($this->isTemporaryProviderLimit($e)) {
                $this->postponeForProviderLimit($mailing, $invoice);

                return;
            }

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

        $this->syncParent($mailing);

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

    /**
     * One shared slot across queue workers. The check and the hit stay
     * inside a short cache gate so two workers cannot both send.
     */
    private function claimSendSlot(): bool
    {
        $gate = self::SEND_SLOT.':gate';

        if (! Cache::add($gate, 1, 10)) {
            return false;
        }

        try {
            if (RateLimiter::tooManyAttempts(self::SEND_SLOT, self::sendsPerSecond())) {
                return false;
            }

            RateLimiter::hit(self::SEND_SLOT, self::secondsUntilNextSend());

            return true;
        } finally {
            Cache::forget($gate);
        }
    }

    private function postponeForProviderLimit(InvoiceMailing $mailing, Invoice $invoice): void
    {
        $attempt = $this->nextProviderLimitAttempt($mailing->id);

        Log::warning('Payment slip email delayed by the mail provider', [
            'invoice_id' => $invoice->id,
            'invoice_mailing_id' => $mailing->id,
            'reference_code' => $invoice->reference_code,
            'recipient_email' => $mailing->recipient,
            'attempt' => $attempt,
        ]);

        if ($attempt >= self::MAX_PROVIDER_LIMIT_DELAYS) {
            $this->markFailed($mailing);
            $this->fail();

            return;
        }

        $this->release(self::secondsUntilNextSend());
    }

    private function nextProviderLimitAttempt(int $mailingId): int
    {
        $key = self::SEND_SLOT.':provider-limit:'.$mailingId;
        $attempt = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempt, 3600);

        return $attempt;
    }

    private function isTemporaryProviderLimit(Throwable $e): bool
    {
        $current = $e;

        while ($current !== null) {
            if (str_contains(strtolower($current->getMessage()), 'too many emails per second')) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private function markFailed(InvoiceMailing $mailing): void
    {
        if ($mailing->status === InvoiceMailing::STATUS_SENT || $mailing->status === InvoiceMailing::STATUS_CANCELLED) {
            return;
        }

        $mailing->update([
            'status' => InvoiceMailing::STATUS_FAILED,
            'error' => InvoiceMailing::FAILURE_MESSAGE,
            'sent_at' => null,
        ]);

        $this->syncParent($mailing);
    }

    private function syncParent(InvoiceMailing $mailing): void
    {
        if ($mailing->mailing_id === null) {
            return;
        }

        Mailing::query()->find($mailing->mailing_id)?->refreshCompletion();
    }
}

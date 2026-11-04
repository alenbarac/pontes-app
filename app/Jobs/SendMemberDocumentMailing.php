<?php

namespace App\Jobs;

use App\Mail\MemberDocumentMailable;
use App\Models\Mailing;
use App\Models\MailSetting;
use App\Models\MemberDocumentMailing;
use App\Support\MailRecipientAllowlist;
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

class SendMemberDocumentMailing implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 75;

    public function __construct(public int $memberDocumentMailingId) {}

    public function handle(): void
    {
        $mailing = MemberDocumentMailing::query()
            ->with(['memberDocument.member', 'memberDocument.documentTemplate'])
            ->find($this->memberDocumentMailingId);

        if ($mailing === null || $mailing->status !== MemberDocumentMailing::STATUS_QUEUED) {
            return;
        }

        if (! MailSetting::permits((string) $mailing->recipient)) {
            Log::warning('Member document email blocked by the recipient allowlist', [
                'member_document_mailing_id' => $mailing->id,
                'recipient_email' => $mailing->recipient,
            ]);
            $this->markBlocked($mailing);

            return;
        }

        $document = $mailing->memberDocument;

        if ($document === null || $document->member === null || $document->documentTemplate === null) {
            $this->markFailed($mailing);

            return;
        }

        if (! $this->claimSendSlot()) {
            $this->release(SendInvoiceMailing::secondsUntilNextSend());

            return;
        }

        try {
            Mail::to($mailing->recipient)->send(new MemberDocumentMailable($document));
        } catch (Throwable $e) {
            if ($this->isTemporaryProviderLimit($e)) {
                $this->postponeForProviderLimit($mailing);

                return;
            }

            Log::error('Failed to send member document email', [
                'member_document_mailing_id' => $mailing->id,
                'member_document_id' => $document->id,
                'recipient_email' => $mailing->recipient,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $mailing->update([
            'status' => MemberDocumentMailing::STATUS_SENT,
            'error' => null,
            'sent_at' => now(),
        ]);

        $this->syncParent($mailing);
    }

    public function failed(?Throwable $exception): void
    {
        $mailing = MemberDocumentMailing::query()->find($this->memberDocumentMailingId);

        if ($mailing === null) {
            return;
        }

        $this->markFailed($mailing);
    }

    private function claimSendSlot(): bool
    {
        $slot = SendInvoiceMailing::SEND_SLOT;
        $gate = $slot.':gate';

        if (! Cache::add($gate, 1, 10)) {
            return false;
        }

        try {
            if (RateLimiter::tooManyAttempts($slot, SendInvoiceMailing::sendsPerSecond())) {
                return false;
            }

            RateLimiter::hit($slot, SendInvoiceMailing::secondsUntilNextSend());

            return true;
        } finally {
            Cache::forget($gate);
        }
    }

    private function postponeForProviderLimit(MemberDocumentMailing $mailing): void
    {
        $key = SendInvoiceMailing::SEND_SLOT.':provider-limit:document:'.$mailing->id;
        $attempt = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempt, 3600);

        if ($attempt >= SendInvoiceMailing::MAX_PROVIDER_LIMIT_DELAYS) {
            $this->markFailed($mailing);
            $this->fail();

            return;
        }

        $this->release(SendInvoiceMailing::secondsUntilNextSend());
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

    private function markBlocked(MemberDocumentMailing $mailing): void
    {
        if ($mailing->status === MemberDocumentMailing::STATUS_SENT || $mailing->status === MemberDocumentMailing::STATUS_CANCELLED) {
            return;
        }

        $mailing->update([
            'status' => MemberDocumentMailing::STATUS_FAILED,
            'error' => MailRecipientAllowlist::restricts()
                ? MailRecipientAllowlist::BLOCKED_MESSAGE
                : MailRecipientAllowlist::EMPTY_LIST_MESSAGE,
            'sent_at' => null,
        ]);

        $this->syncParent($mailing);
    }

    private function markFailed(MemberDocumentMailing $mailing): void
    {
        if ($mailing->status === MemberDocumentMailing::STATUS_SENT || $mailing->status === MemberDocumentMailing::STATUS_CANCELLED) {
            return;
        }

        $mailing->update([
            'status' => MemberDocumentMailing::STATUS_FAILED,
            'error' => MemberDocumentMailing::FAILURE_MESSAGE,
            'sent_at' => null,
        ]);

        $this->syncParent($mailing);
    }

    private function syncParent(MemberDocumentMailing $mailing): void
    {
        if ($mailing->mailing_id === null) {
            return;
        }

        Mailing::query()->find($mailing->mailing_id)?->refreshCompletion();
    }
}

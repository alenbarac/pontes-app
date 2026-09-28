<?php

namespace App\Services;

use App\Jobs\SendInvoiceMailing;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Member;
use App\Support\MonthString;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentSlipEmailService
{
    public const MAX_INVOICES_PER_SEND = 100;

    public static function maxInvoicesMessage(): string
    {
        return 'Možete poslati najviše '.self::MAX_INVOICES_PER_SEND.' uplatnica odjednom.';
    }

    /**
     * @return array{message: string, errors: array{invoices: list<string>}}
     */
    public function capExceededPayload(): array
    {
        $message = self::maxInvoicesMessage();

        return [
            'message' => $message,
            'errors' => ['invoices' => [$message]],
        ];
    }

    /**
     * Payment slips are sent only to `members.invoice_email` (email za račune).
     *
     * @return array{email: string, source: 'invoice_email'}|null
     */
    public function resolveRecipient(Member $member): ?array
    {
        if (! empty($member->invoice_email)) {
            return ['email' => $member->invoice_email, 'source' => 'invoice_email'];
        }

        return null;
    }

    /**
     * Queue a single invoice slip. The worker sends the PDF and updates the log.
     *
     * A successful `slip` mailing is not queued again unless `$resend` is true.
     * `loadMissing` is defensive for direct callers; `sendForInvoiceIds`
     * already eager-loads the same relations so it is a no-op there.
     *
     * @return array{ok: bool, reason?: string, message?: string, recipient?: string, reference_code?: string, invoice_id?: int, sent_at?: string|null, invoice_mailing_id?: int}
     */
    public function sendForInvoice(Invoice $invoice, bool $resend = false): array
    {
        $invoice->loadMissing(['member', 'workshop', 'membershipPlan']);

        return $this->queueSlip($invoice, $resend);
    }

    /**
     * Queue one job per invoice and return immediately.
     *
     * Missing `invoice_email` rows and slips that already have a successful
     * mailing are skipped (unless `$resend` is true). The HTTP response does
     * not wait for SMTP. More than {@see self::MAX_INVOICES_PER_SEND} invoices
     * is refused without queueing any of them.
     *
     * @param  array<int>  $invoiceIds
     * @return array{queued: int, skipped_no_email: list<array<string, mixed>>, skipped_already_sent: list<array<string, mixed>>, failed: list<array<string, mixed>>, exceeds_cap: bool}
     */
    public function sendForInvoiceIds(array $invoiceIds, bool $resend = false): array
    {
        $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));

        if (count($invoiceIds) > self::MAX_INVOICES_PER_SEND) {
            return $this->emptyDispatchSummary(exceedsCap: true);
        }

        $queued = 0;
        $skippedNoEmail = [];
        $skippedAlreadySent = [];
        $failed = [];

        $invoices = Invoice::with(['member', 'workshop', 'membershipPlan'])
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        foreach ($invoiceIds as $id) {
            $invoice = $invoices->get($id);
            if (! $invoice) {
                $failed[] = [
                    'invoice_id' => $id,
                    'reference_code' => null,
                    'message' => 'Račun nije pronađen.',
                ];

                continue;
            }

            $result = $this->queueSlip($invoice, $resend);
            if ($result['ok']) {
                $queued++;
            } elseif (($result['reason'] ?? '') === 'no_email') {
                $skippedNoEmail[] = [
                    'invoice_id' => $invoice->id,
                    'reference_code' => $invoice->reference_code,
                    'member' => trim(($invoice->member->first_name ?? '').' '.($invoice->member->last_name ?? '')),
                ];
            } elseif (($result['reason'] ?? '') === 'already_sent') {
                $skippedAlreadySent[] = [
                    'invoice_id' => $invoice->id,
                    'reference_code' => $invoice->reference_code,
                    'member' => trim(($invoice->member->first_name ?? '').' '.($invoice->member->last_name ?? '')),
                    'sent_at' => $result['sent_at'] ?? null,
                ];
            } else {
                $failed[] = [
                    'invoice_id' => $invoice->id,
                    'reference_code' => $invoice->reference_code,
                    'member' => trim(($invoice->member?->first_name ?? '').' '.($invoice->member?->last_name ?? '')),
                    'message' => 'Greška pri slanju e-pošte.',
                ];
            }
        }

        return [
            'queued' => $queued,
            'skipped_no_email' => $skippedNoEmail,
            'skipped_already_sent' => $skippedAlreadySent,
            'failed' => $failed,
            'exceeds_cap' => false,
        ];
    }

    /**
     * Resolve invoices for given members and calendar month (due_date), then queue each slip.
     *
     * Successful slips are skipped unless `$resend` is true.
     *
     * @param  array<int>  $memberIds
     * @return array{queued: int, skipped_no_email: list<array<string, mixed>>, skipped_already_sent: list<array<string, mixed>>, failed: list<array<string, mixed>>, exceeds_cap: bool, invoice_count: int, invalid_month: bool}
     */
    public function sendForMembersInMonth(array $memberIds, string $monthYyyyMm, bool $resend = false): array
    {
        $parsed = MonthString::parse($monthYyyyMm);
        if ($parsed === null) {
            return [
                ...$this->emptyDispatchSummary(false),
                'invoice_count' => 0,
                'invalid_month' => true,
            ];
        }

        [$year, $month] = $parsed;

        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        if ($memberIds === []) {
            return [
                ...$this->emptyDispatchSummary(false),
                'invoice_count' => 0,
                'invalid_month' => false,
            ];
        }

        $invoiceIds = Invoice::query()
            ->whereIn('member_id', $memberIds)
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->pluck('id')
            ->all();

        $summary = $this->sendForInvoiceIds($invoiceIds, $resend);

        return $summary + [
            'invoice_count' => count($invoiceIds),
            'invalid_month' => false,
        ];
    }

    /**
     * Pre-flight stats for the bulk send modal: how many invoices exist for
     * the given members in the given month, how many members lack an
     * `invoice_email`, and how many slips were already emailed.
     *
     * `deliverable_count` is what a default send will attempt: the member has
     * an `invoice_email` and the invoice has no successful slip mailing yet.
     *
     * Does not send anything.
     *
     * @param  array<int>  $memberIds
     * @return array{
     *     invalid_month: bool,
     *     members_total: int,
     *     invoices_count: int,
     *     members_with_invoice: int,
     *     members_without_invoice: int,
     *     members_without_invoice_email: int,
     *     already_sent_count: int,
     *     deliverable_count: int
     * }
     */
    public function previewForMembersInMonth(array $memberIds, string $monthYyyyMm): array
    {
        $parsed = MonthString::parse($monthYyyyMm);
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        $membersTotal = count($memberIds);

        if ($parsed === null) {
            return $this->emptyPreview($membersTotal, invalidMonth: true);
        }

        if ($membersTotal === 0) {
            return $this->emptyPreview(0, invalidMonth: false);
        }

        [$year, $month] = $parsed;

        $invoices = Invoice::query()
            ->whereIn('member_id', $memberIds)
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->get(['id', 'member_id']);

        $invoicesCount = $invoices->count();
        $membersWithInvoice = $invoices->pluck('member_id')->unique()->count();

        $membersMissingEmailCount = Member::query()
            ->whereIn('id', $memberIds)
            ->where(function ($q) {
                $q->whereNull('invoice_email')->orWhere('invoice_email', '');
            })
            ->count();

        $invoices->load(['member:id,invoice_email']);

        $alreadySentIds = $this->successfulSlipInvoiceIds($invoices->pluck('id')->all());

        // Already emailed = any invoice in scope with a successful slip row.
        $alreadySentCount = $invoices
            ->filter(fn (Invoice $invoice) => isset($alreadySentIds[$invoice->id]))
            ->count();

        // Deliverable = has invoice_email and has not already been emailed.
        // A default send attempts only this set; resend is a separate confirmation.
        $deliverableCount = $invoices
            ->filter(fn (Invoice $invoice) => ! empty($invoice->member?->invoice_email)
                && ! isset($alreadySentIds[$invoice->id]))
            ->count();

        return [
            'invalid_month' => false,
            'members_total' => $membersTotal,
            'invoices_count' => $invoicesCount,
            'members_with_invoice' => $membersWithInvoice,
            'members_without_invoice' => max(0, $membersTotal - $membersWithInvoice),
            'members_without_invoice_email' => $membersMissingEmailCount,
            'already_sent_count' => $alreadySentCount,
            'deliverable_count' => $deliverableCount,
        ];
    }

    /**
     * @param  array{queued: int, skipped_no_email?: list<array<string, mixed>>, skipped_already_sent?: list<array<string, mixed>>, failed?: list<array<string, mixed>>}  $summary
     */
    public function humanSummary(array $summary): string
    {
        $parts = [];
        $parts[] = 'Stavljeno u red: '.$summary['queued'].'.';

        $skipCount = count($summary['skipped_no_email'] ?? []);
        if ($skipCount > 0) {
            $parts[] = 'Bez e-maila (preskočeno): '.$skipCount.'.';
        }

        $alreadySentCount = count($summary['skipped_already_sent'] ?? []);
        if ($alreadySentCount > 0) {
            $parts[] = 'Već poslano (preskočeno): '.$alreadySentCount.'.';
        }

        $failCount = count($summary['failed'] ?? []);
        if ($failCount > 0) {
            $parts[] = 'Greške: '.$failCount.'.';
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{
     *     invalid_month: bool,
     *     members_total: int,
     *     invoices_count: int,
     *     members_with_invoice: int,
     *     members_without_invoice: int,
     *     members_without_invoice_email: int,
     *     already_sent_count: int,
     *     deliverable_count: int
     * }
     */
    private function emptyPreview(int $membersTotal, bool $invalidMonth): array
    {
        return [
            'invalid_month' => $invalidMonth,
            'members_total' => $membersTotal,
            'invoices_count' => 0,
            'members_with_invoice' => 0,
            'members_without_invoice' => $invalidMonth ? $membersTotal : 0,
            'members_without_invoice_email' => 0,
            'already_sent_count' => 0,
            'deliverable_count' => 0,
        ];
    }

    private function latestSuccessfulSlip(Invoice $invoice): ?InvoiceMailing
    {
        return $invoice->mailings()
            ->where('type', InvoiceMailing::TYPE_SLIP)
            ->where('status', InvoiceMailing::STATUS_SENT)
            ->latest('sent_at')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<int>  $invoiceIds
     * @return array<int, true>
     */
    private function successfulSlipInvoiceIds(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return InvoiceMailing::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->where('type', InvoiceMailing::TYPE_SLIP)
            ->where('status', InvoiceMailing::STATUS_SENT)
            ->pluck('invoice_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @return array{ok: bool, reason?: string, message?: string, recipient?: string, reference_code?: string, invoice_id?: int, sent_at?: string|null, invoice_mailing_id?: int}
     */
    private function queueSlip(Invoice $invoice, bool $resend): array
    {
        if (! $resend) {
            $alreadySent = $this->latestSuccessfulSlip($invoice);
            if ($alreadySent !== null) {
                return [
                    'ok' => false,
                    'reason' => 'already_sent',
                    'message' => 'Uplatnica je već poslana.',
                    'invoice_id' => $invoice->id,
                    'reference_code' => $invoice->reference_code,
                    'recipient' => $alreadySent->recipient,
                    'sent_at' => $alreadySent->sent_at?->toIso8601String(),
                ];
            }
        }

        $recipient = $this->resolveRecipient($invoice->member);
        if ($recipient === null) {
            return [
                'ok' => false,
                'reason' => 'no_email',
                'message' => 'Član nema unesenu e-mail adresu za račune (invoice_email).',
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
            ];
        }

        $mailing = $this->recordMailing($invoice, $recipient['email'], InvoiceMailing::STATUS_QUEUED);

        try {
            SendInvoiceMailing::dispatch($mailing->id);
        } catch (Throwable $e) {
            $mailing->update([
                'status' => InvoiceMailing::STATUS_FAILED,
                'error' => InvoiceMailing::FAILURE_MESSAGE,
            ]);

            Log::error('Failed to queue payment slip email', [
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
                'recipient_email' => $recipient['email'],
                'invoice_mailing_id' => $mailing->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason' => 'mail_error',
                'message' => 'Greška pri slanju e-pošte.',
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
            ];
        }

        return [
            'ok' => true,
            'recipient' => $recipient['email'],
            'reference_code' => $invoice->reference_code,
            'invoice_id' => $invoice->id,
            'invoice_mailing_id' => $mailing->id,
        ];
    }

    /**
     * @return array{queued: int, skipped_no_email: list<array<string, mixed>>, skipped_already_sent: list<array<string, mixed>>, failed: list<array<string, mixed>>, exceeds_cap: bool}
     */
    private function emptyDispatchSummary(bool $exceedsCap): array
    {
        return [
            'queued' => 0,
            'skipped_no_email' => [],
            'skipped_already_sent' => [],
            'failed' => [],
            'exceeds_cap' => $exceedsCap,
        ];
    }

    private function recordMailing(Invoice $invoice, string $recipient, string $status, ?string $error = null): InvoiceMailing
    {
        return InvoiceMailing::query()->create([
            'invoice_id' => $invoice->id,
            'member_id' => $invoice->member_id,
            'type' => InvoiceMailing::TYPE_SLIP,
            'recipient' => $recipient,
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === InvoiceMailing::STATUS_SENT ? now() : null,
        ]);
    }
}

<?php

namespace App\Services;

use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;
use App\Models\Member;
use App\Support\MonthString;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentSlipEmailService
{
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
     * Send a single invoice slip.
     *
     * `loadMissing` is defensive for direct callers; `sendForInvoiceIds`
     * already eager-loads the same relations so it is a no-op there.
     *
     * @return array{ok: bool, reason?: string, message?: string, recipient?: string, reference_code?: string, invoice_id?: int}
     */
    public function sendForInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['member', 'workshop', 'membershipPlan']);

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

        try {
            $mailable = new PaymentSlipMailable($invoice, $recipient['email']);
            Mail::to($recipient['email'])->send($mailable);

            Log::info('Payment slip email sent', [
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
                'recipient_email' => $recipient['email'],
                'email_source' => $recipient['source'],
            ]);

            return [
                'ok' => true,
                'recipient' => $recipient['email'],
                'reference_code' => $invoice->reference_code,
                'invoice_id' => $invoice->id,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to send payment slip email', [
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
                'recipient_email' => $recipient['email'],
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason' => 'mail_error',
                'message' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
            ];
        }
    }

    /**
     * Send slips for the given invoice IDs (synchronously, in order).
     *
     * The detailed exception message is logged via `sendForInvoice` and is
     * intentionally not echoed back into the API response (it can leak SMTP
     * server / network details). The client receives a generic message.
     *
     * @param  array<int>  $invoiceIds
     * @return array{sent: int, skipped_no_email: list<array<string, mixed>>, failed: list<array<string, mixed>>}
     */
    public function sendForInvoiceIds(array $invoiceIds): array
    {
        $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));

        $sent = 0;
        $skippedNoEmail = [];
        $failed = [];

        $invoices = Invoice::with(['member', 'workshop', 'membershipPlan'])
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        // Sending is a long-running, side-effecting loop. Don't let an aborted
        // request kill us mid-batch — finish the batch and report the summary.
        @set_time_limit(0);
        ignore_user_abort(true);

        // Optional per-message throttle for SMTP providers with low per-second
        // caps (e.g. Mailtrap Testing tier returns 550 5.7.0 above ~1/sec).
        $throttleMicroseconds = (int) (((float) config('mail.bulk_throttle_seconds', 0)) * 1_000_000);
        $isFirst = true;

        foreach ($invoiceIds as $id) {
            if (! $isFirst && $throttleMicroseconds > 0) {
                usleep($throttleMicroseconds);
            }
            $isFirst = false;

            $invoice = $invoices->get($id);
            if (! $invoice) {
                $failed[] = [
                    'invoice_id' => $id,
                    'reference_code' => null,
                    'message' => 'Račun nije pronađen.',
                ];

                continue;
            }

            $result = $this->sendForInvoice($invoice);
            if ($result['ok']) {
                $sent++;
            } elseif (($result['reason'] ?? '') === 'no_email') {
                $skippedNoEmail[] = [
                    'invoice_id' => $invoice->id,
                    'reference_code' => $invoice->reference_code,
                    'member' => trim(($invoice->member->first_name ?? '').' '.($invoice->member->last_name ?? '')),
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
            'sent' => $sent,
            'skipped_no_email' => $skippedNoEmail,
            'failed' => $failed,
        ];
    }

    /**
     * Resolve invoices for given members and calendar month (due_date), then send each slip.
     *
     * @param  array<int>  $memberIds
     * @return array{sent: int, skipped_no_email: list<array<string, mixed>>, failed: list<array<string, mixed>>, invoice_count: int}
     */
    public function sendForMembersInMonth(array $memberIds, string $monthYyyyMm): array
    {
        $parsed = MonthString::parse($monthYyyyMm);
        if ($parsed === null) {
            return [
                'sent' => 0,
                'skipped_no_email' => [],
                'failed' => [],
                'invoice_count' => 0,
                'invalid_month' => true,
            ];
        }

        [$year, $month] = $parsed;

        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        if ($memberIds === []) {
            return [
                'sent' => 0,
                'skipped_no_email' => [],
                'failed' => [],
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

        $summary = $this->sendForInvoiceIds($invoiceIds);

        return $summary + [
            'invoice_count' => count($invoiceIds),
            'invalid_month' => false,
        ];
    }

    /**
     * Pre-flight stats for the bulk send modal: how many invoices exist for
     * the given members in the given month, and how many of those members
     * lack an `invoice_email` (would be skipped).
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
     *     deliverable_count: int
     * }
     */
    public function previewForMembersInMonth(array $memberIds, string $monthYyyyMm): array
    {
        $parsed = MonthString::parse($monthYyyyMm);
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        $membersTotal = count($memberIds);

        if ($parsed === null) {
            return [
                'invalid_month' => true,
                'members_total' => $membersTotal,
                'invoices_count' => 0,
                'members_with_invoice' => 0,
                'members_without_invoice' => $membersTotal,
                'members_without_invoice_email' => 0,
                'deliverable_count' => 0,
            ];
        }

        if ($membersTotal === 0) {
            return [
                'invalid_month' => false,
                'members_total' => 0,
                'invoices_count' => 0,
                'members_with_invoice' => 0,
                'members_without_invoice' => 0,
                'members_without_invoice_email' => 0,
                'deliverable_count' => 0,
            ];
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

        // Deliverable = invoices whose member actually has an invoice_email.
        $deliverableCount = $invoices
            ->load(['member:id,invoice_email'])
            ->filter(fn (Invoice $invoice) => ! empty($invoice->member?->invoice_email))
            ->count();

        return [
            'invalid_month' => false,
            'members_total' => $membersTotal,
            'invoices_count' => $invoicesCount,
            'members_with_invoice' => $membersWithInvoice,
            'members_without_invoice' => max(0, $membersTotal - $membersWithInvoice),
            'members_without_invoice_email' => $membersMissingEmailCount,
            'deliverable_count' => $deliverableCount,
        ];
    }

    /**
     * @param  array{sent: int, skipped_no_email: list<array<string, mixed>>, failed: list<array<string, mixed>>}  $summary
     */
    public function humanSummary(array $summary): string
    {
        $parts = [];
        $parts[] = 'Poslano: '.$summary['sent'].'.';

        $skipCount = count($summary['skipped_no_email']);
        if ($skipCount > 0) {
            $parts[] = 'Bez e-maila (preskočeno): '.$skipCount.'.';
        }

        $failCount = count($summary['failed']);
        if ($failCount > 0) {
            $parts[] = 'Greške: '.$failCount.'.';
        }

        return implode(' ', $parts);
    }
}

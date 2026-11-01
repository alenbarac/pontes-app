<?php

namespace App\Services;

use App\Jobs\SendInvoiceMailing;
use App\Models\Invoice;
use App\Models\InvoiceMailing;
use App\Models\Mailing;
use App\Models\Member;
use App\Support\MailRecipientAllowlist;
use App\Support\MonthString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
     * @return array{message: string, errors: array{recipient: list<string>}}
     */
    public function recipientNotAllowedPayload(): array
    {
        $message = MailRecipientAllowlist::REFUSAL_MESSAGE;

        return [
            'message' => $message,
            'errors' => ['recipient' => [$message]],
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
    public function sendForInvoice(Invoice $invoice, bool $resend = false, ?int $userId = null): array
    {
        $invoice->loadMissing(['member', 'workshop', 'membershipPlan']);

        if ($this->blockedByAllowlist([$invoice], $resend)) {
            return [
                'ok' => false,
                'reason' => 'recipient_not_allowed',
                'message' => MailRecipientAllowlist::REFUSAL_MESSAGE,
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
            ];
        }

        $batch = SlipMailingBatch::forInvoices($userId);
        $result = $this->queueSlip($invoice, $resend, $batch);
        if ($result['ok'] ?? false) {
            $result['mailing_id'] = $batch->mailing?->id;
        }

        return $result;
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
     * @return array{queued: int, skipped_no_email: list<array<string, mixed>>, skipped_already_sent: list<array<string, mixed>>, failed: list<array<string, mixed>>, exceeds_cap: bool, mailing_id: int|null, recipient_not_allowed?: bool, message?: string}
     */
    public function sendForInvoiceIds(array $invoiceIds, bool $resend = false, ?SlipMailingBatch $batch = null, ?int $userId = null): array
    {
        $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));

        if (count($invoiceIds) > self::MAX_INVOICES_PER_SEND) {
            return $this->emptyDispatchSummary(exceedsCap: true);
        }

        $batch ??= SlipMailingBatch::forInvoices($userId);
        $queued = 0;
        $skippedNoEmail = [];
        $skippedAlreadySent = [];
        $failed = [];

        $invoices = Invoice::with(['member', 'workshop', 'membershipPlan'])
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        // One address outside the list refuses the whole send. No mailing
        // row is written, so a parent does not get a failed attempt.
        if ($this->blockedByAllowlist($invoices, $resend)) {
            return [
                ...$this->emptyDispatchSummary(false),
                'recipient_not_allowed' => true,
                'message' => MailRecipientAllowlist::REFUSAL_MESSAGE,
            ];
        }

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

            $result = $this->queueSlip($invoice, $resend, $batch);
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

        $summary = [
            'queued' => $queued,
            'skipped_no_email' => $skippedNoEmail,
            'skipped_already_sent' => $skippedAlreadySent,
            'failed' => $failed,
            'exceeds_cap' => false,
            'mailing_id' => $batch->mailing?->id,
        ];
        $summary['message'] = $queued > 0
            ? $this->startedMessage($queued, $batch->audience)
            : $this->humanSummary($summary);

        return $summary;
    }

    public function reminderGraceDays(): int
    {
        return max(0, (int) config('pontes.reminder_grace_days', 10));
    }

    /**
     * Unpaid invoices that already received a slip and are past the grace period.
     * This query feeds the suggestion. Sending a reminder does not use it.
     */
    public function suggestedReminderQuery(): Builder
    {
        return Invoice::query()
            ->where('payment_status', '!=', 'Plaćeno')
            ->whereDate('due_date', '<=', now()->startOfDay()->subDays($this->reminderGraceDays()))
            ->whereHas('mailings', function ($query) {
                $query->where('type', InvoiceMailing::TYPE_SLIP)
                    ->where('status', InvoiceMailing::STATUS_SENT);
            });
    }

    /**
     * Overdue invoices that still need a reminder. A queued or sent reminder drops the count.
     */
    public function suggestedReminderCount(): int
    {
        return $this->suggestedReminderQuery()
            ->whereDoesntHave('mailings', function ($query) {
                $query->where('type', InvoiceMailing::TYPE_REMINDER)
                    ->whereIn('status', [InvoiceMailing::STATUS_SENT, InvoiceMailing::STATUS_QUEUED]);
            })
            ->count();
    }

    /**
     * Rows for the review page, including invoices that were already reminded.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestedReminderRows(): array
    {
        return $this->suggestedReminderQuery()
            ->with([
                'member.workshopGroups.group',
                'workshop',
                'latestSuccessfulSlipMailing',
                'latestSlipMailing',
                'latestReminderMailing',
            ])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Invoice $invoice) => $this->suggestedReminderRow($invoice))
            ->all();
    }

    /**
     * Queue reminder emails. The grace period is not checked: staff can send before day 10.
     * A successful or queued reminder is skipped unless `$resend` is true.
     *
     * @param  array<int>  $invoiceIds
     * @return array{queued: int, skipped_no_email: list<array<string, mixed>>, skipped_already_sent: list<array<string, mixed>>, skipped_not_eligible: list<array<string, mixed>>, failed: list<array<string, mixed>>, exceeds_cap: bool, mailing_id: int|null, recipient_not_allowed?: bool, message?: string}
     */
    public function sendRemindersForInvoiceIds(array $invoiceIds, bool $resend = false, ?int $userId = null): array
    {
        $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));

        if (count($invoiceIds) > self::MAX_INVOICES_PER_SEND) {
            return $this->emptyReminderSummary(exceedsCap: true);
        }

        $invoices = Invoice::with(['member', 'workshop', 'membershipPlan'])
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($this->reminderBlockedByAllowlist($invoices, $resend)) {
            return [
                ...$this->emptyReminderSummary(false),
                'recipient_not_allowed' => true,
                'message' => MailRecipientAllowlist::REFUSAL_MESSAGE,
            ];
        }

        $batch = SlipMailingBatch::forReminders($userId);
        $queued = 0;
        $skippedNoEmail = [];
        $skippedAlreadySent = [];
        $skippedNotEligible = [];
        $failed = [];

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

            $result = $this->queueReminder($invoice, $resend, $batch);
            if ($result['ok']) {
                $queued++;
            } elseif (($result['reason'] ?? '') === 'no_email') {
                $skippedNoEmail[] = $this->reminderSkipRow($invoice);
            } elseif (($result['reason'] ?? '') === 'already_sent') {
                $skippedAlreadySent[] = [
                    ...$this->reminderSkipRow($invoice),
                    'sent_at' => $result['sent_at'] ?? null,
                ];
            } elseif (in_array($result['reason'] ?? '', ['paid', 'no_slip'], true)) {
                $skippedNotEligible[] = [
                    ...$this->reminderSkipRow($invoice),
                    'message' => $result['message'] ?? null,
                ];
            } else {
                $failed[] = [
                    ...$this->reminderSkipRow($invoice),
                    'message' => $result['message'] ?? 'Greška pri slanju e-pošte.',
                ];
            }
        }

        $summary = [
            'queued' => $queued,
            'skipped_no_email' => $skippedNoEmail,
            'skipped_already_sent' => $skippedAlreadySent,
            'skipped_not_eligible' => $skippedNotEligible,
            'failed' => $failed,
            'exceeds_cap' => false,
            'mailing_id' => $batch->mailing?->id,
        ];
        $summary['message'] = $queued > 0
            ? $this->reminderStartedMessage($queued)
            : $this->reminderSummary($summary);

        return $summary;
    }

    /**
     * @return array{ok: bool, reason?: string, message?: string, recipient?: string, reference_code?: string, invoice_id?: int, sent_at?: string|null, invoice_mailing_id?: int, mailing_id?: int|null}
     */
    public function sendReminderForInvoice(Invoice $invoice, bool $resend = false, ?int $userId = null): array
    {
        $invoice->loadMissing(['member', 'workshop', 'membershipPlan']);

        if ($this->reminderBlockedByAllowlist([$invoice], $resend)) {
            return [
                'ok' => false,
                'reason' => 'recipient_not_allowed',
                'message' => MailRecipientAllowlist::REFUSAL_MESSAGE,
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
            ];
        }

        $batch = SlipMailingBatch::forReminders($userId);
        $result = $this->queueReminder($invoice, $resend, $batch);
        if ($result['ok'] ?? false) {
            $result['mailing_id'] = $batch->mailing?->id;
        }

        return $result;
    }

    public function reminderStartedMessage(int $count): string
    {
        $noun = $this->croatianCountForm($count, 'podsjetnik', 'podsjetnika', 'podsjetnika');
        $verb = $this->croatianCountForm($count, 'šalje se', 'šalju se', 'šalje se');

        return "Slanje je pokrenuto. {$count} {$noun} {$verb}.";
    }

    /**
     * Resolve invoices for given members and calendar month (due_date), then queue each slip.
     *
     * Successful slips are skipped unless `$resend` is true.
     *
     * @param  array<int>  $memberIds
     * @return array{queued: int, skipped_no_email: list<array<string, mixed>>, skipped_already_sent: list<array<string, mixed>>, failed: list<array<string, mixed>>, exceeds_cap: bool, mailing_id: int|null, invoice_count: int, invalid_month: bool, message?: string}
     */
    public function sendForMembersInMonth(array $memberIds, string $monthYyyyMm, bool $resend = false, ?SlipMailingBatch $batch = null, ?int $userId = null): array
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

        $summary = $this->sendForInvoiceIds(
            $invoiceIds,
            $resend,
            $batch ?? SlipMailingBatch::forMembers($monthYyyyMm, $userId),
        );

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
        $queued = (int) ($summary['queued'] ?? 0);

        if ($queued > 0) {
            $parts[] = 'Pokrenuto je slanje '.$queued.' '.$this->uplatniceAfterSlanje($queued).'.';
        }

        $skipCount = count($summary['skipped_no_email'] ?? []);
        if ($skipCount > 0) {
            $parts[] = $skipCount.' '.$this->croatianCountForm(
                $skipCount,
                'uplatnica nema e-mail i zato je preskočena',
                'uplatnice nemaju e-mail i zato su preskočene',
                'uplatnica nema e-mail i zato su preskočene',
            ).'.';
        }

        $alreadySentCount = count($summary['skipped_already_sent'] ?? []);
        if ($alreadySentCount > 0) {
            $parts[] = $alreadySentCount.' '.$this->croatianCountForm(
                $alreadySentCount,
                'uplatnica je već poslana i zato je preskočena',
                'uplatnice su već poslane i zato su preskočene',
                'uplatnica je već poslano i zato su preskočene',
            ).'.';
        }

        $failCount = count($summary['failed'] ?? []);
        if ($failCount > 0) {
            $parts[] = $failCount.' '.$this->croatianCountForm(
                $failCount,
                'uplatnica nije mogla krenuti na slanje',
                'uplatnice nisu mogle krenuti na slanje',
                'uplatnica nije moglo krenuti na slanje',
            ).'.';
        }

        if ($parts === []) {
            return 'Slanje nije pokrenuto.';
        }

        return implode(' ', $parts);
    }

    /**
     * Toast after a send is accepted. The queue, not this sentence, is the status.
     */
    public function startedMessage(int $count, string $audience = ''): string
    {
        $noun = $this->croatianCountForm($count, 'uplatnica', 'uplatnice', 'uplatnica');
        $verb = $this->croatianCountForm($count, 'šalje se', 'šalju se', 'šalje se');
        $where = $audience !== '' ? ' '.$audience : '';

        return "Slanje je pokrenuto. {$count} {$noun} {$verb}{$where}.";
    }

    /**
     * Re-queue only the latest failed recipient on this batch. Sent rows stay.
     * One stored address outside the allowlist refuses the whole retry.
     *
     * @return array{queued: int, recipient_not_allowed: bool, message?: string}
     */
    public function retryFailed(Mailing $mailing): array
    {
        $latest = InvoiceMailing::query()
            ->selectRaw('MAX(id) as id')
            ->where('mailing_id', $mailing->id)
            ->groupBy('invoice_id');

        $failed = InvoiceMailing::query()
            ->select('invoice_mailings.*')
            ->joinSub($latest, 'latest', 'latest.id', '=', 'invoice_mailings.id')
            ->where('invoice_mailings.status', InvoiceMailing::STATUS_FAILED)
            ->get();

        if ($this->recipientsBlocked($failed->pluck('recipient'))) {
            return [
                'queued' => 0,
                'recipient_not_allowed' => true,
                'message' => MailRecipientAllowlist::REFUSAL_MESSAGE,
            ];
        }

        $queued = 0;

        foreach ($failed as $row) {
            $created = InvoiceMailing::query()->create([
                'mailing_id' => $mailing->id,
                'invoice_id' => $row->invoice_id,
                'member_id' => $row->member_id,
                'type' => $row->type,
                'recipient' => $row->recipient,
                'status' => InvoiceMailing::STATUS_QUEUED,
                'error' => null,
                'sent_at' => null,
            ]);

            try {
                SendInvoiceMailing::dispatch($created->id);
                $queued++;
            } catch (Throwable $e) {
                $created->update([
                    'status' => InvoiceMailing::STATUS_FAILED,
                    'error' => $row->type === InvoiceMailing::TYPE_REMINDER
                        ? InvoiceMailing::REMINDER_FAILURE_MESSAGE
                        : InvoiceMailing::FAILURE_MESSAGE,
                ]);

                Log::error('Failed to queue payment slip email', [
                    'invoice_id' => $row->invoice_id,
                    'invoice_mailing_id' => $created->id,
                    'mailing_id' => $mailing->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($queued > 0) {
            $mailing->forceFill(['completed_at' => null])->save();
        } else {
            $mailing->refreshCompletion();
        }

        return [
            'queued' => $queued,
            'recipient_not_allowed' => false,
        ];
    }

    /**
     * Noun after "slanje N": 1 uplatnice, 3 uplatnice, 13 uplatnica.
     */
    private function uplatniceAfterSlanje(int $count): string
    {
        return $this->croatianCountForm($count, 'uplatnice', 'uplatnice', 'uplatnica');
    }

    private function croatianCountForm(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $few;
        }

        return $many;
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

    /**
     * @return array<string, mixed>
     */
    private function suggestedReminderRow(Invoice $invoice): array
    {
        $email = trim((string) ($invoice->member?->invoice_email ?? ''));
        $reminder = $invoice->staffReminderMailing();
        $status = $reminder['status'] ?? null;
        $blocked = match (true) {
            $email === '' => 'Nema e-mail adrese za račune.',
            $status === InvoiceMailing::STATUS_QUEUED => 'Podsjetnik je u redu za slanje.',
            default => null,
        };

        $due = $invoice->due_date ? Carbon::parse($invoice->due_date) : null;

        return [
            'id' => $invoice->id,
            'member_id' => $invoice->member_id,
            'member_name' => trim(($invoice->member->first_name ?? '').' '.($invoice->member->last_name ?? '')),
            'group_name' => $this->reminderGroupLabel($invoice),
            'workshop_name' => $invoice->workshop?->name,
            'month_label' => $due?->format('m/Y'),
            'amount_label' => number_format((float) $invoice->amount_due, 2, ',', '.').' '.trim((string) config('pontes.currency', 'EUR')),
            'due_on' => $due?->format('d.m.Y.'),
            'invoice_email' => $email !== '' ? $email : null,
            'payment_status' => $invoice->payment_status,
            'slip_mailing' => $invoice->staffSlipMailing(),
            'reminder_mailing' => $reminder,
            'selectable' => $blocked === null,
            'selected' => $blocked === null && $status !== InvoiceMailing::STATUS_SENT,
            'send_block' => $blocked,
        ];
    }

    private function reminderGroupLabel(Invoice $invoice): ?string
    {
        $rows = $invoice->member?->workshopGroups;
        if ($rows === null || $rows->isEmpty()) {
            return null;
        }

        $forWorkshop = $rows->filter(
            fn ($row) => $invoice->workshop_id === null || (int) $row->workshop_id === (int) $invoice->workshop_id
        );
        $names = ($forWorkshop->isNotEmpty() ? $forWorkshop : $rows)
            ->map(fn ($row) => $row->group?->name)
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? null : $names->join(', ');
    }

    /**
     * @return array{invoice_id: int, reference_code: string|null, member: string}
     */
    private function reminderSkipRow(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'reference_code' => $invoice->reference_code,
            'member' => trim(($invoice->member?->first_name ?? '').' '.($invoice->member?->last_name ?? '')),
        ];
    }

    /**
     * @param  array{queued: int, skipped_no_email?: list<array<string, mixed>>, skipped_already_sent?: list<array<string, mixed>>, skipped_not_eligible?: list<array<string, mixed>>, failed?: list<array<string, mixed>>}  $summary
     */
    private function reminderSummary(array $summary): string
    {
        $parts = [];
        $queued = (int) ($summary['queued'] ?? 0);

        if ($queued > 0) {
            $parts[] = 'Pokrenuto je slanje '.$queued.' '.$this->croatianCountForm($queued, 'podsjetnika', 'podsjetnika', 'podsjetnika').'.';
        }

        $skipCount = count($summary['skipped_no_email'] ?? []);
        if ($skipCount > 0) {
            $parts[] = $skipCount.' '.$this->croatianCountForm(
                $skipCount,
                'podsjetnik nema e-mail i zato je preskočen',
                'podsjetnika nemaju e-mail i zato su preskočena',
                'podsjetnika nema e-mail i zato su preskočena',
            ).'.';
        }

        $alreadySentCount = count($summary['skipped_already_sent'] ?? []);
        if ($alreadySentCount > 0) {
            $parts[] = $alreadySentCount.' '.$this->croatianCountForm(
                $alreadySentCount,
                'podsjetnik je već poslan ili je u redu i zato je preskočen',
                'podsjetnika su već poslana ili su u redu i zato su preskočena',
                'podsjetnika je već poslano ili su u redu i zato su preskočena',
            ).'.';
        }

        $ineligibleCount = count($summary['skipped_not_eligible'] ?? []);
        if ($ineligibleCount > 0) {
            $parts[] = $ineligibleCount.' '.$this->croatianCountForm(
                $ineligibleCount,
                'račun je plaćen ili nema poslanu uplatnicu i zato je preskočen',
                'računa su plaćena ili nemaju poslanu uplatnicu i zato su preskočena',
                'računa je plaćeno ili nema poslanu uplatnicu i zato su preskočena',
            ).'.';
        }

        $failCount = count($summary['failed'] ?? []);
        if ($failCount > 0) {
            $parts[] = $failCount.' '.$this->croatianCountForm(
                $failCount,
                'podsjetnik nije mogao krenuti na slanje',
                'podsjetnika nisu mogla krenuti na slanje',
                'podsjetnika nije moglo krenuti na slanje',
            ).'.';
        }

        if ($parts === []) {
            return 'Slanje nije pokrenuto.';
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{queued: int, skipped_no_email: list<array<string, mixed>>, skipped_already_sent: list<array<string, mixed>>, skipped_not_eligible: list<array<string, mixed>>, failed: list<array<string, mixed>>, exceeds_cap: bool, mailing_id: null}
     */
    private function emptyReminderSummary(bool $exceedsCap): array
    {
        return [
            'queued' => 0,
            'skipped_no_email' => [],
            'skipped_already_sent' => [],
            'skipped_not_eligible' => [],
            'failed' => [],
            'exceeds_cap' => $exceedsCap,
            'mailing_id' => null,
        ];
    }

    /**
     * @return array{ok: bool, reason?: string, message?: string, recipient?: string, reference_code?: string, invoice_id?: int, sent_at?: string|null, invoice_mailing_id?: int, mailing_id?: int|null}
     */
    private function queueReminder(Invoice $invoice, bool $resend, SlipMailingBatch $batch): array
    {
        $reason = $this->reminderSkipReason($invoice, $resend);
        if ($reason !== null) {
            $latest = $reason === 'already_sent' ? $this->latestReminder($invoice) : null;

            return [
                'ok' => false,
                'reason' => $reason,
                'message' => match ($reason) {
                    'paid' => 'Račun je već podmiren.',
                    'no_slip' => 'Uplatnica još nije poslana.',
                    'no_email' => 'Član nema unesenu e-mail adresu za račune (invoice_email).',
                    default => 'Podsjetnik je već poslan.',
                },
                'invoice_id' => $invoice->id,
                'reference_code' => $invoice->reference_code,
                'recipient' => $latest?->recipient,
                'sent_at' => $latest?->sent_at?->toIso8601String(),
            ];
        }

        $recipient = $this->resolveRecipient($invoice->member);
        $mailing = $this->recordMailing(
            $invoice,
            $recipient['email'],
            InvoiceMailing::STATUS_QUEUED,
            null,
            $batch,
            InvoiceMailing::TYPE_REMINDER,
        );

        try {
            SendInvoiceMailing::dispatch($mailing->id);
        } catch (Throwable $e) {
            $mailing->update([
                'status' => InvoiceMailing::STATUS_FAILED,
                'error' => InvoiceMailing::REMINDER_FAILURE_MESSAGE,
            ]);
            $batch->mailing?->refreshCompletion();

            Log::error('Failed to queue payment reminder email', [
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
            'mailing_id' => $batch->mailing?->id,
        ];
    }

    private function reminderSkipReason(Invoice $invoice, bool $resend): ?string
    {
        if ($invoice->payment_status === 'Plaćeno') {
            return 'paid';
        }

        if ($this->latestSuccessfulSlip($invoice) === null) {
            return 'no_slip';
        }

        if (! $resend) {
            $latest = $this->latestReminder($invoice);
            if ($latest !== null && in_array($latest->status, [InvoiceMailing::STATUS_SENT, InvoiceMailing::STATUS_QUEUED], true)) {
                return 'already_sent';
            }
        }

        if ($this->resolveRecipient($invoice->member) === null) {
            return 'no_email';
        }

        return null;
    }

    private function latestReminder(Invoice $invoice): ?InvoiceMailing
    {
        return $invoice->mailings()
            ->where('type', InvoiceMailing::TYPE_REMINDER)
            ->latest('id')
            ->first();
    }

    /**
     * @param  iterable<Invoice>  $invoices
     */
    private function reminderBlockedByAllowlist(iterable $invoices, bool $resend): bool
    {
        if (! MailRecipientAllowlist::restricts()) {
            return false;
        }

        return $this->recipientsBlocked($this->reminderProspectiveRecipients($invoices, $resend));
    }

    /**
     * @param  iterable<Invoice>  $invoices
     * @return list<string>
     */
    private function reminderProspectiveRecipients(iterable $invoices, bool $resend): array
    {
        $emails = [];

        foreach ($invoices as $invoice) {
            if ($this->reminderSkipReason($invoice, $resend) !== null) {
                continue;
            }

            $email = $invoice->member?->invoice_email;
            if (! is_string($email) || trim($email) === '') {
                continue;
            }

            $emails[] = $email;
        }

        return $emails;
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
     * True when the allowlist is set and any address this send would queue is outside it.
     * Already-sent slips are not recipients unless `$resend` is true. Missing
     * invoice emails are skipped by the normal send, not by this check.
     *
     * @param  iterable<Invoice>  $invoices
     */
    private function blockedByAllowlist(iterable $invoices, bool $resend): bool
    {
        if (! MailRecipientAllowlist::restricts()) {
            return false;
        }

        return $this->recipientsBlocked($this->prospectiveRecipients($invoices, $resend));
    }

    /**
     * @param  iterable<Invoice>  $invoices
     * @return list<string>
     */
    private function prospectiveRecipients(iterable $invoices, bool $resend): array
    {
        $invoices = collect($invoices);
        $alreadySentIds = $resend
            ? []
            : $this->successfulSlipInvoiceIds($invoices->pluck('id')->filter()->map(fn ($id) => (int) $id)->all());

        $emails = [];

        foreach ($invoices as $invoice) {
            if (isset($alreadySentIds[(int) $invoice->id])) {
                continue;
            }

            $email = $invoice->member?->invoice_email;

            if (! is_string($email) || trim($email) === '') {
                continue;
            }

            $emails[] = $email;
        }

        return $emails;
    }

    /**
     * @param  iterable<mixed>  $emails
     */
    private function recipientsBlocked(iterable $emails): bool
    {
        if (! MailRecipientAllowlist::restricts()) {
            return false;
        }

        foreach ($emails as $email) {
            if (! MailRecipientAllowlist::allows((string) $email)) {
                return true;
            }
        }

        return false;
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
     * @return array{ok: bool, reason?: string, message?: string, recipient?: string, reference_code?: string, invoice_id?: int, sent_at?: string|null, invoice_mailing_id?: int, mailing_id?: int|null}
     */
    private function queueSlip(Invoice $invoice, bool $resend, SlipMailingBatch $batch): array
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

        $mailing = $this->recordMailing($invoice, $recipient['email'], InvoiceMailing::STATUS_QUEUED, null, $batch);

        try {
            SendInvoiceMailing::dispatch($mailing->id);
        } catch (Throwable $e) {
            $mailing->update([
                'status' => InvoiceMailing::STATUS_FAILED,
                'error' => InvoiceMailing::FAILURE_MESSAGE,
            ]);
            $batch->mailing?->refreshCompletion();

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
            'mailing_id' => $batch->mailing?->id,
        ];
    }

    /**
     * @return array{queued: int, skipped_no_email: list<array<string, mixed>>, skipped_already_sent: list<array<string, mixed>>, failed: list<array<string, mixed>>, exceeds_cap: bool, mailing_id: null}
     */
    private function emptyDispatchSummary(bool $exceedsCap): array
    {
        return [
            'queued' => 0,
            'skipped_no_email' => [],
            'skipped_already_sent' => [],
            'failed' => [],
            'exceeds_cap' => $exceedsCap,
            'mailing_id' => null,
        ];
    }

    private function recordMailing(Invoice $invoice, string $recipient, string $status, ?string $error, SlipMailingBatch $batch, string $type = InvoiceMailing::TYPE_SLIP): InvoiceMailing
    {
        $parent = $batch->ensure($invoice);

        return InvoiceMailing::query()->create([
            'mailing_id' => $parent->id,
            'invoice_id' => $invoice->id,
            'member_id' => $invoice->member_id,
            'type' => $type,
            'recipient' => $recipient,
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === InvoiceMailing::STATUS_SENT ? now() : null,
        ]);
    }
}

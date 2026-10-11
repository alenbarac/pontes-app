<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberWorkshop;
use App\Support\PayerNameMatcher;
use App\Support\PaymentMonth;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankStatementReview
{
    public function __construct(
        private PayerNameMatcher $names = new PayerNameMatcher,
        private InvoiceGenerationService $invoices = new InvoiceGenerationService,
    ) {}

    public function store(array $results, string $filename, ?int $userId): BankStatementImport
    {
        return DB::transaction(function () use ($results, $filename, $userId) {
            $import = BankStatementImport::create([
                'filename' => mb_substr($filename, 0, 255),
                'user_id' => $userId,
                'imported_at' => now(),
                'total_rows' => $results['total_rows'] ?? 0,
                'incoming_rows' => $results['incoming_rows'] ?? 0,
                'paid_count' => $results['paid_count'] ?? 0,
                'mismatch_count' => $results['mismatch_count'] ?? 0,
                'failed_count' => $results['failed_count'] ?? 0,
                'unresolved_count' => $results['unresolved_count'] ?? 0,
                'ambiguous_count' => $results['ambiguous_count'] ?? 0,
                'skipped_count' => $results['skipped_count'] ?? 0,
            ]);

            foreach ($results['lines'] ?? [] as $line) {
                $import->lines()->create([
                    'row_number' => $line['row_number'],
                    'kind' => $line['kind'],
                    'payer' => $line['payer'] !== '' ? $line['payer'] : null,
                    'amount_paid' => $line['amount_paid'],
                    'amount_due' => $line['amount_due'],
                    'reference' => $line['reference'],
                    'value_date' => $line['value_date'],
                    'description' => $line['description'],
                    'message' => $line['message'],
                    'member_id' => $line['member_id'],
                    'invoice_id' => $line['invoice_id'],
                    'candidate_invoice_ids' => $line['candidate_invoice_ids'] !== []
                        ? array_values($line['candidate_invoice_ids'])
                        : null,
                    'resolution' => $line['kind'] === BankStatementImportLine::KIND_PAID
                        ? BankStatementImportLine::RESOLUTION_RESOLVED
                        : BankStatementImportLine::RESOLUTION_OPEN,
                ]);
            }

            return $import;
        });
    }

    public function present(BankStatementImport $import): array
    {
        $lines = $import->lines()
            ->with([
                'member:id,first_name,last_name',
                'invoice.member:id,first_name,last_name',
                'invoice.membershipPlan:id,workshop_id,plan,fee,total_fee,discount_type',
            ])
            ->orderBy('row_number')
            ->get();

        $openMismatches = $lines
            ->where('kind', BankStatementImportLine::KIND_MISMATCH)
            ->where('resolution', BankStatementImportLine::RESOLUTION_OPEN)
            ->values();

        $workshopIds = $openMismatches
            ->map(fn (BankStatementImportLine $line) => $line->invoice?->workshop_id)
            ->filter()
            ->unique()
            ->values();
        $memberIds = $openMismatches
            ->map(fn (BankStatementImportLine $line) => $line->member_id ?? $line->invoice?->member_id)
            ->filter()
            ->unique()
            ->values();

        $plansByWorkshop = $workshopIds->isEmpty()
            ? collect()
            : MembershipPlan::query()
                ->whereIn('workshop_id', $workshopIds)
                ->get(['id', 'workshop_id', 'plan', 'fee', 'total_fee', 'discount_type'])
                ->groupBy('workshop_id');

        $enrollments = ($memberIds->isEmpty() || $workshopIds->isEmpty())
            ? collect()
            : MemberWorkshop::query()
                ->with('membershipPlan:id,workshop_id,plan,fee,total_fee,discount_type')
                ->whereIn('member_id', $memberIds)
                ->whereIn('workshop_id', $workshopIds)
                ->get()
                ->keyBy(fn (MemberWorkshop $enrollment) => $enrollment->member_id.'-'.$enrollment->workshop_id);

        $openUnresolved = $lines
            ->where('kind', BankStatementImportLine::KIND_UNRESOLVED)
            ->where('resolution', BankStatementImportLine::RESOLUTION_OPEN)
            ->values();
        $openAmbiguous = $lines
            ->where('kind', BankStatementImportLine::KIND_AMBIGUOUS)
            ->where('resolution', BankStatementImportLine::RESOLUTION_OPEN)
            ->values();

        $memberDirectory = ($openUnresolved->isNotEmpty() || $openAmbiguous->isNotEmpty())
            ? Member::query()->get(['id', 'first_name', 'last_name'])
            : collect();

        return [
            'statementImport' => [
                'id' => $import->id,
                'filename' => $import->filename,
                'imported_at' => $import->imported_at?->format('d.m.Y. H:i'),
                'total_rows' => $import->total_rows,
                'incoming_rows' => $import->incoming_rows,
                'paid_count' => $import->paid_count,
                'mismatch_count' => $import->mismatch_count,
                'failed_count' => $import->failed_count,
                'unresolved_count' => $import->unresolved_count,
                'ambiguous_count' => $import->ambiguous_count,
                'skipped_count' => $import->skipped_count,
                'open_mismatch_count' => $openMismatches->count(),
                'open_unresolved_count' => $openUnresolved->count(),
                'open_ambiguous_count' => $openAmbiguous->count(),
            ],
            'mismatches' => $openMismatches
                ->map(fn (BankStatementImportLine $line) => $this->presentMismatch($line, $plansByWorkshop, $enrollments))
                ->values()
                ->all(),
            'unresolved' => $openUnresolved
                ->map(fn (BankStatementImportLine $line) => $this->presentProblem(
                    $line,
                    $this->suggestionsFor($line),
                    $this->membersNamedInPayment($line, $memberDirectory)
                ))
                ->values()
                ->all(),
            'ambiguous' => $openAmbiguous
                ->map(fn (BankStatementImportLine $line) => $this->presentProblem(
                    $line,
                    $this->candidateSuggestions($line),
                    $this->membersNamedInPayment($line, $memberDirectory)
                ))
                ->values()
                ->all(),
            'resolved' => $lines
                ->filter(fn (BankStatementImportLine $line) => $line->kind !== BankStatementImportLine::KIND_PAID
                    && $line->resolution !== BankStatementImportLine::RESOLUTION_OPEN)
                ->map(fn (BankStatementImportLine $line) => $this->presentResolved($line))
                ->values()
                ->all(),
        ];
    }

    public function align(BankStatementImportLine $line): void
    {
        $this->assertOpen($line, [BankStatementImportLine::KIND_MISMATCH]);
        $invoice = $line->invoice;
        if (! $invoice) {
            throw ValidationException::withMessages([
                'line' => 'Račun za ovu stavku više ne postoji.',
            ]);
        }

        DB::transaction(function () use ($line, $invoice) {
            $paid = round((float) $line->amount_paid, 2);
            $invoice->amount_due = $paid;
            $invoice->amount_paid = $paid;
            $invoice->payment_status = 'Plaćeno';
            $invoice->save();

            $line->resolution = BankStatementImportLine::RESOLUTION_RESOLVED;
            $line->save();
        });
    }

    public function applyPlan(BankStatementImportLine $line, MembershipPlan $plan): void
    {
        $this->assertOpen($line, [BankStatementImportLine::KIND_MISMATCH]);
        $invoice = $line->invoice;
        if (! $invoice || ! $invoice->member_id || ! $invoice->workshop_id) {
            throw ValidationException::withMessages([
                'line' => 'Račun za ovu stavku više ne postoji.',
            ]);
        }

        if ((int) $plan->workshop_id !== (int) $invoice->workshop_id) {
            throw ValidationException::withMessages([
                'membership_plan_id' => 'Plan ne pripada radionici ovog računa.',
            ]);
        }

        $charged = $this->chargedAmount($plan);
        if (! $this->amountsMatch($charged, (float) $line->amount_paid)) {
            throw ValidationException::withMessages([
                'membership_plan_id' => 'Iznos plana ne odgovara uplati.',
            ]);
        }

        $enrollment = MemberWorkshop::query()
            ->where('member_id', $invoice->member_id)
            ->where('workshop_id', $invoice->workshop_id)
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'membership_plan_id' => 'Član nije upisan u radionicu ovog računa.',
            ]);
        }

        DB::transaction(function () use ($line, $invoice, $plan, $enrollment, $charged) {
            $enrollment->membership_plan_id = $plan->id;
            $enrollment->save();

            $snapshot = $plan->invoiceDiscountSnapshot($charged);
            $invoice->membership_plan_id = $plan->id;
            $invoice->amount_due = $charged;
            $invoice->amount_paid = round((float) $line->amount_paid, 2);
            $invoice->discount_percent = $snapshot['discount_percent'];
            $invoice->original_amount = $snapshot['original_amount'];
            $invoice->payment_status = 'Plaćeno';
            $invoice->save();

            $line->resolution = BankStatementImportLine::RESOLUTION_RESOLVED;
            $line->save();
        });
    }

    public function attach(BankStatementImportLine $line, Invoice $invoice): string
    {
        $this->assertOpen($line, [
            BankStatementImportLine::KIND_UNRESOLVED,
            BankStatementImportLine::KIND_AMBIGUOUS,
        ]);

        if ($line->kind === BankStatementImportLine::KIND_AMBIGUOUS) {
            $allowed = array_map('intval', $line->candidate_invoice_ids ?? []);
            if (! in_array((int) $invoice->id, $allowed, true)) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Odabrani račun nije među ponuđenima.',
                ]);
            }
        }

        $alreadyUsed = BankStatementImportLine::query()
            ->where('bank_statement_import_id', $line->bank_statement_import_id)
            ->where('id', '!=', $line->id)
            ->where('invoice_id', $invoice->id)
            ->where('resolution', '!=', BankStatementImportLine::RESOLUTION_DISMISSED)
            ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Račun je već povezan s drugom stavkom ovog izvoda.',
            ]);
        }

        $exact = false;

        DB::transaction(function () use ($line, $invoice, &$exact) {
            $paid = round((float) $line->amount_paid, 2);
            $due = round((float) $invoice->amount_due, 2);
            $exact = $this->amountsMatch($due, $paid);

            $invoice->amount_paid = $paid;
            $invoice->payment_status = $exact ? 'Plaćeno' : 'Neusklađeno';
            $invoice->save();

            $line->invoice_id = $invoice->id;
            $line->member_id = $invoice->member_id;
            $line->amount_due = $due;
            if (! $line->reference) {
                $line->reference = $invoice->reference_code;
            }

            if ($exact) {
                $line->resolution = BankStatementImportLine::RESOLUTION_RESOLVED;
            } else {
                $line->kind = BankStatementImportLine::KIND_MISMATCH;
                $line->resolution = BankStatementImportLine::RESOLUTION_OPEN;
                $line->message = null;
            }

            $line->save();
        });

        return $exact
            ? 'Uplata je povezana i račun je označen plaćenim.'
            : 'Uplata je povezana. Iznos se razlikuje, stavka je među neusklađenima.';
    }

    public function dismiss(BankStatementImportLine $line): void
    {
        $this->assertOpen($line, [
            BankStatementImportLine::KIND_UNRESOLVED,
            BankStatementImportLine::KIND_AMBIGUOUS,
        ]);

        $line->resolution = BankStatementImportLine::RESOLUTION_DISMISSED;
        $line->save();
    }

    public function saveNote(BankStatementImportLine $line, ?string $note): void
    {
        $note = trim((string) $note);
        $line->note = $note !== '' ? $note : null;
        $line->save();
    }

    public function linkMember(BankStatementImportLine $line, Member $member): void
    {
        $this->assertOpen($line, [
            BankStatementImportLine::KIND_UNRESOLVED,
            BankStatementImportLine::KIND_AMBIGUOUS,
        ]);

        $line->member_id = $member->id;
        $line->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function suggestionsFor(BankStatementImportLine $line): array
    {
        $payer = trim((string) $line->payer);
        if ($payer === '') {
            return [];
        }

        $usedInvoiceIds = BankStatementImportLine::query()
            ->where('bank_statement_import_id', $line->bank_statement_import_id)
            ->where('id', '!=', $line->id)
            ->whereNotNull('invoice_id')
            ->where('resolution', '!=', BankStatementImportLine::RESOLUTION_DISMISSED)
            ->pluck('invoice_id');

        $query = Invoice::query()
            ->with('member:id,first_name,last_name')
            ->where('payment_status', '!=', 'Plaćeno');

        if ($usedInvoiceIds->isNotEmpty()) {
            $query->whereNotIn('id', $usedInvoiceIds);
        }

        if ($line->value_date) {
            $date = Carbon::parse($line->value_date);
            $query->whereBetween('due_date', [
                $date->copy()->subMonths(2)->startOfMonth()->toDateString(),
                $date->copy()->addMonth()->endOfMonth()->toDateString(),
            ]);
        }

        $scored = [];
        foreach ($query->get() as $invoice) {
            $memberName = $this->memberName($invoice->member);
            $score = $this->names->score($payer, $memberName);
            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'invoice' => $invoice,
                'member_name' => $memberName,
                'score' => $score,
            ];
        }

        usort($scored, function (array $left, array $right) use ($line) {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }

            $paid = (float) $line->amount_paid;

            return abs((float) $left['invoice']->amount_due - $paid)
                <=> abs((float) $right['invoice']->amount_due - $paid);
        });

        return array_map(
            fn (array $item) => $this->presentSuggestion($item['invoice'], $item['member_name']),
            array_slice($scored, 0, 5)
        );
    }

    /**
     * Members whose first and last name both appear in the payment text.
     *
     * @param  Collection<int, Member>  $members
     * @return array<int, array<string, mixed>>
     */
    public function membersNamedInPayment(BankStatementImportLine $line, Collection $members): array
    {
        $text = trim(((string) $line->description).' '.((string) $line->payer));
        if ($text === '' || $members->isEmpty()) {
            return [];
        }

        $matched = [];
        foreach ($members as $member) {
            if (! $this->names->containsFullName($text, (string) $member->first_name, (string) $member->last_name)) {
                continue;
            }

            $matched[] = $member;
            if (count($matched) >= 5) {
                break;
            }
        }

        if ($matched === []) {
            return [];
        }

        $memberIds = array_map(fn (Member $member) => $member->id, $matched);
        $usedInvoiceIds = BankStatementImportLine::query()
            ->where('bank_statement_import_id', $line->bank_statement_import_id)
            ->where('id', '!=', $line->id)
            ->whereNotNull('invoice_id')
            ->where('resolution', '!=', BankStatementImportLine::RESOLUTION_DISMISSED)
            ->pluck('invoice_id');

        $invoices = Invoice::query()
            ->with('member:id,first_name,last_name')
            ->whereIn('member_id', $memberIds)
            ->where('payment_status', '!=', 'Plaćeno')
            ->when($usedInvoiceIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $usedInvoiceIds))
            ->orderBy('due_date')
            ->get()
            ->groupBy('member_id');

        $describedMonth = PaymentMonth::fromDescription((string) $line->description);
        $enrollments = MemberWorkshop::query()
            ->with([
                'member:id,is_active',
                'workshop:id,name,type',
                'membershipPlan',
            ])
            ->whereIn('member_id', $memberIds)
            ->whereHas('workshop', fn ($query) => $query->where('type', '!=', 'Individualno'))
            ->get()
            ->groupBy('member_id');

        return array_map(function (Member $member) use ($invoices, $line, $describedMonth, $enrollments) {
            $memberInvoices = $invoices->get($member->id, collect());
            $date = $line->value_date ? Carbon::parse($line->value_date) : null;
            if ($memberInvoices->isNotEmpty() && ($date || $describedMonth)) {
                $memberInvoices = $memberInvoices->sortBy(function (Invoice $invoice) use ($date, $describedMonth) {
                    $due = $invoice->due_date ? Carbon::parse($invoice->due_date) : null;
                    $sameMonth = $due && $describedMonth
                        && $due->year === $describedMonth->year
                        && $due->month === $describedMonth->month
                        ? 0
                        : 1;
                    $distance = $due && $date ? abs($date->diffInDays($due)) : PHP_INT_MAX;

                    return [$sameMonth, $distance];
                })->values();
            }

            $name = $this->memberName($member);

            return [
                'member_id' => $member->id,
                'member_name' => $name,
                'invoices' => $memberInvoices
                    ->take(3)
                    ->map(fn (Invoice $invoice) => $this->presentSuggestion($invoice, (string) $name))
                    ->values()
                    ->all(),
                'missing_invoices' => $describedMonth
                    ? $this->missingInvoicesFor($enrollments->get($member->id, collect()), $describedMonth)
                    : [],
                'workshops' => $enrollments->get($member->id, collect())
                    ->map(fn (MemberWorkshop $enrollment) => [
                        'workshop_id' => $enrollment->workshop_id,
                        'workshop_name' => $enrollment->workshop?->name,
                    ])
                    ->values()
                    ->all(),
                'suggested_month' => ($describedMonth ?? ($line->value_date ? Carbon::parse($line->value_date)->startOfMonth() : null))?->format('Y-m'),
            ];
        }, $matched);
    }

    /**
     * Create the membership invoice for the month named in the payment description.
     */
    public function generateInvoice(
        BankStatementImportLine $line,
        Member $member,
        int $workshopId,
        string $targetMonth
    ): Invoice {
        $this->assertOpen($line, [
            BankStatementImportLine::KIND_UNRESOLVED,
            BankStatementImportLine::KIND_AMBIGUOUS,
        ]);

        try {
            $month = Carbon::createFromFormat('!Y-m', $targetMonth)->startOfMonth();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'target_month' => 'Odabrani mjesec nije ispravan.',
            ]);
        }

        $earliest = now()->subMonths(18)->startOfMonth();
        $latest = now()->addMonths(14)->startOfMonth();
        if ($month->lt($earliest) || $month->gt($latest)) {
            throw ValidationException::withMessages([
                'target_month' => 'Odabrani mjesec je izvan dozvoljenog razdoblja.',
            ]);
        }

        $namedInPayment = $this->names->containsFullName(
            trim(((string) $line->description).' '.((string) $line->payer)),
            (string) $member->first_name,
            (string) $member->last_name
        );
        if (! $namedInPayment && (int) $line->member_id !== (int) $member->id) {
            throw ValidationException::withMessages([
                'member_id' => 'Član nije naveden u tekstu uplate.',
            ]);
        }

        $enrollment = MemberWorkshop::query()
            ->with(['member', 'workshop', 'membershipPlan'])
            ->where('member_id', $member->id)
            ->where('workshop_id', $workshopId)
            ->first();

        if (! $enrollment || strcasecmp((string) $enrollment->workshop?->type, 'Individualno') === 0) {
            throw ValidationException::withMessages([
                'workshop_id' => 'Član nema članarinu na toj radionici.',
            ]);
        }

        if (! $this->invoices->shouldGenerateInvoice($enrollment, $month)) {
            throw ValidationException::withMessages([
                'target_month' => 'Račun za taj mjesec nije moguće generirati.',
            ]);
        }

        $invoice = $this->invoices->createInvoice($enrollment, $month);
        if (! $invoice) {
            throw ValidationException::withMessages([
                'target_month' => 'Račun za taj mjesec nije moguće generirati.',
            ]);
        }

        return $invoice;
    }

    /**
     * @param  Collection<int, MemberWorkshop>  $enrollments
     * @return array<int, array<string, mixed>>
     */
    private function missingInvoicesFor(Collection $enrollments, Carbon $month): array
    {
        $missing = [];

        foreach ($enrollments as $enrollment) {
            if (! $this->invoices->shouldGenerateInvoice($enrollment, $month)) {
                continue;
            }

            $missing[] = [
                'workshop_id' => $enrollment->workshop_id,
                'workshop_name' => $enrollment->workshop?->name,
                'target_month' => $month->format('Y-m'),
                'label' => $month->format('m/Y'),
            ];
        }

        return $missing;
    }

    /**
     * @param  array<int, string>  $kinds
     */
    private function assertOpen(BankStatementImportLine $line, array $kinds): void
    {
        if (
            $line->resolution !== BankStatementImportLine::RESOLUTION_OPEN
            || ! in_array($line->kind, $kinds, true)
        ) {
            throw ValidationException::withMessages([
                'line' => 'Ova stavka više nije otvorena za izmjenu.',
            ]);
        }
    }

    /**
     * @param  Collection<int|string, Collection<int, MembershipPlan>>  $plansByWorkshop
     * @param  Collection<string, MemberWorkshop>  $enrollments
     * @return array<string, mixed>
     */
    private function presentMismatch(
        BankStatementImportLine $line,
        Collection $plansByWorkshop,
        Collection $enrollments
    ): array {
        $invoice = $line->invoice;
        $member = $line->member ?? $invoice?->member;
        $currentPlan = null;
        $matchingPlans = [];

        if ($invoice) {
            $enrollment = $enrollments->get($invoice->member_id.'-'.$invoice->workshop_id);
            $plan = $enrollment?->membershipPlan ?? $invoice->membershipPlan;
            if ($plan) {
                $currentPlan = $this->presentPlan($plan);
            }

            foreach ($plansByWorkshop->get($invoice->workshop_id, collect()) as $candidate) {
                if ($plan && (int) $candidate->id === (int) $plan->id) {
                    continue;
                }

                if ($this->amountsMatch($this->chargedAmount($candidate), (float) $line->amount_paid)) {
                    $matchingPlans[] = $this->presentPlan($candidate);
                }
            }
        }

        return [
            'id' => $line->id,
            'row_number' => $line->row_number,
            'payer' => $line->payer,
            'member_name' => $this->memberName($member),
            'member_id' => $line->member_id ?? $invoice?->member_id,
            'reference' => $line->reference,
            'amount_paid' => (float) $line->amount_paid,
            'amount_due' => $line->amount_due !== null ? (float) $line->amount_due : null,
            'description' => $line->description,
            'note' => $line->note,
            'current_plan' => $currentPlan,
            'matching_plans' => $matchingPlans,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $suggestions
     * @param  array<int, array<string, mixed>>  $memberSuggestions
     * @return array<string, mixed>
     */
    private function presentProblem(BankStatementImportLine $line, array $suggestions, array $memberSuggestions = []): array
    {
        return [
            'id' => $line->id,
            'row_number' => $line->row_number,
            'payer' => $line->payer,
            'member_id' => $line->member_id,
            'member_name' => $this->memberName($line->member),
            'amount_paid' => (float) $line->amount_paid,
            'reference' => $line->reference,
            'description' => $line->description,
            'message' => $line->message,
            'note' => $line->note,
            'value_date' => $line->value_date?->format('d.m.Y.'),
            'suggestions' => $suggestions,
            'member_suggestions' => $memberSuggestions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentResolved(BankStatementImportLine $line): array
    {
        $member = $line->member ?? $line->invoice?->member;

        return [
            'id' => $line->id,
            'row_number' => $line->row_number,
            'payer' => $line->payer,
            'member_id' => $line->member_id ?? $line->invoice?->member_id,
            'member_name' => $this->memberName($member),
            'reference' => $line->reference,
            'amount_paid' => (float) $line->amount_paid,
            'amount_due' => $line->amount_due !== null ? (float) $line->amount_due : null,
            'kind' => $line->kind,
            'resolution' => $line->resolution,
            'message' => $line->message,
            'note' => $line->note,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function candidateSuggestions(BankStatementImportLine $line): array
    {
        $ids = array_map('intval', $line->candidate_invoice_ids ?? []);
        if ($ids === []) {
            return [];
        }

        $invoices = Invoice::query()
            ->with('member:id,first_name,last_name')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $suggestions = [];
        foreach ($ids as $id) {
            $invoice = $invoices->get($id);
            if (! $invoice) {
                continue;
            }

            $suggestions[] = $this->presentSuggestion($invoice, $this->memberName($invoice->member));
        }

        return $suggestions;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSuggestion(Invoice $invoice, string $memberName): array
    {
        return [
            'invoice_id' => $invoice->id,
            'member_id' => $invoice->member_id,
            'member_name' => $memberName,
            'reference' => $invoice->reference_code,
            'amount_due' => (float) $invoice->amount_due,
            'due_date' => $invoice->due_date
                ? Carbon::parse($invoice->due_date)->format('d.m.Y.')
                : null,
        ];
    }

    /**
     * @return array{id: int, name: string, fee: float}
     */
    private function presentPlan(MembershipPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->plan,
            'fee' => $this->chargedAmount($plan),
        ];
    }

    private function chargedAmount(MembershipPlan $plan): float
    {
        return round((float) ($plan->total_fee ?? $plan->fee), 2);
    }

    private function amountsMatch(float $left, float $right): bool
    {
        return abs($left - $right) < 0.01;
    }

    private function memberName(?object $member): ?string
    {
        if (! $member) {
            return null;
        }

        $name = trim(($member->first_name ?? '').' '.($member->last_name ?? ''));

        return $name !== '' ? $name : null;
    }
}

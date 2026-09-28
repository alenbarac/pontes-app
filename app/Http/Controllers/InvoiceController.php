<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDownloadInvoiceSlipsRequest;
use App\Http\Requests\BulkSendInvoiceEmailsRequest;
use App\Models\Invoice;
use App\Models\MemberGroup;
use App\Models\MembershipPlan;
use App\Models\Workshop;
use App\Services\PaymentSlipEmailService;
use App\Services\PaymentSlipPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(
        protected PaymentSlipEmailService $paymentSlipEmailService,
        protected PaymentSlipPdfService $paymentSlipPdfService,
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $filter = $request->get('filter', '');
        $workshopId = $request->get('workshop_id', '');
        $paymentStatus = $request->get('payment_status', '');
        $groupId = $request->get('group_id', '');
        $monthFilter = $request->get('month', ''); // Format: YYYY-MM
        $membershipPlanId = $request->get('membership_plan_id', '');
        $hasDiscount = $request->get('has_discount', '');

        $query = Invoice::with([
            'member',
            'member.workshopGroups.group',
            'workshop',
            'membershipPlan',
            'latestSuccessfulSlipMailing',
            'latestSlipMailing',
        ])
            ->when($filter, function ($query, $filter) {
                $query->where(function ($q) use ($filter) {
                    $q->whereHas('member', fn ($q) => $q->where('first_name', 'like', "%$filter%")
                        ->orWhere('last_name', 'like', "%$filter%")
                    )
                        ->orWhereHas('workshop', fn ($q) => $q->where('name', 'like', "%$filter%")
                        )
                        ->orWhere('reference_code', 'like', "%$filter%")
                        ->orWhere('payment_status', 'like', "%$filter%");
                });
            })
            ->when($workshopId, function ($query, $workshopId) {
                $query->where('workshop_id', $workshopId);
            })
            ->when($paymentStatus, function ($query, $paymentStatus) {
                $query->where('payment_status', $paymentStatus);
            })
            ->when($groupId, function ($query, $groupId) use ($workshopId) {
                $query->whereHas('member.workshopGroups', function ($q) use ($groupId, $workshopId) {
                    $q->where('member_group_id', $groupId);
                    if ($workshopId) {
                        $q->where('workshop_id', $workshopId);
                    }
                });
            })
            ->when($monthFilter, function ($query, $monthFilter) {
                // Filter by year and month (format: YYYY-MM)
                if (preg_match('/^(\d{4})-(\d{2})$/', $monthFilter, $matches)) {
                    $year = (int) $matches[1];
                    $month = (int) $matches[2];
                    $query->whereYear('due_date', $year)
                        ->whereMonth('due_date', $month);
                }
            })
            ->when($membershipPlanId, function ($query, $membershipPlanId) {
                $query->where('membership_plan_id', $membershipPlanId);
            })
            ->when($hasDiscount === '1' || $hasDiscount === 'any', function ($query) {
                $query->withDiscount();
            })
            ->when($hasDiscount === '0' || $hasDiscount === 'none', function ($query) {
                $query->withoutDiscount();
            })
            ->orderByDesc('due_date');

        $invoices = $query->paginate($perPage)->withQueryString();

        // Get all workshops for the filter dropdown
        $workshops = Workshop::select('id', 'name')->orderBy('name')->get();

        // Payment status options
        $paymentStatuses = ['Otvoreno', 'Plaćeno', 'Neusklađeno', 'Opomeni'];

        // Groups for filter (optionally scoped by workshop)
        // Some schemas don't have workshop_id on member_groups, so fall back to the mapping table workshop_groups
        $groups = MemberGroup::query()
            ->select('member_groups.id', 'member_groups.name')
            ->when($workshopId, function ($q) use ($workshopId) {
                $q->join('workshop_groups', 'workshop_groups.member_group_id', '=', 'member_groups.id')
                    ->where('workshop_groups.workshop_id', $workshopId)
                    ->distinct();
            })
            ->orderBy('member_groups.name')
            ->get();

        $membershipPlans = MembershipPlan::query()
            ->select('id', 'workshop_id', 'plan', 'total_fee', 'discount_type')
            ->with('workshop:id,name')
            ->when($workshopId, function ($q) use ($workshopId) {
                $q->where('workshop_id', $workshopId);
            })
            ->orderBy('plan')
            ->get()
            ->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'workshop_id' => $plan->workshop_id,
                'plan' => $plan->plan,
                'total_fee' => $plan->total_fee,
                'discount_type' => $plan->discount_type,
                'workshop_name' => $plan->workshop?->name,
                'is_discounted' => $plan->isDiscounted(),
            ]);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage(),
            ],
            'filter' => $filter,
            'workshopId' => $workshopId,
            'paymentStatus' => $paymentStatus,
            'groupId' => $groupId,
            'month' => $monthFilter,
            'membershipPlanId' => $membershipPlanId,
            'hasDiscount' => $hasDiscount,
            'workshops' => $workshops,
            'paymentStatuses' => $paymentStatuses,
            'groups' => $groups,
            'membershipPlans' => $membershipPlans,
        ]);
    }

    public function show(Invoice $invoice)
    {
        // Redirect to invoices index with filter for this invoice's reference code
        // This allows users to see the invoice in the table context
        return redirect()->route('invoices.index', [
            'filter' => $invoice->reference_code,
        ]);
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Plaćeno,Otvoreno,Neusklađeno'],
        ]);

        $status = $validated['status'];

        if ($status === 'Plaćeno') {
            // If you hit this endpoint instead of markPaid, set amount_paid to full
            $invoice->amount_paid = $invoice->amount_due;
        } elseif ($status === 'Otvoreno') {
            $invoice->amount_paid = 0;
        }

        $invoice->payment_status = $status;
        $invoice->save();

        // If coming from member page or Inertia request, return back instead of redirecting
        if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
            return back()->with('success', 'Status uspješno promijenjen.');
        }

        return redirect()->route('invoices.index')->with('success', 'Status uspješno promijenjen.');
    }

    /**
     * Update slip description and optionally payment status for a single invoice.
     */
    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'slip_description' => ['nullable', 'string', 'max:255'],
            'amount_due' => ['nullable', 'numeric', 'min:0.01'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:Plaćeno,Otvoreno,Neusklađeno'],
        ]);

        if (array_key_exists('slip_description', $validated)) {
            $description = trim((string) ($validated['slip_description'] ?? ''));
            $invoice->slip_description = $description !== '' ? $description : null;
        }

        if (array_key_exists('discount_percent', $validated)) {
            $percent = $validated['discount_percent'] !== null
                ? (float) $validated['discount_percent']
                : null;
            $invoice->applyPercentageDiscount($percent);
        }

        if (array_key_exists('amount_due', $validated) && $validated['amount_due'] !== null) {
            $invoice->amount_due = round((float) $validated['amount_due'], 2);
        }

        if (array_key_exists('due_date', $validated) && ! empty($validated['due_date'])) {
            $invoice->due_date = $validated['due_date'];
        }

        $status = $validated['status'] ?? $invoice->payment_status;
        if (! empty($validated['status'])) {
            $invoice->payment_status = $status;
        }

        if ($invoice->payment_status === 'Plaćeno') {
            $invoice->amount_paid = $invoice->amount_due;
        } elseif ($invoice->payment_status === 'Otvoreno') {
            $invoice->amount_paid = 0;
        }

        $invoice->save();

        if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
            return back()->with('success', 'Račun je uspješno ažuriran.');
        }

        return redirect()->route('invoices.index')->with('success', 'Račun je uspješno ažuriran.');
    }

    /**
     * Bulk update status for many invoices
     */
    public function markBulkAsPaid(Request $request)
    {
        $validated = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
        ]);

        // Only update if not already marked as paid
        $invoices = Invoice::whereIn('id', $validated['invoice_ids'])
            ->where('payment_status', '!=', 'Plaćeno')
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->amount_paid = $invoice->amount_due;
            $invoice->payment_status = 'Plaćeno';
            $invoice->save();
        }

        $updatedCount = $invoices->count();
        $total = count($validated['invoice_ids']);

        if ($updatedCount === 0) {
            return redirect()->route('invoices.index')->with('info', 'Svi označeni računi su već bili plaćeni.');
        }

        return redirect()->route('invoices.index')
            ->with('success', "Označeno kao plaćeno: {$updatedCount}/{$total} računa.");
    }

    /**
     * Bulk toggle open/paid status for invoices
     */
    public function toggleBulkInvoiceStatus(Request $request)
    {
        $validated = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'status' => ['required', 'in:Plaćeno,Otvoreno,Neusklađeno'],
        ]);

        $status = $validated['status'];

        $invoices = Invoice::whereIn('id', $validated['invoice_ids'])->get();

        $updatedCount = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->payment_status !== $status) {
                if ($status === 'Plaćeno') {
                    $invoice->amount_paid = $invoice->amount_due;
                } elseif ($status === 'Otvoreno') {
                    $invoice->amount_paid = 0;
                }
                $invoice->payment_status = $status;
                $invoice->save();
                $updatedCount++;
            }
        }

        $total = count($validated['invoice_ids']);

        if ($updatedCount === 0) {
            return redirect()->route('invoices.index')->with(
                'info',
                $status === 'Plaćeno'
                    ? 'Svi označeni računi su već bili plaćeni.'
                    : 'Svi označeni računi su već bili otvoreni.'
            );
        }

        $statusText = match ($status) {
            'Plaćeno' => 'plaćenih',
            'Otvoreno' => 'otvorenih',
            default => 'neusklađenih',
        };

        return redirect()->route('invoices.index')
            ->with('success', "Ažurirano kao {$statusText}: {$updatedCount}/{$total} računa.");
    }

    public function markAsPaid(Request $request, Invoice $invoice)
    {
        if ($invoice->payment_status === 'Plaćeno') {
            // If coming from member page or Inertia request, return back instead of redirecting
            if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
                return back()->with('info', 'Račun je već plaćen.');
            }

            return redirect()->route('invoices.index')->with('info', 'Račun je već plaćen.');
        }

        // Optional: allow override amount; by default pay in full
        $validated = $request->validate([
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice->amount_paid = $validated['amount_paid'] ?? $invoice->amount_due;
        $invoice->payment_status = 'Plaćeno';
        $invoice->save();

        // If coming from member page or Inertia request, return back instead of redirecting
        if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
            return back()->with('success', 'Račun označen kao plaćen.');
        }

        return redirect()->route('invoices.index')->with('success', 'Račun označen kao plaćen.');
    }

    /**
     * Generate PDF slip for an invoice (public method for reuse).
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    /**
     * @deprecated Use PaymentSlipPdfService::generate(Invoice $invoice) directly.
     *             Kept as a thin wrapper so any older callers keep working.
     */
    public function generateSlipPDF(Invoice $invoice)
    {
        return $this->paymentSlipPdfService->generate($invoice);
    }

    public function slip(Invoice $invoice)
    {
        $pdf = $this->generateSlipPDF($invoice);

        return $pdf->stream($this->paymentSlipPdfService->pdfFilename($invoice));
    }

    /**
     * Bulk download payment slips for selected invoices as a ZIP.
     */
    public function bulkDownloadSlips(BulkDownloadInvoiceSlipsRequest $request)
    {
        $invoices = Invoice::with(['member', 'workshop', 'membershipPlan'])
            ->whereIn('id', $request->validated('invoice_ids'))
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'message' => 'Nema odabranih računa.',
                'errors' => ['invoice_ids' => ['Nema odabranih računa.']],
            ], 404);
        }

        try {
            $zipFileName = $this->paymentSlipPdfService->zipDownloadName($invoices);
            $zipPath = $this->paymentSlipPdfService->zipForInvoices($invoices, $zipFileName);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['error' => [$e->getMessage()]],
            ], 500);
        }

        return response()->download($zipPath, $zipFileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Send payment slip via email to the member.
     */
    public function sendEmail(Request $request, Invoice $invoice)
    {
        $invoice->load(['member', 'workshop', 'membershipPlan']);

        $result = $this->paymentSlipEmailService->sendForInvoice($invoice, $request->boolean('resend'));

        if (! $result['ok']) {
            $reason = $result['reason'] ?? '';
            $errorMessage = match ($reason) {
                'no_email' => 'Član nema unesenu e-mail adresu za račune. Molimo unesite polje „Email za račune” (invoice_email) u podacima člana.',
                'already_sent' => 'Uplatnica je već poslana. Potvrdite ponovno slanje ako je želite poslati još jednom.',
                default => 'Došlo je do greške pri slanju e-maila. Molimo pokušajte ponovno.',
            };

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $errorMessage,
                    'reason' => $reason !== '' ? $reason : 'mail_error',
                    'sent_at' => $result['sent_at'] ?? null,
                ], 422);
            }

            if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
                return back()->with('error', $errorMessage);
            }

            return redirect()->route('invoices.index')->with('error', $errorMessage);
        }

        $successMessage = $this->paymentSlipEmailService->startedMessage(1);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $successMessage,
                'recipient' => $result['recipient'],
                'mailing_id' => $result['mailing_id'] ?? null,
            ]);
        }

        if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
            return back()->with('success', $successMessage);
        }

        return redirect()->route('invoices.index')->with('success', $successMessage);
    }

    /**
     * Queue payment-slip e-mails for many invoices (JSON).
     */
    public function bulkSendSlipEmails(BulkSendInvoiceEmailsRequest $request): JsonResponse
    {
        $summary = $this->paymentSlipEmailService->sendForInvoiceIds(
            $request->validated('invoice_ids'),
            $request->boolean('resend'),
        );

        if ($summary['exceeds_cap']) {
            return response()->json($this->paymentSlipEmailService->capExceededPayload(), 422);
        }

        return response()->json([
            'queued' => $summary['queued'],
            'skipped_no_email' => $summary['skipped_no_email'],
            'skipped_already_sent' => $summary['skipped_already_sent'],
            'failed' => $summary['failed'],
            'mailing_id' => $summary['mailing_id'] ?? null,
            'message' => $summary['message'] ?? $this->paymentSlipEmailService->humanSummary($summary),
        ]);
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        $invoice->delete();

        // If coming from member page or Inertia request, return back instead of redirecting
        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Račun uspješno obrisan.');
        }

        return redirect()->route('invoices.index')->with('success', 'Račun uspješno obrisan.');
    }
}

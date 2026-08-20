<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkSendInvoiceEmailsRequest;
use App\Models\Invoice;
use App\Models\MemberGroup;
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

        $query = Invoice::with(['member', 'member.workshopGroups.group', 'workshop', 'membershipPlan'])
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
            'workshops' => $workshops,
            'paymentStatuses' => $paymentStatuses,
            'groups' => $groups,
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
            'status' => ['nullable', 'in:Plaćeno,Otvoreno,Neusklađeno'],
        ]);

        if (array_key_exists('slip_description', $validated)) {
            $description = trim((string) ($validated['slip_description'] ?? ''));
            $invoice->slip_description = $description !== '' ? $description : null;
        }

        if (array_key_exists('amount_due', $validated) && $validated['amount_due'] !== null) {
            $invoice->amount_due = round((float) $validated['amount_due'], 2);
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

        // Generate filename: Firstname-Lastname-referencecode.pdf
        $firstName = trim($invoice->member->first_name ?? '');
        $lastName = trim($invoice->member->last_name ?? '');

        // Transliterate Croatian characters to ASCII
        $firstName = $this->transliterateCroatian($firstName);
        $lastName = $this->transliterateCroatian($lastName);

        // Convert to lowercase, sanitize, then capitalize first letter
        $firstName = strtolower($firstName);
        $lastName = strtolower($lastName);
        // Remove special characters and replace spaces with hyphens
        $firstName = preg_replace('/[^a-z0-9]+/', '-', $firstName);
        $lastName = preg_replace('/[^a-z0-9]+/', '-', $lastName);
        // Capitalize first letter of each name
        $firstName = ucfirst($firstName);
        $lastName = ucfirst($lastName);
        $fileName = trim($firstName.'-'.$lastName.'-'.$invoice->reference_code, '-').'.pdf';

        // Stream in a new tab (nice for printing); change to download() if you prefer attachment
        return $pdf->stream($fileName);
    }

    /**
     * Send payment slip via email to the member.
     */
    public function sendEmail(Request $request, Invoice $invoice)
    {
        $invoice->load(['member', 'workshop', 'membershipPlan']);

        $result = $this->paymentSlipEmailService->sendForInvoice($invoice);

        if (! $result['ok']) {
            $errorMessage = ($result['reason'] ?? '') === 'no_email'
                ? 'Član nema unesenu e-mail adresu za račune. Molimo unesite polje „Email za račune” (invoice_email) u podacima člana.'
                : 'Došlo je do greške pri slanju e-maila. Molimo pokušajte ponovno.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $errorMessage], 422);
            }

            if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
                return back()->with('error', $errorMessage);
            }

            return redirect()->route('invoices.index')->with('error', $errorMessage);
        }

        $successMessage = 'Uplatnica je uspješno poslana na e-mail adresu: '.$result['recipient'];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $successMessage,
                'recipient' => $result['recipient'],
            ]);
        }

        if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
            return back()->with('success', $successMessage);
        }

        return redirect()->route('invoices.index')->with('success', $successMessage);
    }

    /**
     * Send payment slips by e-mail for many invoices (synchronous, JSON).
     */
    public function bulkSendSlipEmails(BulkSendInvoiceEmailsRequest $request): JsonResponse
    {
        $summary = $this->paymentSlipEmailService->sendForInvoiceIds($request->validated('invoice_ids'));

        return response()->json([
            'sent' => $summary['sent'],
            'skipped_no_email' => $summary['skipped_no_email'],
            'failed' => $summary['failed'],
            'message' => $this->paymentSlipEmailService->humanSummary($summary),
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

    /**
     * Transliterate Croatian characters to ASCII equivalents.
     */
    private function transliterateCroatian(string $text): string
    {
        // Handle multi-character sequences first (DŽ, dž)
        $text = str_replace(['DŽ', 'dž', 'Dž'], ['DJ', 'dj', 'Dj'], $text);

        // Handle single characters
        $transliteration = [
            'Č' => 'C', 'č' => 'c',
            'Ć' => 'C', 'ć' => 'c',
            'Đ' => 'D', 'đ' => 'd',
            'Š' => 'S', 'š' => 's',
            'Ž' => 'Z', 'ž' => 'z',
        ];

        return strtr($text, $transliteration);
    }

    /**
     * Clean up old temporary files to prevent storage bloat.
     *
     * @param  int  $maxAgeSeconds  Maximum age in seconds (default: 1 hour)
     */
    private function cleanupOldTempFiles(string $directory, int $maxAgeSeconds = 3600): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory.'/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileAge = $now - filemtime($file);
                if ($fileAge > $maxAgeSeconds) {
                    @unlink($file);
                }
            }
        }
    }
}

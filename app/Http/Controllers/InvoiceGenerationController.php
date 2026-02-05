<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\InvoiceGenerationService;
use App\Services\SchoolYearService;
use App\Models\Invoice;
use Carbon\Carbon;
use Inertia\Inertia;

class InvoiceGenerationController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceGenerationService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Show invoice generation interface.
     */
    public function index()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $currentSchoolYear = SchoolYearService::getCurrentSchoolYear();
        
        // Get months in current school year
        $schoolYearMonths = SchoolYearService::getMonthsInSchoolYear();
        
        // Format month labels in Croatian
        $monthNames = [
            1 => 'siječanj', 2 => 'veljača', 3 => 'ožujak', 4 => 'travanj',
            5 => 'svibanj', 6 => 'lipanj', 7 => 'srpanj', 8 => 'kolovoz',
            9 => 'rujan', 10 => 'listopad', 11 => 'studeni', 12 => 'prosinac'
        ];
        
        // Get status for each month in school year
        $monthStatuses = [];
        foreach ($schoolYearMonths as $month) {
            $invoiceCount = Invoice::forMonth($month)->count();
            $monthLabel = $monthNames[$month->month] . ' ' . $month->year;
            $monthStatuses[] = [
                'month' => $month->format('Y-m'),
                'label' => $monthLabel,
                'invoice_count' => $invoiceCount,
                'is_current' => $month->isSameMonth($currentMonth),
                'is_past' => $month->lt($currentMonth),
                'is_future' => $month->gt($currentMonth),
            ];
        }
        
        // Format school year dates for display
        $currentSchoolYear['start'] = $currentSchoolYear['start']->format('d.m.Y');
        $currentSchoolYear['end'] = $currentSchoolYear['end']->format('d.m.Y');

        return Inertia::render('Invoices/Generate', [
            'currentMonth' => $currentMonth->format('Y-m'),
            'currentSchoolYear' => $currentSchoolYear,
            'monthStatuses' => $monthStatuses,
        ]);
    }

    /**
     * Preview invoices that would be generated for a target month.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'target_month' => ['required', 'date_format:Y-m'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $targetMonth = Carbon::createFromFormat('Y-m', $validated['target_month'])->startOfMonth();
        $memberIds = $validated['member_ids'] ?? null;

        $previews = $this->invoiceService->previewInvoicesForMonth($targetMonth, $memberIds);

        // Format month label in Croatian
        $monthNames = [
            1 => 'siječanj', 2 => 'veljača', 3 => 'ožujak', 4 => 'travanj',
            5 => 'svibanj', 6 => 'lipanj', 7 => 'srpanj', 8 => 'kolovoz',
            9 => 'rujan', 10 => 'listopad', 11 => 'studeni', 12 => 'prosinac'
        ];
        $monthLabel = $monthNames[$targetMonth->month] . ' ' . $targetMonth->year;

        return response()->json([
            'target_month' => $targetMonth->format('Y-m'),
            'target_month_label' => $monthLabel,
            'previews' => $previews->values()->all(),
            'summary' => [
                'total' => $previews->count(),
                'ready_to_generate' => $previews->where('should_generate', true)->count(),
                'already_exists' => $previews->where('already_exists', true)->count(),
                'skipped' => $previews->where('should_generate', false)->where('already_exists', false)->count(),
            ],
        ]);
    }

    /**
     * Generate invoices for a specific month.
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'target_month' => ['required', 'date_format:Y-m'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $targetMonth = Carbon::createFromFormat('Y-m', $validated['target_month'])->startOfMonth();
        $memberIds = $validated['member_ids'] ?? null;

        // Validate that target month is not too far in the future (optional business rule)
        $maxFutureMonths = 3;
        $maxDate = Carbon::now()->addMonths($maxFutureMonths)->endOfMonth();
        if ($targetMonth->gt($maxDate)) {
            return back()->withErrors([
                'target_month' => "Cannot generate invoices more than {$maxFutureMonths} months in advance.",
            ]);
        }

        $result = $this->invoiceService->generateInvoicesForMonth($targetMonth, $memberIds);

        if (!empty($result['errors'])) {
            return back()->withErrors([
                'generation' => 'Some invoices failed to generate. Check logs for details.',
            ])->with('generation_result', $result);
        }

        $message = "Generated {$result['generated']} invoices";
        if ($result['skipped'] > 0) {
            $message .= ", skipped {$result['skipped']} (already exist or don't match criteria)";
        }

        return redirect()->route('invoices.generate.index')
            ->with('success', $message)
            ->with('generation_result', $result);
    }

    /**
     * Regenerate a specific invoice (if needed for corrections).
     */
    public function regenerate(Request $request, Invoice $invoice)
    {
        // This is a placeholder for future functionality
        // For now, we'll just return an error
        return back()->withErrors([
            'regenerate' => 'Invoice regeneration is not yet implemented.',
        ]);
    }

    /**
     * Delete all invoices for a specific month.
     */
    public function deleteMonth(Request $request)
    {
        $validated = $request->validate([
            'target_month' => ['required', 'date_format:Y-m'],
        ]);

        $targetMonth = Carbon::createFromFormat('Y-m', $validated['target_month'])->startOfMonth();

        // Count invoices for this month
        $invoiceCount = Invoice::forMonth($targetMonth)->count();

        if ($invoiceCount === 0) {
            return response()->json([
                'message' => 'Nema računa za brisanje za odabrani mjesec.',
            ], 200);
        }

        // Delete all invoices for this month
        $deleted = Invoice::forMonth($targetMonth)->delete();

        // Format month label in Croatian
        $monthNames = [
            1 => 'siječanj', 2 => 'veljača', 3 => 'ožujak', 4 => 'travanj',
            5 => 'svibanj', 6 => 'lipanj', 7 => 'srpanj', 8 => 'kolovoz',
            9 => 'rujan', 10 => 'listopad', 11 => 'studeni', 12 => 'prosinac'
        ];
        $monthLabel = $monthNames[$targetMonth->month] . ' ' . $targetMonth->year;

        return response()->json([
            'message' => "Uspješno obrisano {$deleted} računa za {$monthLabel}.",
            'deleted_count' => $deleted,
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MemberGroup;
use App\Http\Resources\MemberGroupResource;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        $now = Carbon::now();
        $currentMonth = $now->copy()->startOfMonth();
        $today = $now->copy()->startOfDay();

        $currentMonthGenerated = Invoice::forMonth($currentMonth);
        $currentMonthPaid = Invoice::forMonth($currentMonth)->where('payment_status', 'Plaćeno');
        $dueInvoices = Invoice::where('payment_status', '!=', 'Plaćeno')
            ->whereDate('due_date', '<', $today);

        $revenue = [
            // Revenue based on payments recorded in current month.
            'current_month_revenue' => (float) Invoice::where('amount_paid', '>', 0)
                ->whereMonth('updated_at', $currentMonth->month)
                ->whereYear('updated_at', $currentMonth->year)
                ->sum('amount_paid'),
            'current_month_generated_count' => (int) (clone $currentMonthGenerated)->count(),
            'current_month_generated_amount' => (float) (clone $currentMonthGenerated)->sum('amount_due'),
            'current_month_paid_count' => (int) (clone $currentMonthPaid)->count(),
            'current_month_paid_amount' => (float) (clone $currentMonthPaid)->sum('amount_paid'),
            'due_invoices_count' => (int) (clone $dueInvoices)->count(),
            'due_invoices_amount' => (float) (clone $dueInvoices)->sum(\Illuminate\Support\Facades\DB::raw('GREATEST(amount_due - amount_paid, 0)')),
        ];

        $labels = [];
        $generatedSeries = [];
        $paidSeries = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = $now->copy()->startOfMonth()->subMonths($i);
            $labels[] = $month->format('M Y');
            $generatedSeries[] = (float) Invoice::forMonth($month)->sum('amount_due');
            $paidSeries[] = (float) Invoice::forMonth($month)
                ->where('payment_status', 'Plaćeno')
                ->sum('amount_paid');
        }

        $revenue['trend'] = [
            'labels' => $labels,
            'generated' => $generatedSeries,
            'paid' => $paidSeries,
        ];

        // Get all groups with member count
        $groups = MemberGroup::with('assignedWorkshop')
            ->withCount('members')
            ->get()
            ->map(function ($group) {
                return new MemberGroupResource($group);
            });

        return Inertia::render('Dashboard', [
            'revenue' => $revenue,
            'groups' => $groups,
        ]);
    }
}

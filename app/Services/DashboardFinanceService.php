<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MemberWorkshop;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardFinanceService
{
    /**
     * Build dashboard finance metrics for the given point in time.
     */
    public function buildMetrics(?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now())->copy();

        return [
            'periods' => [
                'current_month' => $this->buildCurrentMonthPeriod($now),
                'school_year' => $this->buildSchoolYearPeriod($now),
            ],
            'trend' => $this->buildTrend($now),
        ];
    }

    private function buildCurrentMonthPeriod(Carbon $now): array
    {
        $currentMonth = $now->copy()->startOfMonth();
        $monthName = ucfirst($now->locale('hr')->translatedFormat('F'));

        return $this->buildPeriodMetrics(
            Invoice::membership()->forMonth($currentMonth),
            $now,
            $monthName,
            $monthName,
        );
    }

    private function buildSchoolYearPeriod(Carbon $now): array
    {
        $schoolYear = SchoolYearService::getCurrentSchoolYear();

        $invoices = Invoice::membership()
            ->whereDate('due_date', '>=', $schoolYear['start'])
            ->whereDate('due_date', '<=', $schoolYear['end']);

        return $this->buildPeriodMetrics(
            $invoices,
            $now,
            'školska godina',
            'Školska godina',
            $schoolYear['label'],
        );
    }

    /**
     * @param  Builder<Invoice>  $invoices
     */
    private function buildPeriodMetrics(
        Builder $invoices,
        Carbon $now,
        string $label,
        string $buttonLabel,
        ?string $schoolYearLabel = null,
    ): array {
        $today = $now->copy()->startOfDay();

        $expectedAmount = (float) (clone $invoices)->sum('amount_due');
        $collectedAmount = (float) (clone $invoices)->sum('amount_paid');

        $expectedRemaining = (float) (clone $invoices)
            ->whereDate('due_date', '>=', $today)
            ->sum(DB::raw('GREATEST(amount_due - amount_paid, 0)'));

        $overdue = (float) (clone $invoices)
            ->whereDate('due_date', '<', $today)
            ->sum(DB::raw('GREATEST(amount_due - amount_paid, 0)'));

        $lateCount = (int) (clone $invoices)
            ->whereDate('due_date', '<', $today)
            ->whereRaw('amount_due - amount_paid > 0')
            ->count();

        $lateMemberCount = (int) (clone $invoices)
            ->whereDate('due_date', '<', $today)
            ->whereRaw('amount_due - amount_paid > 0')
            ->distinct('member_id')
            ->count('member_id');

        return [
            'label' => $label,
            'button_label' => $buttonLabel,
            'school_year_label' => $schoolYearLabel,
            'expected_amount' => $expectedAmount,
            'collected_amount' => $collectedAmount,
            'expected_remaining' => $expectedRemaining,
            'overdue' => $overdue,
            'collection_rate' => $this->collectionRate($collectedAmount, $expectedAmount),
            'late_count' => $lateCount,
            'late_member_count' => $lateMemberCount,
            'is_healthy' => $lateCount === 0,
        ];
    }

    private function buildTrend(Carbon $now): array
    {
        $labels = [];
        $cashExpected = [];
        $cashCollected = [];
        $recurringExpected = [];
        $recurringCollected = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = $now->copy()->startOfMonth()->subMonths($i);
            $labels[] = $month->format('M Y');

            $monthInvoices = Invoice::membership()->forMonth($month);
            $cashExpected[] = (float) (clone $monthInvoices)->sum('amount_due');
            $cashCollected[] = (float) (clone $monthInvoices)->sum('amount_paid');

            $recurringExpected[] = $this->recurringExpectedForMonth($month);
            $recurringCollected[] = $this->recurringCollectedForMonth($month);
        }

        return [
            'labels' => $labels,
            'cash' => [
                'expected' => $cashExpected,
                'collected' => $cashCollected,
            ],
            'recurring' => [
                'expected' => $recurringExpected,
                'collected' => $recurringCollected,
            ],
        ];
    }

    private function recurringExpectedForMonth(Carbon $month): float
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $enrollments = MemberWorkshop::with('membershipPlan', 'member')
            ->whereHas('member', fn ($query) => $query->where('is_active', true))
            ->whereNotNull('membership_plan_id')
            ->whereDate('membership_start_date', '<=', $monthEnd)
            ->where(function ($query) use ($monthStart) {
                $query->whereNull('membership_end_date')
                    ->orWhereDate('membership_end_date', '>=', $monthStart);
            })
            ->get();

        $total = 0.0;

        foreach ($enrollments as $enrollment) {
            $plan = $enrollment->membershipPlan;
            if (! $plan || $this->isPerSession($plan->billing_frequency)) {
                continue;
            }

            $total += $this->monthlyEquivalent($plan);
        }

        return round($total, 2);
    }

    private function recurringCollectedForMonth(Carbon $month): float
    {
        $invoices = Invoice::membership()
            ->where('amount_paid', '>', 0)
            ->whereMonth('updated_at', $month->month)
            ->whereYear('updated_at', $month->year)
            ->with('membershipPlan')
            ->get();

        $total = 0.0;

        foreach ($invoices as $invoice) {
            $plan = $invoice->membershipPlan;
            if (! $plan || $this->isPerSession($plan->billing_frequency)) {
                continue;
            }

            $divisor = $this->billingFrequencyDivisor($plan->billing_frequency);
            $total += (float) $invoice->amount_paid / $divisor;
        }

        return round($total, 2);
    }

    public function monthlyEquivalent(MembershipPlan $plan): float
    {
        if ($this->isPerSession($plan->billing_frequency)) {
            return 0.0;
        }

        $fee = (float) ($plan->total_fee ?? $plan->fee);
        $divisor = $this->billingFrequencyDivisor($plan->billing_frequency);

        return round($fee / $divisor, 2);
    }

    public function billingFrequencyDivisor(?string $billingFrequency): int
    {
        $normalized = strtolower(trim((string) $billingFrequency));

        return match (true) {
            in_array($normalized, ['godišnje', 'yearly', 'annual'], true) => 12,
            in_array($normalized, ['polugodišnje', 'semi-annual'], true) => 6,
            default => 1,
        };
    }

    public function isPerSession(?string $billingFrequency): bool
    {
        $normalized = strtolower(trim((string) $billingFrequency));

        return in_array($normalized, ['po sastanku', 'per session'], true);
    }

    private function collectionRate(float $collected, float $expected): float
    {
        if ($expected <= 0) {
            return 0.0;
        }

        return round(($collected / $expected) * 100, 1);
    }
}

<?php

use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberWorkshop;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\Workshop;
use App\Services\DashboardFinanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('dashboard returns cash-flow finance metrics for current month', function () {
    $workshop = Workshop::factory()->create();
    $memberA = Member::factory()->create(['is_active' => true]);
    $memberB = Member::factory()->create(['is_active' => true]);

    Invoice::factory()->create([
        'member_id' => $memberA->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 100,
        'amount_paid' => 100,
        'due_date' => '2026-10-15',
        'payment_status' => 'Plaćeno',
        'invoice_type' => 'membership',
    ]);

    Invoice::factory()->create([
        'member_id' => $memberB->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 50,
        'amount_paid' => 20,
        'due_date' => '2026-10-15',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    Invoice::factory()->create([
        'member_id' => $memberA->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 40,
        'amount_paid' => 0,
        'due_date' => '2026-10-25',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('revenue.periods.current_month', fn ($period) => $period
                ->where('label', 'Listopad')
                ->where('button_label', 'Listopad')
                ->where('expected_amount', 190)
                ->where('collected_amount', 120)
                ->where('collection_rate', 63.2)
                ->where('expected_remaining', 40)
                ->where('overdue', 30)
                ->where('is_healthy', false)
                ->etc()
            )
            ->has('revenue.periods.school_year')
            ->has('revenue.trend.labels', 12)
            ->has('revenue.trend.cash.expected', 12)
            ->has('revenue.trend.cash.collected', 12)
            ->has('revenue.trend.recurring.expected', 12)
            ->has('revenue.trend.recurring.collected', 12)
        );
});

test('school year period aggregates invoices within the school year', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create(['is_active' => true]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 100,
        'amount_paid' => 80,
        'due_date' => '2026-10-15',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 200,
        'amount_paid' => 200,
        'due_date' => '2026-09-15',
        'payment_status' => 'Plaćeno',
        'invoice_type' => 'membership',
    ]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 500,
        'amount_paid' => 0,
        'due_date' => '2025-10-15',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    $schoolYear = app(DashboardFinanceService::class)->buildMetrics()['periods']['school_year'];

    expect($schoolYear['label'])->toBe('školska godina')
        ->and($schoolYear['button_label'])->toBe('Školska godina')
        ->and($schoolYear['school_year_label'])->toBe('2026-2027')
        ->and($schoolYear['expected_amount'])->toBe(300.0)
        ->and($schoolYear['collected_amount'])->toBe(280.0);
});

test('late amount counts distinct members with overdue membership invoices in period', function () {
    $workshop = Workshop::factory()->create();
    $memberA = Member::factory()->create();
    $memberB = Member::factory()->create();

    Invoice::factory()->create([
        'member_id' => $memberA->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 60,
        'amount_paid' => 0,
        'due_date' => '2026-09-15',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    Invoice::factory()->create([
        'member_id' => $memberA->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 40,
        'amount_paid' => 10,
        'due_date' => '2026-09-15',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    Invoice::factory()->create([
        'member_id' => $memberB->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 25,
        'amount_paid' => 0,
        'due_date' => '2026-09-15',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    $schoolYear = app(DashboardFinanceService::class)->buildMetrics()['periods']['school_year'];

    expect($schoolYear['overdue'])->toBe(115.0)
        ->and($schoolYear['late_member_count'])->toBe(2);
});

test('recurring collected normalizes annual membership payments to monthly equivalent', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create(['is_active' => true]);

    $annualPlan = MembershipPlan::forceCreate([
        'workshop_id' => $workshop->id,
        'plan' => 'Godišnja članarina',
        'fee' => 430,
        'total_fee' => 430,
        'billing_frequency' => 'Godišnje',
    ]);

    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $annualPlan->id,
        'amount_due' => 430,
        'amount_paid' => 430,
        'due_date' => '2026-10-15',
        'payment_status' => 'Plaćeno',
        'invoice_type' => 'membership',
    ]);

    DB::table('invoices')
        ->where('id', $invoice->id)
        ->update(['updated_at' => '2026-10-10 10:00:00']);

    $metrics = app(DashboardFinanceService::class)->buildMetrics();
    $currentMonthIndex = count($metrics['trend']['labels']) - 1;

    expect($metrics['trend']['cash']['collected'][$currentMonthIndex])->toBe(430.0)
        ->and($metrics['trend']['recurring']['collected'][$currentMonthIndex])->toBe(35.83);
});

test('recurring expected sums active memberships as monthly equivalents', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create(['is_active' => true]);

    $monthlyPlan = MembershipPlan::forceCreate([
        'workshop_id' => $workshop->id,
        'plan' => 'Mjesečna članarina',
        'fee' => 50,
        'total_fee' => 50,
        'billing_frequency' => 'Mjesečno',
    ]);

    $annualPlan = MembershipPlan::forceCreate([
        'workshop_id' => $workshop->id,
        'plan' => 'Godišnja članarina',
        'fee' => 432,
        'total_fee' => 432,
        'billing_frequency' => 'Godišnje',
    ]);

    MemberWorkshop::create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $monthlyPlan->id,
        'membership_start_date' => '2026-01-01',
        'membership_end_date' => null,
    ]);

    $memberTwo = Member::factory()->create(['is_active' => true]);
    MemberWorkshop::create([
        'member_id' => $memberTwo->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $annualPlan->id,
        'membership_start_date' => '2026-01-01',
        'membership_end_date' => null,
    ]);

    $metrics = app(DashboardFinanceService::class)->buildMetrics();
    $currentMonthIndex = count($metrics['trend']['labels']) - 1;

    expect($metrics['trend']['recurring']['expected'][$currentMonthIndex])->toBe(86.0);
});

test('membership-only scope excludes session invoices from dashboard metrics', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create();

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 100,
        'amount_paid' => 100,
        'due_date' => '2026-10-15',
        'payment_status' => 'Plaćeno',
        'invoice_type' => 'membership',
    ]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 500,
        'amount_paid' => 500,
        'due_date' => '2026-10-15',
        'payment_status' => 'Plaćeno',
        'invoice_type' => 'session',
    ]);

    $currentMonth = app(DashboardFinanceService::class)->buildMetrics()['periods']['current_month'];

    expect($currentMonth['expected_amount'])->toBe(100.0)
        ->and($currentMonth['collected_amount'])->toBe(100.0);
});

<?php

use App\Models\Invoice;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberWorkshop;
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

test('dashboard cash-flow widget totals all open invoices of any type', function () {
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

    Invoice::factory()->create([
        'member_id' => $memberA->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 60,
        'amount_paid' => 0,
        'due_date' => '2026-08-01',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'session',
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('revenue.open', fn ($open) => $open
                ->where('expected_amount', 130)
                ->where('collected_amount', 20)
                ->where('overdue', 90)
                ->where('late_member_count', 2)
                ->where('is_healthy', false)
                ->etc()
            )
            ->has('revenue.trend.labels')
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
    $octoberIndex = array_search('Oct 2026', $metrics['trend']['labels']);

    expect($octoberIndex)->not->toBeFalse()
        ->and($metrics['trend']['cash']['collected'][$octoberIndex])->toBe(430.0)
        ->and($metrics['trend']['recurring']['collected'][$octoberIndex])->toBe(35.83);
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
    $octoberIndex = array_search('Oct 2026', $metrics['trend']['labels']);

    expect($octoberIndex)->not->toBeFalse()
        ->and($metrics['trend']['recurring']['expected'][$octoberIndex])->toBe(86.0);
});

test('open cash-flow totals include session invoices', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create();

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 450,
        'amount_paid' => 0,
        'due_date' => '2026-10-25',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 50,
        'amount_paid' => 0,
        'due_date' => '2026-08-20',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'session',
    ]);

    $open = app(DashboardFinanceService::class)->buildMetrics()['open'];

    expect($open['expected_amount'])->toBe(500.0)
        ->and($open['overdue'])->toBe(50.0);
});

test('august uses the upcoming school year so september invoices appear as expected cash', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-28 12:00:00'));

    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create(['is_active' => true]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 450,
        'amount_paid' => 0,
        'due_date' => '2026-09-15',
        'payment_status' => 'Otvoreno',
        'invoice_type' => 'membership',
    ]);

    $metrics = app(DashboardFinanceService::class)->buildMetrics();
    $septemberIndex = array_search('Sep 2026', $metrics['trend']['labels']);

    expect($metrics['periods']['school_year']['school_year_label'])->toBe('2026-2027')
        ->and($metrics['periods']['school_year']['expected_amount'])->toBe(450.0)
        ->and($metrics['periods']['current_month']['expected_amount'])->toBe(0.0)
        ->and($septemberIndex)->not->toBeFalse()
        ->and($metrics['trend']['cash']['expected'][$septemberIndex])->toBe(450.0);
});

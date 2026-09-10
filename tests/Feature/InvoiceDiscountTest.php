<?php

use App\Models\Invoice;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberWorkshop;
use App\Models\User;
use App\Models\Workshop;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function createDiscountedPlan(Workshop $workshop, float $totalFee = 360, float $percent = 20): MembershipPlan
{
    return MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Godišnja članarina ('.$percent.'% OFF)',
        'fee' => 450,
        'billing_frequency' => 'godišnje',
        'discount_type' => $percent.'% OFF',
        'total_fee' => $totalFee,
    ]);
}

function createRegularPlan(Workshop $workshop): MembershipPlan
{
    return MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Godišnja članarina',
        'fee' => 450,
        'billing_frequency' => 'godišnje',
        'discount_type' => null,
        'total_fee' => 450,
    ]);
}

test('applying a discount on an invoice stores the original amount and reduces amount due', function () {
    $invoice = Invoice::factory()->create([
        'amount_due' => 100,
        'payment_status' => 'Otvoreno',
    ]);

    $this->patch(route('invoices.update', $invoice), [
        'discount_percent' => 20,
        'amount_due' => 80,
        'stay_on_page' => true,
    ])->assertRedirect();

    $invoice->refresh();

    expect((float) $invoice->discount_percent)->toBe(20.0)
        ->and((float) $invoice->original_amount)->toBe(100.0)
        ->and((float) $invoice->amount_due)->toBe(80.0)
        ->and($invoice->has_discount)->toBeTrue()
        ->and($invoice->discount_label)->toBe('20% OFF');
});

test('clearing a discount restores the original amount', function () {
    $invoice = Invoice::factory()->create([
        'amount_due' => 80,
        'discount_percent' => 20,
        'original_amount' => 100,
        'payment_status' => 'Otvoreno',
    ]);

    $this->patch(route('invoices.update', $invoice), [
        'discount_percent' => 0,
        'amount_due' => 100,
        'stay_on_page' => true,
    ])->assertRedirect();

    $invoice->refresh();

    expect($invoice->discount_percent)->toBeNull()
        ->and($invoice->original_amount)->toBeNull()
        ->and((float) $invoice->amount_due)->toBe(100.0)
        ->and($invoice->has_discount)->toBeFalse();
});

test('invoices index can filter by discounted membership plan', function () {
    $workshop = Workshop::factory()->create();
    $discountedPlan = createDiscountedPlan($workshop);
    $regularPlan = createRegularPlan($workshop);

    $discountedInvoice = Invoice::factory()->create([
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $discountedPlan->id,
        'amount_due' => 360,
        'discount_percent' => 20,
        'original_amount' => 450,
        'due_date' => '2026-09-15',
        'reference_code' => '202609-001-001',
    ]);

    Invoice::factory()->create([
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $regularPlan->id,
        'amount_due' => 450,
        'due_date' => '2026-09-15',
        'reference_code' => '202609-002-001',
    ]);

    $this->get(route('invoices.index', [
        'membership_plan_id' => $discountedPlan->id,
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $discountedInvoice->id)
            ->where('membershipPlanId', (string) $discountedPlan->id)
            ->has('membershipPlans')
        );
});

test('invoices index can filter invoices that have discounts', function () {
    $workshop = Workshop::factory()->create();
    $discountedPlan = createDiscountedPlan($workshop);
    $regularPlan = createRegularPlan($workshop);

    $fromPlan = Invoice::factory()->create([
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $discountedPlan->id,
        'amount_due' => 360,
        'discount_percent' => 20,
        'original_amount' => 450,
        'due_date' => '2026-09-15',
        'reference_code' => '202609-011-001',
    ]);

    $manualDiscount = Invoice::factory()->create([
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $regularPlan->id,
        'amount_due' => 80,
        'discount_percent' => 20,
        'original_amount' => 100,
        'due_date' => '2026-09-15',
        'reference_code' => '202609-012-001',
    ]);

    Invoice::factory()->create([
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $regularPlan->id,
        'amount_due' => 450,
        'due_date' => '2026-09-15',
        'reference_code' => '202609-013-001',
    ]);

    $this->get(route('invoices.index', ['has_discount' => '1']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 2)
            ->where('hasDiscount', '1')
            ->where('invoices.data', function ($data) use ($fromPlan, $manualDiscount) {
                $ids = collect($data)->pluck('id')->sort()->values()->all();

                return $ids === collect([$fromPlan->id, $manualDiscount->id])->sort()->values()->all();
            })
        );
});

test('invoices index can filter invoices without discounts', function () {
    $workshop = Workshop::factory()->create();
    $regularPlan = createRegularPlan($workshop);

    $open = Invoice::factory()->create([
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $regularPlan->id,
        'amount_due' => 450,
        'due_date' => '2026-09-15',
        'reference_code' => '202609-021-001',
    ]);

    Invoice::factory()->create([
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $regularPlan->id,
        'amount_due' => 80,
        'discount_percent' => 20,
        'original_amount' => 100,
        'due_date' => '2026-09-15',
        'reference_code' => '202609-022-001',
    ]);

    $this->get(route('invoices.index', ['has_discount' => '0']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $open->id)
        );
});

test('generated invoices inherit discount snapshot from the membership plan', function () {
    $workshop = Workshop::factory()->create(['type' => 'Group']);
    $member = Member::factory()->create(['is_active' => true]);
    $plan = createDiscountedPlan($workshop);

    $memberWorkshop = MemberWorkshop::create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $plan->id,
        'membership_start_date' => '2026-09-01',
    ]);
    $memberWorkshop->setRelations([
        'member' => $member,
        'workshop' => $workshop,
        'membershipPlan' => $plan,
    ]);

    $invoice = app(InvoiceGenerationService::class)
        ->createInvoice($memberWorkshop, Carbon::parse('2026-09-01'));

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->amount_due)->toBe(360.0)
        ->and((float) $invoice->discount_percent)->toBe(20.0)
        ->and((float) $invoice->original_amount)->toBe(450.0)
        ->and($invoice->notes)->toBe('Godišnja članarina 2026/2027');
});

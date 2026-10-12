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

function monthlyPlan(Workshop $workshop, float $totalFee = 50): MembershipPlan
{
    return MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Mjesečna članarina',
        'fee' => $totalFee,
        'billing_frequency' => 'Mjesečno',
        'discount_type' => null,
        'total_fee' => $totalFee,
    ]);
}

test('creating a 50 percent discount stores a 25 eur monthly plan', function () {
    $workshop = Workshop::factory()->create(['name' => 'Dramska radionica']);
    $monthly = monthlyPlan($workshop);

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), [
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $monthly->id,
            'percent' => 50,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $discount = MembershipPlan::query()
        ->where('workshop_id', $workshop->id)
        ->where('plan', 'Mjesečna članarina (50% OFF)')
        ->first();

    expect($discount)->not->toBeNull()
        ->and($discount->discount_type)->toBe('50% OFF')
        ->and($discount->billing_frequency)->toBe('Mjesečno')
        ->and((float) $discount->fee)->toBe(50.0)
        ->and((float) $discount->total_fee)->toBe(25.0);

    $this->get(route('workshop-discounts.index', ['workshop_id' => $workshop->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workshops/Discounts')
            ->has('discounts', 1)
            ->where('discounts.0.plan', 'Mjesečna članarina (50% OFF)')
            ->where('discounts.0.percent', 50)
            ->where('discounts.0.total_fee', 25)
            ->where('discounts.0.base_amount', 50)
        );
});

test('the same percent cannot be created twice for one plan', function () {
    $workshop = Workshop::factory()->create();
    $monthly = monthlyPlan($workshop);

    $payload = [
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $monthly->id,
        'percent' => 50,
    ];

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), $payload)
        ->assertSessionHasNoErrors();

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), $payload)
        ->assertSessionHasErrors('percent');

    expect(MembershipPlan::query()->where('workshop_id', $workshop->id)->count())->toBe(2);
});

test('a discount cannot be based on another discount', function () {
    $workshop = Workshop::factory()->create();
    $monthly = monthlyPlan($workshop);

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), [
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $monthly->id,
            'percent' => 20,
        ])
        ->assertSessionHasNoErrors();

    $discount = MembershipPlan::query()
        ->where('plan', 'Mjesečna članarina (20% OFF)')
        ->first();

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), [
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $discount->id,
            'percent' => 50,
        ])
        ->assertSessionHasErrors('membership_plan_id');

    expect(MembershipPlan::query()->where('plan', 'like', '%50% OFF%')->exists())->toBeFalse();
});

test('delete is blocked while a member is enrolled on the discount', function () {
    $workshop = Workshop::factory()->create();
    $monthly = monthlyPlan($workshop);
    $member = Member::factory()->create();

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), [
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $monthly->id,
            'percent' => 50,
        ]);

    $discount = MembershipPlan::query()->where('plan', 'Mjesečna članarina (50% OFF)')->first();

    MemberWorkshop::create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $discount->id,
        'membership_start_date' => '2026-09-01',
    ]);

    $this->from(route('workshop-discounts.index'))
        ->delete(route('workshop-discounts.destroy', $discount))
        ->assertRedirect()
        ->assertSessionHasErrors('plan');

    $this->assertDatabaseHas('membership_plans', ['id' => $discount->id]);
    $this->assertDatabaseHas('member_workshop', [
        'member_id' => $member->id,
        'membership_plan_id' => $discount->id,
    ]);
});

test('delete is blocked while an invoice still references the discount', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create();
    $monthly = monthlyPlan($workshop);

    $discount = MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Mjesečna članarina (50% OFF)',
        'fee' => 50,
        'billing_frequency' => 'Mjesečno',
        'discount_type' => '50% OFF',
        'total_fee' => 25,
    ]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $discount->id,
        'amount_due' => 25,
    ]);

    $this->from(route('workshop-discounts.index'))
        ->delete(route('workshop-discounts.destroy', $discount))
        ->assertSessionHasErrors('plan');

    $this->assertDatabaseHas('membership_plans', ['id' => $discount->id]);
    expect($monthly->exists)->toBeTrue();
});

test('an unused discount can be deleted', function () {
    $workshop = Workshop::factory()->create();
    $monthly = monthlyPlan($workshop);

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), [
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $monthly->id,
            'percent' => 20,
        ]);

    $discount = MembershipPlan::query()->where('plan', 'Mjesečna članarina (20% OFF)')->first();

    $this->from(route('workshop-discounts.index'))
        ->delete(route('workshop-discounts.destroy', $discount))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseMissing('membership_plans', ['id' => $discount->id]);
    $this->assertDatabaseHas('membership_plans', ['id' => $monthly->id]);
});

test('member plan dropdown lists the new discount after its base plan', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create();
    $yearly = MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Godišnja članarina',
        'fee' => 500,
        'billing_frequency' => 'Godišnje',
        'discount_type' => 'Godišnji popust',
        'total_fee' => 450,
    ]);
    $monthly = monthlyPlan($workshop);

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), [
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $monthly->id,
            'percent' => 50,
        ])
        ->assertSessionHasNoErrors();

    $discount = MembershipPlan::query()->where('plan', 'Mjesečna članarina (50% OFF)')->first();

    $this->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where("membershipPlans.{$workshop->id}.0.id", $yearly->id)
            ->where("membershipPlans.{$workshop->id}.1.id", $monthly->id)
            ->where("membershipPlans.{$workshop->id}.2.id", $discount->id)
        );
});

test('future slip generation charges 25 eur after the member is on the 50 percent plan', function () {
    $workshop = Workshop::factory()->create(['type' => 'Group']);
    $member = Member::factory()->create(['is_active' => true]);
    $monthly = monthlyPlan($workshop);

    $this->from(route('workshop-discounts.index'))
        ->post(route('workshop-discounts.store'), [
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $monthly->id,
            'percent' => 50,
        ]);

    $discount = MembershipPlan::query()->where('plan', 'Mjesečna članarina (50% OFF)')->first();

    $memberWorkshop = MemberWorkshop::create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $discount->id,
        'membership_start_date' => '2026-09-01',
    ]);
    $memberWorkshop->setRelations([
        'member' => $member,
        'workshop' => $workshop,
        'membershipPlan' => $discount,
    ]);

    $invoice = app(InvoiceGenerationService::class)
        ->createInvoice($memberWorkshop, Carbon::parse('2026-10-01'));

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->amount_due)->toBe(25.0)
        ->and((float) $invoice->discount_percent)->toBe(50.0)
        ->and((float) $invoice->original_amount)->toBe(50.0);
});

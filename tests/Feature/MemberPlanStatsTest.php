<?php

use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberWorkshop;
use App\Models\User;
use App\Models\Workshop;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('member plan counts group discount variants into the base frequency', function () {
    $drama = Workshop::factory()->create();
    $seniors = Workshop::factory()->create();

    $yearly = MembershipPlan::create([
        'workshop_id' => $drama->id,
        'plan' => 'Godišnja članarina',
        'fee' => 450,
        'billing_frequency' => 'Godišnje',
        'discount_type' => null,
        'total_fee' => 450,
    ]);
    $yearlyOff = MembershipPlan::create([
        'workshop_id' => $drama->id,
        'plan' => 'Godišnja članarina (20% OFF)',
        'fee' => 450,
        'billing_frequency' => 'Godišnje',
        'discount_type' => '20% OFF',
        'total_fee' => 360,
    ]);
    $monthly = MembershipPlan::create([
        'workshop_id' => $drama->id,
        'plan' => 'Mjesečna članarina',
        'fee' => 50,
        'billing_frequency' => 'Mjesečno',
        'discount_type' => null,
        'total_fee' => 50,
    ]);
    $seniorsMonthly = MembershipPlan::create([
        'workshop_id' => $seniors->id,
        'plan' => 'Mjesečna članarina',
        'fee' => 35,
        'billing_frequency' => 'Mjesečno',
        'discount_type' => null,
        'total_fee' => 35,
    ]);

    $discounted = Member::factory()->create();
    MemberWorkshop::create([
        'member_id' => $discounted->id,
        'workshop_id' => $drama->id,
        'membership_plan_id' => $yearlyOff->id,
        'membership_start_date' => '2026-09-01',
    ]);

    $monthlyOnly = Member::factory()->create();
    MemberWorkshop::create([
        'member_id' => $monthlyOnly->id,
        'workshop_id' => $drama->id,
        'membership_plan_id' => $monthly->id,
        'membership_start_date' => '2026-09-01',
    ]);

    $both = Member::factory()->create();
    MemberWorkshop::create([
        'member_id' => $both->id,
        'workshop_id' => $drama->id,
        'membership_plan_id' => $yearly->id,
        'membership_start_date' => '2026-09-01',
    ]);
    MemberWorkshop::create([
        'member_id' => $both->id,
        'workshop_id' => $seniors->id,
        'membership_plan_id' => $seniorsMonthly->id,
        'membership_start_date' => '2026-09-01',
    ]);

    $this->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Members/Index')
            ->where('planStats.total', 3)
            ->where('planStats.frequencies', function ($frequencies) {
                $keys = $frequencies->pluck('key')->all();

                return $keys === ['monthly', 'yearly']
                    && $frequencies->firstWhere('key', 'monthly')['count'] === 2
                    && $frequencies->firstWhere('key', 'yearly')['count'] === 2
                    && $frequencies->pluck('label')->doesntContain(fn ($label) => str_contains((string) $label, 'OFF'));
            })
            ->missing('membershipPlans')
        );

    $this->get(route('members.index', ['billing_frequency' => 'yearly']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billingFrequency', 'yearly')
            ->where('members.pagination.total', 2)
            ->where('planStats.total', 3)
            ->where('members.data', function ($members) use ($discounted, $both, $monthlyOnly) {
                $ids = $members->pluck('id')->all();

                return count($ids) === 2
                    && in_array($discounted->id, $ids, true)
                    && in_array($both->id, $ids, true)
                    && ! in_array($monthlyOnly->id, $ids, true);
            })
        );

    $this->get(route('members.index', ['filter' => 'nema-ovog-clana']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.pagination.total', 0)
            ->where('planStats.total', 3)
        );
});

<?php

use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupWorkshop;
use App\Models\MemberWorkshop;
use App\Models\User;
use App\Models\Workshop;
use App\Services\DashboardFinanceService;
use App\Services\PaymentSlipEmailService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('test members and test groups stay out of finance totals and reminders', function () {
    $workshop = Workshop::factory()->create();
    $real = Member::factory()->create(['is_active' => true, 'is_test' => false]);
    $testMember = Member::factory()->create(['is_active' => true, 'is_test' => true]);

    Invoice::factory()->create([
        'member_id' => $real->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 100,
        'amount_paid' => 40,
        'due_date' => '2026-10-01',
        'payment_status' => 'Otvoreno',
    ]);

    Invoice::factory()->create([
        'member_id' => $testMember->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 80,
        'amount_paid' => 80,
        'due_date' => '2026-10-01',
        'payment_status' => 'Otvoreno',
    ]);

    expect((float) Invoice::query()->exceptTest()->sum('amount_due'))->toBe(100.0)
        ->and(app(PaymentSlipEmailService::class)->suggestedReminderCount())->toBe(1);
});

test('a test group excludes that workshop even when the member is not marked test', function () {
    $workshop = Workshop::factory()->create();
    $group = MemberGroup::query()->create(['name' => 'Proba', 'is_test' => true]);
    $member = Member::factory()->create(['is_active' => true, 'is_test' => false]);

    MemberGroupWorkshop::query()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'member_group_id' => $group->id,
    ]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 50,
        'due_date' => '2026-10-01',
        'payment_status' => 'Otvoreno',
    ]);

    expect((float) Invoice::query()->exceptTest()->sum('amount_due'))->toBe(0.0)
        ->and(app(PaymentSlipEmailService::class)->suggestedReminderCount())->toBe(0);
});

test('a test member still counts for a workshop assigned to a real group', function () {
    $realWorkshop = Workshop::factory()->create();
    $testWorkshop = Workshop::factory()->create();
    $realGroup = MemberGroup::query()->create(['name' => 'Prava', 'is_test' => false]);
    $testGroup = MemberGroup::query()->create(['name' => 'Proba', 'is_test' => true]);
    $member = Member::factory()->create(['is_active' => true, 'is_test' => true]);

    MemberGroupWorkshop::query()->create([
        'member_id' => $member->id,
        'workshop_id' => $realWorkshop->id,
        'member_group_id' => $realGroup->id,
    ]);
    MemberGroupWorkshop::query()->create([
        'member_id' => $member->id,
        'workshop_id' => $testWorkshop->id,
        'member_group_id' => $testGroup->id,
    ]);

    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $realWorkshop->id,
        'amount_due' => 30,
        'due_date' => '2026-10-01',
        'payment_status' => 'Otvoreno',
    ]);
    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $testWorkshop->id,
        'amount_due' => 70,
        'due_date' => '2026-10-01',
        'payment_status' => 'Otvoreno',
    ]);

    MemberWorkshop::query()->create([
        'member_id' => $member->id,
        'workshop_id' => $realWorkshop->id,
        'membership_start_date' => '2026-09-01',
    ]);
    MemberWorkshop::query()->create([
        'member_id' => $member->id,
        'workshop_id' => $testWorkshop->id,
        'membership_start_date' => '2026-09-01',
    ]);

    expect((float) Invoice::query()->exceptTest()->sum('amount_due'))->toBe(30.0)
        ->and(app(PaymentSlipEmailService::class)->suggestedReminderCount())->toBe(1)
        ->and(MemberWorkshop::query()->exceptTest()->count())->toBe(1);
});

test('staff can mark a member and a group as test', function () {
    $member = Member::factory()->create([
        'is_active' => true,
        'is_test' => false,
        'date_of_birth' => '2014-04-01',
        'phone_number' => '0910000000',
    ]);

    $this->put(route('members.update', $member), [
        'first_name' => $member->first_name,
        'last_name' => $member->last_name,
        'date_of_birth' => '2014-04-01',
        'phone_number' => '0910000000',
        'email' => $member->email,
        'is_active' => true,
        'is_test' => true,
    ])->assertRedirect();

    expect($member->fresh()->is_test)->toBeTrue();

    $group = MemberGroup::query()->create(['name' => 'Proba', 'description' => 'Proba']);

    $this->put(route('member-groups.update', $group), [
        'name' => 'Proba',
        'description' => 'Proba',
        'is_test' => true,
    ])->assertRedirect();

    expect($group->fresh()->is_test)->toBeTrue();
});

test('the dashboard hides test groups', function () {
    $this->mock(DashboardFinanceService::class, function ($mock) {
        $mock->shouldReceive('buildMetrics')->andReturn([
            'months' => [],
            'current_month_key' => '',
        ]);
    });

    MemberGroup::query()->create(['name' => 'Prava', 'is_test' => false]);
    MemberGroup::query()->create(['name' => 'Proba', 'is_test' => true]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->where('groups.0.name', 'Prava')
        );
});

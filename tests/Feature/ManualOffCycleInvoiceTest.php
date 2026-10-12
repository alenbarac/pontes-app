<?php

use App\Models\Invoice;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberWorkshop;
use App\Models\User;
use App\Models\Workshop;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->workshop = Workshop::factory()->create();
    $this->plan = MembershipPlan::create([
        'workshop_id' => $this->workshop->id,
        'plan' => '6 mjeseci',
        'fee' => 270,
        'billing_frequency' => 'Polugodišnje',
        'total_fee' => 270,
    ]);
    $this->member = Member::factory()->create(['is_active' => true]);
    $this->enrollment = MemberWorkshop::create([
        'member_id' => $this->member->id,
        'workshop_id' => $this->workshop->id,
        'membership_plan_id' => $this->plan->id,
        'membership_start_date' => '2026-04-01',
        'membership_end_date' => '2026-09-30',
    ]);
});

test('manual generation creates a slip for a non-billing month on a six-month plan', function () {
    $this->postJson(route('members.invoices.generate', $this->member), [
        'workshop_id' => $this->workshop->id,
        'target_month' => '2026-05',
    ])->assertOk()
        ->assertJsonPath('success', true);

    $invoice = Invoice::where('member_id', $this->member->id)->first();

    expect($invoice)->not->toBeNull()
        ->and(Carbon::parse($invoice->due_date)->toDateString())->toBe('2026-05-15')
        ->and((float) $invoice->amount_due)->toBe(270.0);
});

test('bulk eligibility still rejects a non-billing month on a six-month plan', function () {
    $enrollment = $this->enrollment->load(['member', 'membershipPlan']);

    expect(app(InvoiceGenerationService::class)->shouldGenerateInvoice(
        $enrollment,
        Carbon::parse('2026-05-01'),
    ))->toBeFalse();
});

test('october bulk generation follows each membership plan', function () {
    $workshop = Workshop::factory()->create(['type' => 'Group']);

    $sixMonth = MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => '6 mjeseci',
        'fee' => 270,
        'billing_frequency' => 'Polugodišnje',
        'total_fee' => 270,
    ]);
    $yearly = MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Godišnja članarina',
        'fee' => 500,
        'billing_frequency' => 'Godišnje',
        'total_fee' => 500,
    ]);
    $monthly = MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Mjesečna članarina',
        'fee' => 50,
        'billing_frequency' => 'Mjesečno',
        'total_fee' => 50,
    ]);

    $sixMonthMember = Member::factory()->create(['is_active' => true]);
    $yearlyMember = Member::factory()->create(['is_active' => true]);
    $monthlyMember = Member::factory()->create(['is_active' => true]);

    foreach ([
        [$sixMonthMember, $sixMonth],
        [$yearlyMember, $yearly],
        [$monthlyMember, $monthly],
    ] as [$member, $plan]) {
        MemberWorkshop::create([
            'member_id' => $member->id,
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $plan->id,
            'membership_start_date' => '2026-09-01',
            'membership_end_date' => '2027-08-31',
        ]);
    }

    $result = app(InvoiceGenerationService::class)->generateInvoicesForMonth(
        Carbon::parse('2026-10-01'),
        [$sixMonthMember->id, $yearlyMember->id, $monthlyMember->id],
    );

    expect($result['generated'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and(Invoice::where('due_date', '2026-10-15')->pluck('member_id')->all())
        ->toBe([$monthlyMember->id]);
});

test('a second slip in the same month is rejected', function () {
    $payload = [
        'workshop_id' => $this->workshop->id,
        'target_month' => '2026-05',
    ];

    $this->postJson(route('members.invoices.generate', $this->member), $payload)->assertOk();

    $this->postJson(route('members.invoices.generate', $this->member), $payload)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invoice already exists for this month.');

    expect(Invoice::where('member_id', $this->member->id)->count())->toBe(1);
});

test('a month outside the membership is rejected', function () {
    $this->postJson(route('members.invoices.generate', $this->member), [
        'workshop_id' => $this->workshop->id,
        'target_month' => '2026-10',
    ])->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(Invoice::where('member_id', $this->member->id)->count())->toBe(0);
});

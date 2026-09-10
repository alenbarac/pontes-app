<?php

use App\Models\Invoice;
use App\Models\MembershipPlan;
use Carbon\Carbon;

test('yearly and 20% off plans share the godišnja school-year note', function () {
    $yearly = new MembershipPlan([
        'plan' => 'Godišnja članarina',
        'billing_frequency' => 'Godišnje',
    ]);
    $discounted = new MembershipPlan([
        'plan' => 'Godišnja članarina (20% OFF)',
        'billing_frequency' => 'Godišnje',
        'discount_type' => '20% OFF',
    ]);
    $due = Carbon::parse('2026-09-15');

    expect(Invoice::defaultMembershipNotes($yearly, $due))->toBe('Godišnja članarina 2026/2027')
        ->and(Invoice::defaultMembershipNotes($discounted, $due))->toBe('Godišnja članarina 2026/2027');
});

test('six-month plans include school year and semester number', function () {
    $plan = new MembershipPlan([
        'plan' => '6 mjeseci',
        'billing_frequency' => 'Polugodišnje',
    ]);

    expect(Invoice::defaultMembershipNotes($plan, '2026-09-15'))
        ->toBe('Članarina 6 mjeseci - 2026/2027 - 1/2')
        ->and(Invoice::defaultMembershipNotes($plan, '2027-03-15'))
        ->toBe('Članarina 6 mjeseci - 2026/2027 - 2/2');
});

test('monthly plans keep the month-year note', function () {
    $plan = new MembershipPlan([
        'plan' => 'Mjesečna članarina (20% OFF)',
        'billing_frequency' => 'Mjesečno',
        'discount_type' => '20% OFF',
    ]);

    expect(Invoice::defaultMembershipNotes($plan, '2026-09-15'))
        ->toBe('Članarina za 09/2026');
});

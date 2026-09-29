<?php

use App\Models\Invoice;
use App\Models\Member;

test('reference code starts at 001 when the prefix is unused', function () {
    expect(Invoice::generateReferenceCode(65, '2026-09-15'))->toBe('202609-065-001');
});

test('reference code increments for another invoice of the same member and month', function () {
    $member = Member::factory()->create();
    $prefix = '202609-'.str_pad((string) $member->id, 3, '0', STR_PAD_LEFT).'-';

    Invoice::factory()->create([
        'member_id' => $member->id,
        'due_date' => '2026-09-15',
        'reference_code' => $prefix.'001',
    ]);

    expect(Invoice::generateReferenceCode($member->id, '2026-09-15'))->toBe($prefix.'002');
});

test('reference code skips a code that exists for a different member or due month', function () {
    $other = Member::factory()->create();

    Invoice::factory()->create([
        'member_id' => $other->id,
        'due_date' => '2026-08-15',
        'reference_code' => '202609-065-001',
    ]);

    expect(Invoice::generateReferenceCode(65, '2026-09-15'))->toBe('202609-065-002');
});

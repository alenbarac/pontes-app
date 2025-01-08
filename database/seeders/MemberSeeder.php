<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Membership;
use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create 50 members using the factory
        $members = Member::factory()->count(50)->create();

        $membershipOptions = [
            [
                'plan' => 'Monthly Plan',
                'fee' => 50.00,
                'billing_frequency' => 'Monthly',
                'discount_type' => null,
                'total_fee' => 50.00,
                'duration' => ['start' => Carbon::now()->subMonth(), 'end' => Carbon::now()->addMonths(11)],
            ],
            [
                'plan' => '6-Month Plan',
                'fee' => 270.00,
                'billing_frequency' => 'Semi-Annual',
                'discount_type' => 'Bulk Discount',
                'total_fee' => 270.00,
                'duration' => ['start' => Carbon::now()->subMonths(6), 'end' => Carbon::now()->addMonths(6)],
            ],
            [
                'plan' => 'Annual Plan',
                'fee' => 500.00,
                'billing_frequency' => 'Yearly',
                'discount_type' => 'Early Bird',
                'total_fee' => 450.00,
                'duration' => ['start' => Carbon::now()->startOfYear(), 'end' => Carbon::now()->endOfYear()],
            ],
        ];

        foreach ($members as $member) {
            // Assign 1-2 random groups
            $groups = MemberGroup::inRandomOrder()->take(random_int(1, 2))->pluck('id');
            $member->groups()->attach($groups);

            // Assign 1-2 random workshops
            $workshops = Workshop::inRandomOrder()->take(random_int(1, 2))->pluck('id');
            $member->workshops()->attach($workshops);

            // Assign one random membership
            $membership = Arr::random($membershipOptions);

            Membership::create([
                'member_id' => $member->id,
                'plan' => $membership['plan'],
                'fee' => $membership['fee'],
                'billing_frequency' => $membership['billing_frequency'],
                'discount_type' => $membership['discount_type'],
                'total_fee' => $membership['total_fee'],
                'start_date' => $membership['duration']['start'],
                'end_date' => $membership['duration']['end'],
                'status' => 'Active',
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Workshop;
use App\Models\MembershipPlan;
use Carbon\Carbon;

class MembershipPlanSeeder extends Seeder
{
    public function run()
    {
        $workshops = Workshop::all();

        // Ensure there are workshops before seeding membership plans
        if ($workshops->isEmpty()) {
            $this->command->warn('Skipping membership_plans seeding: No workshops found.');
            return;
        }

        $membershipOptions = [
            [
                'plan' => 'Mjesečna članarina',
                'fee' => 50.00,
                'billing_frequency' => 'Mjesečno',
                'discount_type' => null,
                'total_fee' => 50.00,
                'start_date' => Carbon::now()->subMonth(),
                'end_date' => Carbon::now()->addMonths(11),
            ],
            [
                'plan' => '6 mjeseci',
                'fee' => 270.00,
                'billing_frequency' => 'Polugodišnje',
                'discount_type' => 'Polugođišnji popust',
                'total_fee' => 270.00,
                'start_date' => Carbon::now()->subMonths(6),
                'end_date' => Carbon::now()->addMonths(6),
            ],
            [
                'plan' => 'Godišnja članarina',
                'fee' => 500.00,
                'billing_frequency' => 'Godišnje',
                'discount_type' => 'Godišnji popust',
                'total_fee' => 450.00,
                'start_date' => Carbon::now()->startOfYear(),
                'end_date' => Carbon::now()->endOfYear(),
            ],
        ];

        foreach ($workshops as $workshop) {
            foreach ($membershipOptions as $option) {
                MembershipPlan::create([
                    'workshop_id' => $workshop->id,
                    'plan' => $option['plan'],
                    'fee' => $option['fee'],
                    'billing_frequency' => $option['billing_frequency'],
                    'discount_type' => $option['discount_type'],
                    'total_fee' => $option['total_fee'],
                    'start_date' => $option['start_date'],
                    'end_date' => $option['end_date'],
                ]);
            }
        }

        $this->command->info('Membership plans seeded successfully!');
    }
}

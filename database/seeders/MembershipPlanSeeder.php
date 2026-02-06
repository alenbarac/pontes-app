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

        // Group workshop plans (Dramska radionica)
        $groupWorkshopPlans = [
            [
                'plan' => 'Mjesečna članarina',
                'fee' => 50.00,
                'billing_frequency' => 'Mjesečno',
                'discount_type' => null,
                'total_fee' => 50.00,
            ],
            [
                'plan' => '6 mjeseci',
                'fee' => 270.00,
                'billing_frequency' => 'Polugodišnje',
                'discount_type' => 'Polugođišnji popust',
                'total_fee' => 270.00,
            ],
            [
                'plan' => 'Godišnja članarina',
                'fee' => 500.00,
                'billing_frequency' => 'Godišnje',
                'discount_type' => 'Godišnji popust',
                'total_fee' => 450.00,
            ],
        ];

        // Individual counseling plan (Individualno savjetovanje)
        $individualCounselingPlan = [
            'plan' => 'Po sastanku',
            'fee' => 50.00, // Base fee, actual amount determined per session (50 or 60 EUR)
            'billing_frequency' => 'Po sastanku',
            'discount_type' => null,
            'total_fee' => 50.00,
        ];

        foreach ($workshops as $workshop) {
            // Check if this is individual counseling workshop
            if (strtolower($workshop->type) === 'individualno' || 
                str_contains(strtolower($workshop->name), 'individualno')) {
                // Create single per-session plan for individual counseling
                MembershipPlan::create([
                    'workshop_id' => $workshop->id,
                    'plan' => $individualCounselingPlan['plan'],
                    'fee' => $individualCounselingPlan['fee'],
                    'billing_frequency' => $individualCounselingPlan['billing_frequency'],
                    'discount_type' => $individualCounselingPlan['discount_type'],
                    'total_fee' => $individualCounselingPlan['total_fee'],
                ]);
            } else {
                // Create standard plans for group workshops
                foreach ($groupWorkshopPlans as $option) {
                    MembershipPlan::create([
                        'workshop_id' => $workshop->id,
                        'plan' => $option['plan'],
                        'fee' => $option['fee'],
                        'billing_frequency' => $option['billing_frequency'],
                        'discount_type' => $option['discount_type'],
                        'total_fee' => $option['total_fee'],
                    ]);
                }
            }
        }

        $this->command->info('Membership plans seeded successfully!');
    }
}

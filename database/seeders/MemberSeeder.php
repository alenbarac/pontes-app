<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Member;
use App\Models\Workshop;
use App\Models\MemberGroup;
use App\Models\MembershipPlan;
use App\Models\MemberWorkshop;
use App\Models\MemberGroupWorkshop;
use Carbon\Carbon;
class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Note: This seeder is kept for reference but is not used in production.
     * Members are imported via CSV import functionality.
     */
    public function run()
    {
        // Seeder disabled - use CSV import for members instead
        // This seeder is kept for reference purposes only
        return;
        
        // Original Faker-based seeding code removed
        // Members should be imported via the CSV import feature
    }

    private function assignWorkshopAndMembership($member, $workshop, $groups, $membershipPlans)
    {
        // Fetch existing membership plan for the workshop
        $availablePlans = $membershipPlans[$workshop->id] ?? collect();
        $selectedPlan = $availablePlans->isNotEmpty() ? $availablePlans->random() : null;

        if ($selectedPlan) {
            // Attach the member to the workshop with the selected membership plan
            MemberWorkshop::create([
                'member_id' => $member->id,
                'workshop_id' => $workshop->id,
                'membership_plan_id' => $selectedPlan->id,
            ]);
        }

        // Assign a single group per workshop
        $group = $groups->random();
        MemberGroupWorkshop::create([
            'member_id' => $member->id,
            'workshop_id' => $workshop->id,
            'member_group_id' => $group->id,
        ]);
    }
}

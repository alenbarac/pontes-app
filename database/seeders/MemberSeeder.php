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
use Faker\Factory as Faker;

class MemberSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        $workshops = Workshop::all();
        $groups = MemberGroup::all();

        // Ensure membership plans exist per workshop
        $membershipPlans = MembershipPlan::all()->groupBy('workshop_id');

        for ($i = 0; $i < 50; $i++) {
            $member = Member::create([
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'date_of_birth' => $faker->date(),
                'phone_number' => $faker->phoneNumber,
                'email' => $faker->unique()->safeEmail,
                'is_active' => $faker->boolean(90),
                'parent_contact' => $faker->boolean(70) ? $faker->phoneNumber : null,
                'parent_email' => $faker->boolean(70) ? $faker->email : null,
            ]);

            // 80% of members join only one workshop
            if ($faker->boolean(80)) {
                $selectedWorkshop = $workshops->random();
                $this->assignWorkshopAndMembership($member, $selectedWorkshop, $groups, $membershipPlans);
            } else {
                // 20% join multiple workshops
                $selectedWorkshops = $workshops->count() > 1 ? $workshops->random(min($workshops->count(), rand(2, 3))) : collect([$workshops->first()]);

                foreach ($selectedWorkshops as $selectedWorkshop) {
                    $this->assignWorkshopAndMembership($member, $selectedWorkshop, $groups, $membershipPlans);
                }
            }
        }
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

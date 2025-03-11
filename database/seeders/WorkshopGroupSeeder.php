<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Workshop;
use App\Models\MemberGroup;
use Illuminate\Support\Facades\DB;

class WorkshopGroupSeeder extends Seeder
{
    public function run()
    {
        $workshops = Workshop::all();
        $groups = MemberGroup::all();

        // Ensure both workshops and groups exist
        if ($workshops->isEmpty() || $groups->isEmpty()) {
            $this->command->warn('Skipping workshop_groups seeding: No workshops or groups found.');
            return;
        }

        // ✅ Ensure every group is assigned to at least one workshop
        foreach ($groups as $group) {
            $randomWorkshop = $workshops->random();
            DB::table('workshop_groups')->insert([
                'workshop_id' => $randomWorkshop->id,
                'member_group_id' => $group->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ✅ Also, randomly assign each workshop multiple groups
        foreach ($workshops as $workshop) {
            $randomGroups = $groups->random(rand(2, 4));

            foreach ($randomGroups as $group) {
                DB::table('workshop_groups')->updateOrInsert([
                    'workshop_id' => $workshop->id,
                    'member_group_id' => $group->id,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Workshop groups seeded successfully!');
    }
}

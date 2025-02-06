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

        // Assign each workshop a few random groups
        foreach ($workshops as $workshop) {
            $randomGroups = $groups->random(rand(1, 3));

            foreach ($randomGroups as $group) {
                DB::table('workshop_groups')->insert([
                    'workshop_id' => $workshop->id,
                    'member_group_id' => $group->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Workshop groups seeded successfully!');
    }
}

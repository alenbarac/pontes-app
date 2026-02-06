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
        // Get only group workshops (exclude individual counseling)
        $groupWorkshops = Workshop::where('type', '!=', 'Individualno')
            ->where(function ($query) {
                $query->where('type', 'Groupe')
                      ->orWhereNull('type')
                      ->orWhere('name', 'LIKE', '%Dramska%');
            })
            ->get();
        
        $groups = MemberGroup::all();

        // Ensure both workshops and groups exist
        if ($groupWorkshops->isEmpty() || $groups->isEmpty()) {
            $this->command->warn('Skipping workshop_groups seeding: No group workshops or groups found.');
            return;
        }

        // ✅ Ensure every group is assigned to at least one group workshop
        foreach ($groups as $group) {
            $randomWorkshop = $groupWorkshops->random();
            DB::table('workshop_groups')->updateOrInsert([
                'workshop_id' => $randomWorkshop->id,
                'member_group_id' => $group->id,
            ], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ✅ Also, randomly assign each group workshop multiple groups
        foreach ($groupWorkshops as $workshop) {
            $randomGroups = $groups->random(rand(2, min(4, $groups->count())));

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

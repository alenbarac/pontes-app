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

        // Get specific workshops
        $dramskaRadionica = Workshop::where('name', 'Dramska radionica')->first();
        $dramska60 = Workshop::where('name', 'Dramska 60+')->first();
        
        // Get regular groups (Grupa 1-8) and Memorabilije 1
        $regularGroups = MemberGroup::where('name', 'LIKE', 'Grupa%')->get();
        $memorabilije1 = MemberGroup::where('name', 'Memorabilije 1')->first();

        // Assign regular groups (Grupa 1-8) to Dramska radionica
        if ($dramskaRadionica && $regularGroups->isNotEmpty()) {
            foreach ($regularGroups as $group) {
                DB::table('workshop_groups')->updateOrInsert([
                    'workshop_id' => $dramskaRadionica->id,
                    'member_group_id' => $group->id,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Assign Memorabilije 1 ONLY to Dramska 60+
        if ($dramska60 && $memorabilije1) {
            DB::table('workshop_groups')->updateOrInsert([
                'workshop_id' => $dramska60->id,
                'member_group_id' => $memorabilije1->id,
            ], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Workshop groups seeded successfully!');
    }
}

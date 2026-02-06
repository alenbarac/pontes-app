<?php

namespace App\Console\Commands;

use App\Models\Workshop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixWorkshopGroups extends Command
{
    protected $signature = 'workshop-groups:fix';
    protected $description = 'Remove groups from individual counseling workshops';

    public function handle()
    {
        $individualWorkshop = Workshop::where('type', 'Individualno')
            ->orWhere('name', 'LIKE', '%Individualno%')
            ->first();

        if (!$individualWorkshop) {
            $this->info('No individual counseling workshop found.');
            return 0;
        }

        $count = DB::table('workshop_groups')
            ->where('workshop_id', $individualWorkshop->id)
            ->count();

        if ($count > 0) {
            DB::table('workshop_groups')
                ->where('workshop_id', $individualWorkshop->id)
                ->delete();
            
            $this->info("Removed {$count} group associations from individual counseling workshop.");
        } else {
            $this->info('No groups found linked to individual counseling workshop.');
        }

        return 0;
    }
}

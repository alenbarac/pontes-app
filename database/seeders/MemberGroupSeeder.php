<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MemberGroup;

class MemberGroupSeeder extends Seeder
{
    public function run()
    {
        $groups = [
            ['name' => 'Grupa 1', 'description' => 'Ponedjeljkom, 18:00 - 19:30'],
            ['name' => 'Grupa 2', 'description' => 'Ponedjeljkom, 20:00 - 21:30'],
            ['name' => 'Grupa 3', 'description' => 'Utorkom, 17:30 – 19:00'],
            ['name' => 'Grupa 4', 'description' => 'Utorkom, 20:00 – 21:30'],
            ['name' => 'Grupa 5', 'description' => 'Srijedom, 20:00 - 21:30'],
            ['name' => 'Grupa 6', 'description' => 'Četvrtkom, 17:00 - 18:30'],
            ['name' => 'Grupa 7', 'description' => 'Četvrtkom, 19:00 - 20:30'],
            ['name' => 'Grupa 8', 'description' => 'Srijedom, 18:30 – 20:00'],   
            ['name' => 'Memorabilije 1', 'description' => 'Dramske radionice za umirovljenike'],
        ];

        foreach ($groups as $group) {
            MemberGroup::create($group);
        }
    }
}

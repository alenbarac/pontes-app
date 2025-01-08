<?php

namespace Database\Seeders;

use App\Models\MemberGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MemberGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            ['name' => 'Juniori 1', 'description' => 'Uzrast 10 - 13 godina. PONEDJELJAK 18:00 - 19:30'],
            ['name' => 'Juniori 2', 'description' => 'Uzrast 11 - 14 godina. ČETVRTAK 19:00 - 20:30'],
            ['name' => 'Mini 1', 'description' => 'Uzrast 9 - 11 godina. ČETVRTAK 17:00 - 18:30'],
            ['name' => 'Mini 2', 'description' => 'Uzrast 9 - 11 godina. SRIJEDA 18:00 - 19:30'],
            ['name' => 'Srednjoškolci 1', 'description' => 'Uzrast 16 - 18 godina. PONEDJELJAK 20:00 - 21:30'],
            ['name' => 'Srednjoškolci 2', 'description' => 'Uzrast 15 - 17 godina. SRIJEDA 20:00 - 21:30'],
            ['name' => 'Memorabilije 1', 'description' => 'Dramske radionice za starije'],
            ['name' => 'Memorabilije 2', 'description' => 'Dramske radionice za starije'],
            ['name' => 'Memorabilije 3', 'description' => 'Dramske radionice za starije'],
        ];
        
        foreach ($groups as $group) {
            MemberGroup::create($group);
        }
    }
}

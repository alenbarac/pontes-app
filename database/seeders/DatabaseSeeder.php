<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Alen',
            'email' => 'info@flatcode.hr',
            'password' => bcrypt('root_1q2w3e4r5t'),
        ]);

        $this->call([
            MemberGroupSeeder::class,
            WorkshopSeeder::class,
            MemberSeeder::class,
            MembershipSeeder::class,
            
           
        ]);
    }
}

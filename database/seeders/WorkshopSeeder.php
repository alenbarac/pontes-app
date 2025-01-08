<?php

namespace Database\Seeders;

use App\Models\Workshop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkshopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workshops = [
            ['name' => 'Dramska radionica', 'type' => 'Groupe', 'description' => 'Grupne dramske radionice za raznovrsni uzrast.'],
            ['name' => 'Individualno savjetovanje', 'type' => 'Individualno', 'description' => 'Psihodramske tehniku u radu s djecom i mladima'],
        ];

        foreach ($workshops as $workshop) {
            Workshop::create($workshop);
        }
    }
}

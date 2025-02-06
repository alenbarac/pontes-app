<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Workshop;

class WorkshopSeeder extends Seeder
{
    public function run()
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

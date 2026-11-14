<?php

namespace Database\Seeders;

use App\Models\MarsRegion;
use Illuminate\Database\Seeder;

class MarsRegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['jezero', 'Cratera Jezero', 18.38, 77.58],
            ['arcadia', 'Arcadia Planitia', 46.7, -172.0],
            ['elysium', 'Elysium Planitia', 4.5, 135.9],
            ['oxia', 'Oxia Planum', 18.28, -24.63],
            ['gale', 'Cratera Gale', -5.4, 137.8],
            ['valles', 'Valles Marineris', -13.9, -59.2],
            ['olympus', 'Monte Olimpo', 18.65, -133.8],
            ['hellas', 'Bacia de Hellas', -42.4, 70.5],
            ['tharsis', 'Tharsis Montes', 1.0, -112.5],
            ['cerberus', 'Cerberus Fossae', 9.0, 162.0],
        ];

        foreach ($regions as [$id, $name, $latitude, $longitude]) {
            MarsRegion::query()->updateOrCreate(
                ['id' => $id],
                [
                    'name' => $name,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'source_name' => 'NASA JPL Mars Trek',
                    'source_url' => 'https://trek.nasa.gov/mars/',
                ],
            );
        }
    }
}

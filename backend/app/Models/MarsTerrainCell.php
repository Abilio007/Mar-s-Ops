<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarsTerrainCell extends Model
{
    protected $fillable = [
        'external_id',
        'source_name',
        'latitude',
        'longitude',
        'elevation_m',
        'slope_degrees',
        'roughness_m',
        'cell_size_km',
        'source_url',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'elevation_m' => 'float',
            'slope_degrees' => 'float',
            'roughness_m' => 'float',
            'cell_size_km' => 'float',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarsRegion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'latitude',
        'longitude',
        'source_name',
        'source_url',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function originMissions(): HasMany
    {
        return $this->hasMany(MarsMission::class, 'origin_region_id');
    }

    public function destinationMissions(): HasMany
    {
        return $this->hasMany(MarsMission::class, 'destination_region_id');
    }
}

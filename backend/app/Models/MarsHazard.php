<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarsHazard extends Model
{
    protected $fillable = [
        'external_id',
        'source_name',
        'category',
        'title',
        'description',
        'latitude',
        'longitude',
        'radius_km',
        'source_url',
        'observed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_km' => 'float',
            'observed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(MarsAlert::class);
    }
}

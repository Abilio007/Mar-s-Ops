<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EarthTrip extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'start_latitude',
        'start_longitude',
        'destination_latitude',
        'destination_longitude',
        'route_geometry',
        'route_steps',
        'distance_m',
        'duration_s',
        'progress_percent',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_latitude' => 'float',
            'start_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'route_geometry' => 'array',
            'route_steps' => 'array',
            'distance_m' => 'integer',
            'duration_s' => 'integer',
            'progress_percent' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

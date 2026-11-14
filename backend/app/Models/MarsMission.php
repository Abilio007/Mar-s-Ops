<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarsMission extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'origin_region_id',
        'destination_region_id',
        'waypoints',
        'departure_date',
        'arrival_date',
        'participants',
        'mission_type',
        'objective',
        'risk_tolerance',
        'notes',
        'status',
        'progress',
        'distance_km',
        'estimated_days',
        'checklist',
    ];

    protected function casts(): array
    {
        return [
            'waypoints' => 'array',
            'checklist' => 'array',
            'departure_date' => 'date:Y-m-d',
            'arrival_date' => 'date:Y-m-d',
            'progress' => 'integer',
            'distance_km' => 'float',
            'estimated_days' => 'integer',
            'participants' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(MarsRegion::class, 'origin_region_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(MarsRegion::class, 'destination_region_id');
    }
}

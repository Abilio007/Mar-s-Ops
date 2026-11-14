<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarsAlert extends Model
{
    protected $fillable = [
        'user_id',
        'mars_hazard_id',
        'severity',
        'distance_km',
        'status',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'float',
            'last_notified_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function hazard(): BelongsTo
    {
        return $this->belongsTo(MarsHazard::class, 'mars_hazard_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

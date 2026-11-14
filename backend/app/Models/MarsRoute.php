<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarsRoute extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'start_latitude',
        'start_longitude',
        'destination_latitude',
        'destination_longitude',
        'candidates',
        'data_coverage',
        'selected_index',
        'status',
        'progress_percent',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_latitude' => 'float',
            'start_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'candidates' => 'array',
            'data_coverage' => 'array',
            'selected_index' => 'integer',
            'progress_percent' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

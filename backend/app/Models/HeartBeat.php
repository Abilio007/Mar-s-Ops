<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeartBeat extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'uuid',
        'latitude',
        'longitude',
        'accuracy_m',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

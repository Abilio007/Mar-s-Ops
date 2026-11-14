<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $user->heartBeats()->delete();
        });
    }

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function heartBeats(): HasMany
    {
        return $this->hasMany(HeartBeat::class, 'user_id');
    }

    public function marsRoutes(): HasMany
    {
        return $this->hasMany(MarsRoute::class);
    }

    public function marsAlerts(): HasMany
    {
        return $this->hasMany(MarsAlert::class);
    }

    public function marsMissions(): HasMany
    {
        return $this->hasMany(MarsMission::class);
    }
}

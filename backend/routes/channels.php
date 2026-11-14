<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('mars.user.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

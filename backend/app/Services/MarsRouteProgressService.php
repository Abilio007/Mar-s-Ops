<?php

namespace App\Services;

use App\Events\MarsRouteProgressUpdated;
use App\Models\HeartBeat;
use App\Models\User;

class MarsRouteProgressService
{
    /**
     * @return array<string, mixed>|null
     */
    public function update(User $user, HeartBeat $heartBeat): ?array
    {
        $route = $user->marsRoutes()
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $route || $route->selected_index === null) {
            return null;
        }

        $candidate = $route->candidates[$route->selected_index];
        $path = $candidate['path'];
        $projection = MarsGeodesy::projectOntoPath(
            $heartBeat->latitude,
            $heartBeat->longitude,
            $path,
        );
        $progress = max($route->progress_percent, $projection['progress_percent']);
        $status = MarsGeodesy::distanceKm(
            $heartBeat->latitude,
            $heartBeat->longitude,
            $route->destination_latitude,
            $route->destination_longitude,
        ) <= 0.2 ? 'completed' : 'active';

        $route->forceFill([
            'progress_percent' => round($status === 'completed' ? 100 : $progress, 2),
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ])->save();

        event(new MarsRouteProgressUpdated($route, $heartBeat->latitude, $heartBeat->longitude));

        return [
            'route_id' => $route->id,
            'status' => $route->status,
            'progress_percent' => $route->progress_percent,
            'distance_from_path_km' => round($projection['distance_km'], 2),
            'candidate' => $candidate,
        ];
    }
}

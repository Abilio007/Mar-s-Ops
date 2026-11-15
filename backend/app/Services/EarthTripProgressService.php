<?php

namespace App\Services;

use App\Models\User;

class EarthTripProgressService
{
    public function __construct(private PushNotificationService $pushNotifications) {}

    /**
     * @param  array{latitude: float, longitude: float}  $position
     * @return array<string, mixed>|null
     */
    public function update(User $user, array $position): ?array
    {
        $trip = $user->earthTrips()->where('status', 'active')->latest()->first();
        if (! $trip) {
            return null;
        }

        $projection = EarthGeodesy::projectOntoPath(
            $position['latitude'],
            $position['longitude'],
            $trip->route_geometry,
        );
        $routeEnd = $trip->route_geometry[array_key_last($trip->route_geometry)];
        $arrived = EarthGeodesy::distanceKm(
            $position['latitude'],
            $position['longitude'],
            $routeEnd['latitude'],
            $routeEnd['longitude'],
        ) <= 0.05;
        $progress = $arrived ? 100 : min(99, (int) round($projection['progress_percent']));

        $trip->forceFill([
            'progress_percent' => $progress,
            'status' => $arrived ? 'completed' : $trip->status,
        ])->save();
        if ($arrived) {
            $this->pushNotifications->send(
                $user,
                'tripUpdates',
                'Viagem terrestre concluída',
                'O destino da viagem foi alcançado.',
            );
        }

        return [
            'trip_id' => $trip->id,
            'progress_percent' => $progress,
            'distance_to_route_km' => round($projection['distance_km'], 3),
            'arrived' => $arrived,
        ];
    }
}

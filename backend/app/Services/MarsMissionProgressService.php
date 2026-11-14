<?php

namespace App\Services;

use App\Models\MarsMission;
use App\Models\User;

class MarsMissionProgressService
{
    public function update(User $user, float $latitude, float $longitude): ?MarsMission
    {
        $mission = $user->marsMissions()
            ->where('status', 'active')
            ->with(['origin', 'destination'])
            ->latest()
            ->first();

        if (! $mission) {
            return null;
        }

        $regionIds = [
            $mission->origin_region_id,
            ...($mission->waypoints ?? []),
            $mission->destination_region_id,
        ];
        $regions = \App\Models\MarsRegion::query()
            ->whereIn('id', $regionIds)
            ->get()
            ->keyBy('id');
        $path = collect($regionIds)->map(fn (string $id): array => [
            'latitude' => $regions->get($id)->latitude,
            'longitude' => $regions->get($id)->longitude,
        ])->all();

        $projection = MarsGeodesy::projectOntoPath($latitude, $longitude, $path);
        $mission->forceFill([
            'progress' => min(99, (int) round($projection['progress_percent'])),
        ])->save();

        return $mission;
    }
}

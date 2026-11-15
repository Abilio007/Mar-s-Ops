<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EarthTripProgressService;
use App\Services\MarsAlertService;
use App\Services\MarsMissionProgressService;
use App\Services\MarsRouteProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HeartBeatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $heartBeats = $request->user()
            ->heartBeats()
            ->where('coordinate_system', 'mars')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $heartBeats]);
    }

    public function latest(Request $request): JsonResponse
    {
        $heartBeat = $request->user()->heartBeats()
            ->where('coordinate_system', 'mars')
            ->latest()
            ->first();

        return response()->json(['data' => $heartBeat]);
    }

    public function store(
        Request $request,
        MarsAlertService $marsAlertService,
        MarsMissionProgressService $marsMissionProgressService,
        MarsRouteProgressService $marsRouteProgressService,
        EarthTripProgressService $earthTripProgressService,
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'coordinate_system' => ['sometimes', 'string', 'in:mars,earth'],
        ]);
        $coordinateSystem = $validated['coordinate_system'] ?? 'mars';

        /** @var User $user */
        $user = $request->user();
        $uuid = (string) Str::uuid();
        $heartBeat = $user->heartBeats()->create([
            'id' => $uuid,
            'uuid' => $uuid,
            ...$validated,
            'coordinate_system' => $coordinateSystem,
        ]);

        if ($coordinateSystem === 'earth') {
            $tripProgress = $earthTripProgressService->update($user, [
                'latitude' => $heartBeat->latitude,
                'longitude' => $heartBeat->longitude,
            ]);

            return response()->json([
                'data' => $heartBeat,
                'alerts' => [],
                'trip_progress' => $tripProgress,
            ], 201);
        }

        $alerts = $marsAlertService->evaluate($user, $heartBeat);
        $marsMissionProgressService->update($user, $heartBeat->latitude, $heartBeat->longitude);
        $routeProgress = $marsRouteProgressService->update($user, $heartBeat);

        return response()->json([
            'data' => $heartBeat,
            'alerts' => $alerts,
            'route_progress' => $routeProgress,
            'heartbeat_interval_seconds' => 60,
        ], 201);
    }
}

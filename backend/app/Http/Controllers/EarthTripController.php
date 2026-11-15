<?php

namespace App\Http\Controllers;

use App\Models\EarthTrip;
use App\Models\User;
use App\Services\EarthRoutingService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EarthTripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->earthTrips()->latest()->get()->map(
                fn (EarthTrip $trip): array => $this->serialize($trip),
            ),
        ]);
    }

    public function store(
        Request $request,
        EarthRoutingService $routing,
        PushNotificationService $pushNotifications,
    ): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'start.latitude' => ['required', 'numeric', 'between:-90,90'],
            'start.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        abort_if(
            (float) $validated['start']['latitude'] === (float) $validated['destination']['latitude']
            && (float) $validated['start']['longitude'] === (float) $validated['destination']['longitude'],
            422,
            'A origem e o destino da viagem têm de ser diferentes.',
        );

        $route = $routing->route($validated['start'], $validated['destination']);
        $trip = $request->user()->earthTrips()->create([
            'name' => $validated['name'],
            'start_latitude' => $validated['start']['latitude'],
            'start_longitude' => $validated['start']['longitude'],
            'destination_latitude' => $validated['destination']['latitude'],
            'destination_longitude' => $validated['destination']['longitude'],
            'route_geometry' => $route['geometry'],
            'route_steps' => $route['steps'],
            'distance_m' => $route['distance_m'],
            'duration_s' => $route['duration_s'],
        ]);
        $pushNotifications->send(
            $request->user(),
            'tripUpdates',
            'Viagem terrestre criada',
            'Uma viagem terrestre foi adicionada ao planeamento.',
        );

        return response()->json(['data' => $this->serialize($trip)], 201);
    }

    public function updateStatus(
        Request $request,
        string $tripId,
        PushNotificationService $pushNotifications,
    ): JsonResponse {
        $trip = $this->ownedTrip($request->user(), $tripId);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['planned', 'active', 'completed', 'cancelled'])],
        ]);

        if ($validated['status'] === 'active') {
            abort_if(
                $request->user()->earthTrips()->where('status', 'active')->where('id', '!=', $trip->id)->exists(),
                409,
                'Já existe uma viagem terrestre ativa nesta conta.',
            );
        }

        $trip->forceFill([
            'status' => $validated['status'],
            'progress_percent' => $validated['status'] === 'completed' ? 100 : $trip->progress_percent,
        ])->save();
        if ($trip->wasChanged('status')) {
            $pushNotifications->send(
                $request->user(),
                'tripUpdates',
                'Estado da viagem atualizado',
                $validated['status'] === 'active'
                    ? 'Uma viagem terrestre foi iniciada.'
                    : ($validated['status'] === 'completed'
                        ? 'Uma viagem terrestre foi concluída.'
                        : 'O estado de uma viagem terrestre foi alterado.'),
            );
        }

        return response()->json(['data' => $this->serialize($trip->refresh())]);
    }

    public function destroy(
        Request $request,
        string $tripId,
        PushNotificationService $pushNotifications,
    ): JsonResponse
    {
        $this->ownedTrip($request->user(), $tripId)->delete();
        $pushNotifications->send(
            $request->user(),
            'tripUpdates',
            'Viagem terrestre removida',
            'Uma viagem terrestre foi removida do planeamento.',
        );

        return response()->json(['message' => 'Viagem terrestre eliminada.']);
    }

    private function ownedTrip(User $user, string $tripId): EarthTrip
    {
        return $user->earthTrips()->findOrFail($tripId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(EarthTrip $trip): array
    {
        return [
            'id' => (string) $trip->id,
            'name' => $trip->name,
            'start' => ['latitude' => $trip->start_latitude, 'longitude' => $trip->start_longitude],
            'destination' => ['latitude' => $trip->destination_latitude, 'longitude' => $trip->destination_longitude],
            'geometry' => $trip->route_geometry,
            'steps' => $trip->route_steps,
            'distanceMeters' => $trip->distance_m,
            'durationSeconds' => $trip->duration_s,
            'progressPercent' => $trip->progress_percent,
            'status' => $trip->status,
        ];
    }
}

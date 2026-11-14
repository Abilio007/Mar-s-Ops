<?php

namespace App\Http\Controllers;

use App\Models\MarsMission;
use App\Models\MarsRegion;
use App\Models\User;
use App\Services\MarsGeodesy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarsMissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()
                ->marsMissions()
                ->latest()
                ->get()
                ->map(fn (MarsMission $mission): array => $this->serialize($mission)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateMission($request);
        $metrics = $this->routeMetrics($validated);

        $mission = $request->user()->marsMissions()->create([
            ...$this->toModelAttributes($validated),
            ...$metrics,
            'status' => 'planned',
            'progress' => 0,
            'checklist' => [],
        ]);

        return response()->json(['data' => $this->serialize($mission)], 201);
    }

    public function update(Request $request, string $missionId): JsonResponse
    {
        $mission = $this->ownedMission($request->user(), $missionId);
        $validated = $this->validateMission($request);
        $mission->update([
            ...$this->toModelAttributes($validated),
            ...$this->routeMetrics($validated),
        ]);

        return response()->json(['data' => $this->serialize($mission->refresh())]);
    }

    public function destroy(Request $request, string $missionId): JsonResponse
    {
        $this->ownedMission($request->user(), $missionId)->delete();

        return response()->json(['message' => 'Mission deleted.']);
    }

    public function updateStatus(Request $request, string $missionId): JsonResponse
    {
        $mission = $this->ownedMission($request->user(), $missionId);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['planned', 'active', 'completed'])],
        ]);
        $mission->forceFill([
            'status' => $validated['status'],
            'progress' => $validated['status'] === 'completed' ? 100 : $mission->progress,
        ])->save();

        return response()->json(['data' => $this->serialize($mission->refresh())]);
    }

    public function updateChecklistItem(Request $request, string $missionId, string $itemId): JsonResponse
    {
        $mission = $this->ownedMission($request->user(), $missionId);
        $validated = $request->validate(['done' => ['required', 'boolean']]);
        $checklist = $mission->checklist ?? [];
        $itemIndex = collect($checklist)->search(fn (array $item): bool => ($item['id'] ?? null) === $itemId);
        abort_if($itemIndex === false, 404);
        $checklist[$itemIndex]['done'] = $validated['done'];
        $mission->forceFill(['checklist' => $checklist])->save();

        return response()->json(['data' => $this->serialize($mission->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMission(Request $request): array
    {
        $regionRule = Rule::exists('mars_regions', 'id');
        $missionTypeValues = collect(config('mars.mission_types'))->pluck('value')->all();
        $riskValues = collect(config('mars.risk_tolerances'))->pluck('value')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'originId' => ['required', 'string', $regionRule],
            'destinationId' => ['required', 'string', $regionRule],
            'waypoints' => ['sometimes', 'array', 'max:20'],
            'waypoints.*' => ['string', $regionRule],
            'departureDate' => ['required', 'date'],
            'arrivalDate' => ['required', 'date', 'after_or_equal:departureDate'],
            'participants' => ['required', 'integer', 'between:1,50'],
            'missionType' => ['required', Rule::in($missionTypeValues)],
            'objective' => ['required', 'string', 'max:2000'],
            'riskTolerance' => ['required', Rule::in($riskValues)],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function toModelAttributes(array $validated): array
    {
        abort_if($validated['originId'] === $validated['destinationId'], 422, 'Origin and destination must differ.');

        return [
            'name' => $validated['name'],
            'origin_region_id' => $validated['originId'],
            'destination_region_id' => $validated['destinationId'],
            'waypoints' => array_values($validated['waypoints'] ?? []),
            'departure_date' => $validated['departureDate'],
            'arrival_date' => $validated['arrivalDate'],
            'participants' => $validated['participants'],
            'mission_type' => $validated['missionType'],
            'objective' => $validated['objective'],
            'risk_tolerance' => $validated['riskTolerance'],
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{distance_km: float, estimated_days: int}
     */
    private function routeMetrics(array $validated): array
    {
        $regionIds = array_values(array_unique([
            $validated['originId'],
            ...($validated['waypoints'] ?? []),
            $validated['destinationId'],
        ]));
        $regions = MarsRegion::query()->whereIn('id', $regionIds)->get()->keyBy('id');
        $orderedRegions = collect([
            $validated['originId'],
            ...($validated['waypoints'] ?? []),
            $validated['destinationId'],
        ])->map(fn (string $id): MarsRegion => $regions->get($id));
        $distance = 0.0;

        for ($index = 1; $index < $orderedRegions->count(); $index++) {
            $start = $orderedRegions[$index - 1];
            $end = $orderedRegions[$index];
            $distance += MarsGeodesy::distanceKm($start->latitude, $start->longitude, $end->latitude, $end->longitude);
        }

        $hours = $distance / (float) config('mars.cruise_speed_km_per_hour');

        return [
            'distance_km' => round($distance),
            'estimated_days' => max(1, (int) ceil($hours / 24.62)),
        ];
    }

    private function ownedMission(User $user, string $missionId): MarsMission
    {
        return $user->marsMissions()->findOrFail($missionId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MarsMission $mission): array
    {
        return [
            'id' => (string) $mission->id,
            'name' => $mission->name,
            'originId' => $mission->origin_region_id,
            'destinationId' => $mission->destination_region_id,
            'waypoints' => $mission->waypoints ?? [],
            'departureDate' => $mission->departure_date->toDateString(),
            'arrivalDate' => $mission->arrival_date->toDateString(),
            'participants' => $mission->participants,
            'missionType' => $mission->mission_type,
            'objective' => $mission->objective,
            'riskTolerance' => $mission->risk_tolerance,
            'notes' => $mission->notes,
            'status' => $mission->status,
            'progress' => $mission->progress,
            'distanceKm' => $mission->distance_km,
            'estimatedDays' => $mission->estimated_days,
            'checklist' => $mission->checklist ?? [],
        ];
    }
}

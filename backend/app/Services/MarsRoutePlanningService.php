<?php

namespace App\Services;

use App\Ai\Agents\AIRouterPlanner;
use App\Models\MarsHazard;
use App\Models\MarsTerrainCell;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarsRoutePlanningService
{
    private const CORRIDOR_BUFFER_KM = 50;

    private const MAX_HAZARDS = 200;

    private const MAX_TERRAIN_CELLS = 300;

    public function __construct(private AIRouterPlanner $agent) {}

    /**
     * @param  array{latitude: float|int, longitude: float|int}  $start
     * @param  array{latitude: float|int, longitude: float|int}  $destination
     * @return array{candidates: array<int, array<string, mixed>>, data_coverage: array<string, mixed>}
     */
    public function plan(array $start, array $destination): array
    {
        $hazards = $this->recordsNearRoute(
            MarsHazard::query()->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            }),
            $start,
            $destination,
            self::MAX_HAZARDS,
        );
        $terrainCells = $this->recordsNearRoute(MarsTerrainCell::query(), $start, $destination, self::MAX_TERRAIN_CELLS);

        $dataCoverage = [
            'hazard_records_loaded' => $hazards->count(),
            'terrain_cells_loaded' => $terrainCells->count(),
            'terrain_verified' => false,
            'route_corridor_buffer_km' => self::CORRIDOR_BUFFER_KM,
            'sources' => $hazards->pluck('source_name')
                ->merge($terrainCells->pluck('source_name'))
                ->unique()
                ->values(),
        ];

        $response = $this->agent->prompt(
            json_encode([
                'task' => 'Generate up to three distinct Marswalk routes, ordered best to worst, between the exact endpoints. Use only these NASA/USGS records. Terrain data is incomplete when no samples cover a route; do not infer safety from missing evidence.',
                'start' => $start,
                'destination' => $destination,
                'route_corridor_buffer_km' => self::CORRIDOR_BUFFER_KM,
                'data_coverage' => $dataCoverage,
                'hazards' => $hazards->map(fn (MarsHazard $hazard): array => [
                    'category' => $hazard->category,
                    'title' => $hazard->title,
                    'description' => $hazard->description,
                    'latitude' => $hazard->latitude,
                    'longitude' => $hazard->longitude,
                    'radius_km' => $hazard->radius_km,
                    'source_name' => $hazard->source_name,
                    'source_url' => $hazard->source_url,
                    'observed_at' => $hazard->observed_at?->toIso8601String(),
                    'expires_at' => $hazard->expires_at?->toIso8601String(),
                ])->all(),
                'terrain_cells' => $terrainCells->map(fn (MarsTerrainCell $cell): array => [
                    'latitude' => $cell->latitude,
                    'longitude' => $cell->longitude,
                    'elevation_m' => $cell->elevation_m,
                    'slope_degrees' => $cell->slope_degrees,
                    'roughness_m' => $cell->roughness_m,
                    'cell_size_km' => $cell->cell_size_km,
                    'source_name' => $cell->source_name,
                    'source_url' => $cell->source_url,
                ])->all(),
            ], JSON_THROW_ON_ERROR),
            provider: (string) config('services.mars.ai_provider', 'gemini'),
            model: (string) config('services.mars.ai_model', 'gemini-2.5-flash'),
            timeout: (int) config('services.mars.ai_timeout', 45),
        );

        $candidates = $this->validateCandidates($response->structured['candidates'] ?? null, $start, $destination);

        foreach ($candidates as $index => &$candidate) {
            $candidate['candidate_index'] = $index;
            $candidate = $this->addVerifiedMeasurements($candidate, $hazards, $terrainCells);
        }
        unset($candidate);

        return [
            'candidates' => $candidates,
            'data_coverage' => $dataCoverage,
        ];
    }

    /**
     * @param  Builder  $query
     * @param  array{latitude: float|int, longitude: float|int}  $start
     * @param  array{latitude: float|int, longitude: float|int}  $destination
     * @return \Illuminate\Database\Eloquent\Collection<int, MarsHazard|MarsTerrainCell>
     */
    private function recordsNearRoute($query, array $start, array $destination, int $maximum)
    {
        $latitudeBuffer = rad2deg(self::CORRIDOR_BUFFER_KM / 3389.5);
        $middleLatitude = ((float) $start['latitude'] + (float) $destination['latitude']) / 2;
        $longitudeBuffer = min(
            180,
            $latitudeBuffer / max(abs(cos(deg2rad($middleLatitude))), 0.05),
        );
        $endLongitude = (float) $start['longitude'] + MarsGeodesy::normalizeLongitude(
            (float) $destination['longitude'] - (float) $start['longitude'],
        );
        $west = min((float) $start['longitude'], $endLongitude) - $longitudeBuffer;
        $east = max((float) $start['longitude'], $endLongitude) + $longitudeBuffer;
        $south = max(-90, min((float) $start['latitude'], (float) $destination['latitude']) - $latitudeBuffer);
        $north = min(90, max((float) $start['latitude'], (float) $destination['latitude']) + $latitudeBuffer);

        $query->whereBetween('latitude', [$south, $north]);

        if ($east - $west < 360) {
            while ($west < -180) {
                $west += 360;
                $east += 360;
            }

            while ($west > 180) {
                $west -= 360;
                $east -= 360;
            }

            if ($east <= 180) {
                $query->whereBetween('longitude', [$west, $east]);
            } elseif ($east - 360 >= -180) {
                $query->where(function ($query) use ($west, $east): void {
                    $query->where('longitude', '>=', $west)
                        ->orWhere('longitude', '<=', $east - 360);
                });
            }
        }

        $records = $query->limit($maximum + 1)->get();

        abort_if(
            $records->count() > $maximum,
            413,
            'Too many map records intersect this route corridor. Import or query a more focused dataset before planning.',
        );

        return $records;
    }

    /**
     * @param  array{latitude: float|int, longitude: float|int}  $start
     * @param  array{latitude: float|int, longitude: float|int}  $destination
     * @return array<int, array<string, mixed>>
     */
    private function validateCandidates(mixed $candidateData, array $start, array $destination): array
    {
        if (! is_array($candidateData)) {
            abort(502, 'The AI route planner did not return routes.');
        }

        $validator = validator(['candidates' => $candidateData], [
            'candidates' => ['required', 'array', 'min:1', 'max:3'],
            'candidates.*.name' => ['required', 'string', 'min:1', 'max:80'],
            'candidates.*.path' => ['required', 'array', 'min:2', 'max:50'],
            'candidates.*.path.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'candidates.*.path.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'candidates.*.score' => ['required', 'numeric', 'between:0,100'],
            'candidates.*.risk_level' => ['required', 'in:low,moderate,high,unknown'],
            'candidates.*.science_value' => ['required', 'numeric', 'between:0,100'],
            'candidates.*.rationale' => ['required', 'string', 'max:600'],
            'candidates.*.uncertainties' => ['present', 'array', 'max:8'],
            'candidates.*.uncertainties.*' => ['string', 'max:200'],
        ]);

        if ($validator->fails()) {
            abort(502, 'The AI route planner returned an invalid route structure.');
        }

        $candidates = $validator->validated()['candidates'];
        $expectedStart = ['latitude' => (float) $start['latitude'], 'longitude' => (float) $start['longitude']];
        $expectedDestination = [
            'latitude' => (float) $destination['latitude'],
            'longitude' => (float) $destination['longitude'],
        ];
        $directPath = [$expectedStart, $expectedDestination];

        foreach ($candidates as &$candidate) {
            $candidate['path'] = array_map(fn (array $point): array => [
                'latitude' => (float) $point['latitude'],
                'longitude' => (float) $point['longitude'],
            ], $candidate['path']);

            $path = $candidate['path'];
            $lastIndex = count($path) - 1;
            abort_if(
                MarsGeodesy::distanceKm(
                    $path[0]['latitude'],
                    $path[0]['longitude'],
                    $expectedStart['latitude'],
                    $expectedStart['longitude'],
                ) > 0.1
                || MarsGeodesy::distanceKm(
                    $path[$lastIndex]['latitude'],
                    $path[$lastIndex]['longitude'],
                    $expectedDestination['latitude'],
                    $expectedDestination['longitude'],
                ) > 0.1,
                502,
                'The AI route planner returned a route with incorrect endpoints.',
            );
            foreach ($path as $point) {
                abort_if(
                    MarsGeodesy::distanceToPathKm(
                        $point['latitude'],
                        $point['longitude'],
                        $directPath,
                    ) > self::CORRIDOR_BUFFER_KM + 0.1,
                    502,
                    'The AI route planner returned a waypoint outside the supported route corridor.',
                );
            }

            $path[0] = $expectedStart;
            $path[$lastIndex] = $expectedDestination;
            $candidate['path'] = $path;
        }
        unset($candidate);

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  Collection<int, MarsHazard>  $hazards
     * @param  Collection<int, MarsTerrainCell>  $terrainCells
     * @return array<string, mixed>
     */
    private function addVerifiedMeasurements(array $candidate, $hazards, $terrainCells): array
    {
        $path = $candidate['path'];
        $hazardObservations = [];
        $crossedHazards = [];
        $nearbyHazards = [];

        foreach ($hazards as $hazard) {
            $distance = MarsGeodesy::distanceToPathKm($hazard->latitude, $hazard->longitude, $path);

            if ($distance > $hazard->radius_km + 50) {
                continue;
            }

            $observation = [
                'category' => $hazard->category,
                'title' => $hazard->title,
                'distance_to_path_km' => round($distance, 2),
                'radius_km' => $hazard->radius_km,
                'source_name' => $hazard->source_name,
                'source_url' => $hazard->source_url,
                'observed_at' => $hazard->observed_at?->toIso8601String(),
                'expires_at' => $hazard->expires_at?->toIso8601String(),
            ];
            $hazardObservations[] = $observation;

            if ($distance <= $hazard->radius_km) {
                $crossedHazards[] = $observation;
            } elseif ($distance <= $hazard->radius_km + 5) {
                $nearbyHazards[] = $observation;
            }
        }

        $terrainObservations = [];
        $steepTerrainCells = 0;
        $terrainSamples = 0;
        foreach ($terrainCells as $cell) {
            $distance = MarsGeodesy::distanceToPathKm($cell->latitude, $cell->longitude, $path);
            if ($distance > max(0.1, $cell->cell_size_km / 2)) {
                continue;
            }

            $terrainSamples++;
            if ($cell->slope_degrees >= 15) {
                $steepTerrainCells++;
            }

            if (count($terrainObservations) < 100) {
                $terrainObservations[] = [
                    'external_id' => $cell->external_id,
                    'latitude' => $cell->latitude,
                    'longitude' => $cell->longitude,
                    'elevation_m' => $cell->elevation_m,
                    'slope_degrees' => $cell->slope_degrees,
                    'roughness_m' => $cell->roughness_m,
                    'cell_size_km' => $cell->cell_size_km,
                    'distance_to_path_km' => round($distance, 2),
                    'source_name' => $cell->source_name,
                    'source_url' => $cell->source_url,
                ];
            }
        }

        return [
            ...$candidate,
            'distance_km' => round(MarsGeodesy::pathDistanceKm($path), 2),
            'hazard_observations' => $hazardObservations,
            'hazards_crossed' => $crossedHazards,
            'nearby_hazards' => $nearbyHazards,
            'terrain_samples' => $terrainSamples,
            'terrain_observations' => $terrainObservations,
            'steep_terrain_cells' => $steepTerrainCells,
            'terrain_data_available' => $terrainObservations !== [],
            'terrain_verified' => false,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MarsHazard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarsHazardController extends Controller
{
    public function index(): JsonResponse
    {
        $hazards = MarsHazard::query()
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('observed_at')
            ->limit(500)
            ->get();

        return response()->json(['data' => $hazards]);
    }

    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.external_id' => ['required', 'string', 'max:160'],
            'records.*.source_name' => ['required', 'in:mars_trek,usgs_geology,pds_weather'],
            'records.*.category' => ['required', 'in:weather,geology,terrain,radiation,other'],
            'records.*.title' => ['required', 'string', 'max:200'],
            'records.*.description' => ['nullable', 'string', 'max:4000'],
            'records.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'records.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'records.*.radius_km' => ['sometimes', 'numeric', 'between:0,500'],
            'records.*.source_url' => ['required', 'url:https', 'max:2048'],
            'records.*.observed_at' => ['nullable', 'date'],
            'records.*.expires_at' => ['nullable', 'date'],
        ]);

        $records = $validated['records'];

        foreach ($records as $record) {
            abort_unless(
                $this->isTrustedSourceUrl($record['source_name'], $record['source_url']),
                422,
                'The source URL is not an approved NASA or USGS host for this source.',
            );
        }

        $upserted = DB::transaction(function () use ($records): int {
            foreach ($records as $record) {
                MarsHazard::query()->updateOrCreate(
                    [
                        'source_name' => $record['source_name'],
                        'external_id' => $record['external_id'],
                    ],
                    [
                        ...$record,
                        'radius_km' => $record['radius_km'] ?? 0,
                    ],
                );
            }

            return count($records);
        });

        return response()->json([
            'upserted' => $upserted,
            'message' => 'Hazard records stored. Only weather records trigger proximity alerts.',
        ]);
    }

    private function isTrustedSourceUrl(string $sourceName, string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedHosts = match ($sourceName) {
            'mars_trek' => ['trek.nasa.gov'],
            'usgs_geology' => ['astrogeology-usgs.hub.arcgis.com', 'planetarymaps.usgs.gov'],
            'pds_weather' => ['pds-geosciences.wustl.edu', 'pds.nasa.gov'],
        };

        foreach ($allowedHosts as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }
}

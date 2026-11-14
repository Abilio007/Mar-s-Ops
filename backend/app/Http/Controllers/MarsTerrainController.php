<?php

namespace App\Http\Controllers;

use App\Models\MarsTerrainCell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarsTerrainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'min_lat' => ['required', 'numeric', 'between:-90,90'],
            'max_lat' => ['required', 'numeric', 'between:-90,90', 'gte:min_lat'],
            'min_lon' => ['required', 'numeric', 'between:-180,180'],
            'max_lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $query = MarsTerrainCell::query()
            ->whereBetween('latitude', [$validated['min_lat'], $validated['max_lat']]);

        if ($validated['min_lon'] <= $validated['max_lon']) {
            $query->whereBetween('longitude', [$validated['min_lon'], $validated['max_lon']]);
        } else {
            $query->where(function ($query) use ($validated): void {
                $query->where('longitude', '>=', $validated['min_lon'])
                    ->orWhere('longitude', '<=', $validated['max_lon']);
            });
        }

        return response()->json([
            'data' => $query->limit(2000)->get(),
            'limit' => 2000,
        ]);
    }

    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.external_id' => ['required', 'string', 'max:160'],
            'records.*.source_name' => ['required', 'in:mars_trek,usgs_geology'],
            'records.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'records.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'records.*.elevation_m' => ['nullable', 'numeric', 'between:-20000,100000'],
            'records.*.slope_degrees' => ['required', 'numeric', 'between:0,90'],
            'records.*.roughness_m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'records.*.cell_size_km' => ['required', 'numeric', 'between:0.01,100'],
            'records.*.source_url' => ['required', 'url:https', 'max:2048'],
        ]);

        $records = $validated['records'];

        foreach ($records as $record) {
            abort_unless(
                $this->isTrustedSourceUrl($record['source_name'], $record['source_url']),
                422,
                'The source URL is not an approved NASA or USGS host for terrain data.',
            );
        }

        $upserted = DB::transaction(function () use ($records): int {
            foreach ($records as $record) {
                MarsTerrainCell::query()->updateOrCreate(
                    [
                        'source_name' => $record['source_name'],
                        'external_id' => $record['external_id'],
                    ],
                    $record,
                );
            }

            return count($records);
        });

        return response()->json(['upserted' => $upserted]);
    }

    private function isTrustedSourceUrl(string $sourceName, string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedHosts = match ($sourceName) {
            'mars_trek' => ['trek.nasa.gov'],
            'usgs_geology' => ['astrogeology-usgs.hub.arcgis.com', 'planetarymaps.usgs.gov'],
        };

        foreach ($allowedHosts as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }
}

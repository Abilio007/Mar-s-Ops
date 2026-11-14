<?php

namespace App\Http\Controllers;

use App\Models\MarsRegion;
use Illuminate\Http\JsonResponse;

class MarsRegionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => MarsRegion::query()
                ->orderBy('name')
                ->get()
                ->map(fn (MarsRegion $region): array => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'lat' => $region->latitude,
                    'lon' => $region->longitude,
                    'sourceName' => $region->source_name,
                    'sourceUrl' => $region->source_url,
                ]),
        ]);
    }
}

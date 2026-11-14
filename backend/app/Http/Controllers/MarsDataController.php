<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MarsDataController extends Controller
{
    public function catalog(): JsonResponse
    {
        return response()->json([
            'data' => [
                'missionTypes' => config('mars.mission_types'),
                'riskTolerances' => config('mars.risk_tolerances'),
                'equipmentByMissionType' => config('mars.equipment_by_mission_type'),
            ],
        ]);
    }

    public function sources(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'id' => 'mars_trek',
                    'name' => 'NASA JPL Mars Trek',
                    'url' => 'https://trek.nasa.gov/mars/',
                    'purpose' => 'Interactive Mars basemaps and mission data; use as a map and terrain-data source.',
                    'integration' => 'NASA map layers are not automatically imported by this MVP.',
                ],
                [
                    'id' => 'usgs_geology',
                    'name' => 'USGS-NASA Planetary Geologic Mapping Program',
                    'url' => 'https://astrogeology-usgs.hub.arcgis.com/pages/interactive-maps-mars',
                    'purpose' => 'Geologic map context and imported geologic hazard zones.',
                    'integration' => 'Import verified map features through the protected hazard-ingest endpoint.',
                ],
                [
                    'id' => 'curiosity_route',
                    'name' => 'Curiosity proposed Mount Sharp route',
                    'url' => 'https://science.nasa.gov/resource/curiositys-proposed-path-up-mount-sharp/',
                    'purpose' => 'Route-planning reference and mission context.',
                    'integration' => 'Reference only; not a current astronaut route or live feed.',
                ],
                [
                    'id' => 'nasa_worldview',
                    'name' => 'NASA Worldview',
                    'url' => 'https://worldview.earthdata.nasa.gov/',
                    'purpose' => 'Earth satellite imagery.',
                    'integration' => 'Earth-only; excluded from Mars route scoring and Mars weather alerts.',
                ],
                [
                    'id' => 'pds_weather',
                    'name' => 'NASA Planetary Data System — InSight archive',
                    'url' => 'https://pds-geosciences.wustl.edu/missions/insight/',
                    'purpose' => 'Archived Mars environmental measurements.',
                    'integration' => 'Not a live weather forecast. Only unexpired, ingested records trigger alerts.',
                ],
                [
                    'id' => 'perseverance_raw_images',
                    'name' => 'Mars Perseverance Raw Images Gallery',
                    'url' => 'https://mars.nasa.gov/mars2020/multimedia/raw-images/',
                    'purpose' => 'Mission raw image gallery.',
                    'integration' => 'Gallery reference; this MVP does not scrape the page.',
                ],
            ],
        ]);
    }
}

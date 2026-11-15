<?php

namespace App\Services;

class EarthGeodesy
{
    private const EARTH_RADIUS_KM = 6371.0088;

    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $path
     * @return array{progress_percent: float, distance_km: float}
     */
    public static function projectOntoPath(float $latitude, float $longitude, array $path): array
    {
        $length = self::pathDistanceKm($path);
        if ($length === 0.0) {
            return [
                'progress_percent' => 0.0,
                'distance_km' => self::distanceKm(
                    $latitude,
                    $longitude,
                    $path[0]['latitude'],
                    $path[0]['longitude'],
                ),
            ];
        }

        $travelled = 0.0;
        $closestDistance = INF;
        $closestProgress = 0.0;

        for ($index = 1; $index < count($path); $index++) {
            $start = $path[$index - 1];
            $end = $path[$index];
            $segmentLength = self::distanceKm($start['latitude'], $start['longitude'], $end['latitude'], $end['longitude']);
            [$fraction, $distance] = self::projectOntoSegment($latitude, $longitude, $start, $end);

            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestProgress = $travelled + ($segmentLength * $fraction);
            }
            $travelled += $segmentLength;
        }

        return [
            'progress_percent' => $length > 0 ? min(100, max(0, $closestProgress / $length * 100)) : 0,
            'distance_km' => $closestDistance,
        ];
    }

    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $path
     */
    private static function pathDistanceKm(array $path): float
    {
        $distance = 0.0;
        for ($index = 1; $index < count($path); $index++) {
            $distance += self::distanceKm(
                $path[$index - 1]['latitude'],
                $path[$index - 1]['longitude'],
                $path[$index]['latitude'],
                $path[$index]['longitude'],
            );
        }

        return $distance;
    }

    public static function distanceKm(float $latA, float $lonA, float $latB, float $lonB): float
    {
        $latitudeDelta = deg2rad($latB - $latA);
        $longitudeDelta = deg2rad(fmod($lonB - $lonA + 540, 360) - 180);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($longitudeDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1, sqrt($a)));
    }

    /**
     * @param  array{latitude: float, longitude: float}  $start
     * @param  array{latitude: float, longitude: float}  $end
     * @return array{0: float, 1: float}
     */
    private static function projectOntoSegment(float $latitude, float $longitude, array $start, array $end): array
    {
        $scaleX = self::EARTH_RADIUS_KM * cos(deg2rad($latitude)) * M_PI / 180;
        $scaleY = self::EARTH_RADIUS_KM * M_PI / 180;
        $startX = (fmod($start['longitude'] - $longitude + 540, 360) - 180) * $scaleX;
        $startY = ($start['latitude'] - $latitude) * $scaleY;
        $endX = (fmod($end['longitude'] - $longitude + 540, 360) - 180) * $scaleX;
        $endY = ($end['latitude'] - $latitude) * $scaleY;
        $deltaX = $endX - $startX;
        $deltaY = $endY - $startY;
        $lengthSquared = $deltaX ** 2 + $deltaY ** 2;
        $fraction = $lengthSquared === 0.0
            ? 0.0
            : max(0, min(1, -($startX * $deltaX + $startY * $deltaY) / $lengthSquared));

        return [
            $fraction,
            hypot($startX + $fraction * $deltaX, $startY + $fraction * $deltaY),
        ];
    }
}

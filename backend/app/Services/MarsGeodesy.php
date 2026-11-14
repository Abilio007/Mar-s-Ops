<?php

namespace App\Services;

class MarsGeodesy
{
    private const MARS_RADIUS_KM = 3389.5;

    public static function distanceKm(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad(self::normalizeLongitude($longitudeB - $longitudeA));
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return 2 * self::MARS_RADIUS_KM * asin(min(1, sqrt($a)));
    }

    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $path
     */
    public static function pathDistanceKm(array $path): float
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

    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $path
     * @return array{progress_percent: float, distance_km: float}
     */
    public static function projectOntoPath(float $latitude, float $longitude, array $path): array
    {
        $routeLength = self::pathDistanceKm($path);

        if ($routeLength === 0.0) {
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

        $distanceTravelled = 0.0;
        $closestDistance = INF;
        $closestProgress = 0.0;

        for ($index = 1; $index < count($path); $index++) {
            $segmentStart = $path[$index - 1];
            $segmentEnd = $path[$index];
            ['fraction' => $fraction, 'distance' => $distance] = self::projectOntoSegment(
                $latitude,
                $longitude,
                $segmentStart,
                $segmentEnd,
            );
            $segmentLength = self::distanceKm(
                $segmentStart['latitude'],
                $segmentStart['longitude'],
                $segmentEnd['latitude'],
                $segmentEnd['longitude'],
            );

            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestProgress = $distanceTravelled + $segmentLength * $fraction;
            }

            $distanceTravelled += $segmentLength;
        }

        return [
            'progress_percent' => min(100, max(0, $closestProgress / $routeLength * 100)),
            'distance_km' => $closestDistance,
        ];
    }

    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $path
     */
    public static function distanceToPathKm(float $latitude, float $longitude, array $path): float
    {
        if (count($path) === 1) {
            return self::distanceKm($latitude, $longitude, $path[0]['latitude'], $path[0]['longitude']);
        }

        $closestDistance = INF;

        for ($index = 1; $index < count($path); $index++) {
            $closestDistance = min(
                $closestDistance,
                self::projectOntoSegment(
                    $latitude,
                    $longitude,
                    $path[$index - 1],
                    $path[$index],
                )['distance'],
            );
        }

        return $closestDistance;
    }

    public static function normalizeLongitude(float $longitude): float
    {
        return fmod(fmod($longitude + 180, 360) + 360, 360) - 180;
    }

    /**
     * @param  array{latitude: float, longitude: float}  $segmentStart
     * @param  array{latitude: float, longitude: float}  $segmentEnd
     * @return array{fraction: float, distance: float}
     */
    private static function projectOntoSegment(
        float $latitude,
        float $longitude,
        array $segmentStart,
        array $segmentEnd,
    ): array {
        $scaleX = self::MARS_RADIUS_KM * cos(deg2rad($latitude)) * M_PI / 180;
        $scaleY = self::MARS_RADIUS_KM * M_PI / 180;
        $startX = self::normalizeLongitude($segmentStart['longitude'] - $longitude) * $scaleX;
        $startY = ($segmentStart['latitude'] - $latitude) * $scaleY;
        $endX = self::normalizeLongitude($segmentEnd['longitude'] - $longitude) * $scaleX;
        $endY = ($segmentEnd['latitude'] - $latitude) * $scaleY;
        $segmentX = $endX - $startX;
        $segmentY = $endY - $startY;
        $segmentLengthSquared = $segmentX ** 2 + $segmentY ** 2;
        $fraction = $segmentLengthSquared === 0.0
            ? 0.0
            : max(0, min(1, -($startX * $segmentX + $startY * $segmentY) / $segmentLengthSquared));

        return [
            'fraction' => $fraction,
            'distance' => hypot($startX + $fraction * $segmentX, $startY + $fraction * $segmentY),
        ];
    }
}

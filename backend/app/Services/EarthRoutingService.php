<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class EarthRoutingService
{
    /**
     * @param  array{latitude: float|int, longitude: float|int}  $start
     * @param  array{latitude: float|int, longitude: float|int}  $destination
     * @return array{geometry: array<int, array{latitude: float, longitude: float}>, steps: array<int, array{instruction: string, name: string, distance_m: int, duration_s: int}>, distance_m: int, duration_s: int}
     */
    public function route(array $start, array $destination): array
    {
        $baseUrl = trim((string) config('services.routing.url'));
        abort_if($baseUrl === '', 503, 'O serviço de rotas terrestres não está configurado.');

        $profile = (string) config('services.routing.profile', 'driving');
        abort_unless(preg_match('/^[a-zA-Z0-9_-]+$/', $profile) === 1, 500, 'O perfil do serviço de rotas é inválido.');

        $coordinates = implode(';', [
            $this->formatCoordinate($start['longitude']),
            $this->formatCoordinate($start['latitude']),
            $this->formatCoordinate($destination['longitude']),
            $this->formatCoordinate($destination['latitude']),
        ]);
        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->get(rtrim($baseUrl, '/')."/route/v1/{$profile}/{$coordinates}", [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'true',
                ]);
        } catch (ConnectionException $exception) {
            abort(502, 'Não foi possível contactar o serviço de rotas terrestres.', previous: $exception);
        }

        abort_if($response->failed(), 502, 'O serviço de rotas terrestres não respondeu corretamente.');

        $routeCode = $response->json('code');
        abort_if(in_array($routeCode, ['NoRoute', 'NoSegment'], true), 422, 'Não foi encontrada uma rota rodoviária entre estes pontos.');
        abort_unless($routeCode === 'Ok' && is_array($route = $response->json('routes.0')), 502, 'O serviço de rotas devolveu uma resposta inválida.');
        abort_if(
            ! is_numeric($route['distance'] ?? null)
            || (float) $route['distance'] < 0
            || ! is_numeric($route['duration'] ?? null)
            || (float) $route['duration'] < 0,
            502,
            'O serviço de rotas devolveu métricas inválidas.',
        );

        $coordinates = $route['geometry']['coordinates'] ?? null;
        abort_if(! is_array($coordinates) || count($coordinates) < 2 || count($coordinates) > 10000, 502, 'O serviço de rotas devolveu uma geometria inválida.');

        $geometry = [];
        foreach ($coordinates as $point) {
            abort_if(
                ! is_array($point)
                || count($point) < 2
                || ! array_key_exists(0, $point)
                || ! array_key_exists(1, $point)
                || ! is_numeric($point[0])
                || ! is_numeric($point[1])
                || (float) $point[0] < -180
                || (float) $point[0] > 180
                || (float) $point[1] < -90
                || (float) $point[1] > 90,
                502,
                'O serviço de rotas devolveu coordenadas inválidas.',
            );
            $geometry[] = ['latitude' => (float) $point[1], 'longitude' => (float) $point[0]];
        }

        $steps = [];
        foreach ($route['legs'] ?? [] as $leg) {
            foreach ($leg['steps'] ?? [] as $step) {
                abort_if(
                    ! is_numeric($step['distance'] ?? null)
                    || (float) $step['distance'] < 0
                    || ! is_numeric($step['duration'] ?? null)
                    || (float) $step['duration'] < 0,
                    502,
                    'O serviço de rotas devolveu instruções inválidas.',
                );
                $name = trim((string) ($step['name'] ?? ''));
                $maneuver = $step['maneuver'] ?? [];
                $type = (string) ($maneuver['type'] ?? 'continue');
                $modifier = (string) ($maneuver['modifier'] ?? '');
                $instruction = $this->instruction($type, $modifier, $name);
                $steps[] = [
                    'instruction' => $instruction,
                    'name' => $name,
                    'distance_m' => max(0, (int) round((float) ($step['distance'] ?? 0))),
                    'duration_s' => max(0, (int) round((float) ($step['duration'] ?? 0))),
                ];
            }
        }

        abort_if($steps === [], 502, 'O serviço de rotas não devolveu instruções para a viagem.');

        return [
            'geometry' => $geometry,
            'steps' => $steps,
            'distance_m' => max(0, (int) round((float) ($route['distance'] ?? 0))),
            'duration_s' => max(0, (int) round((float) ($route['duration'] ?? 0))),
        ];
    }

    private function formatCoordinate(float|int $coordinate): string
    {
        return rtrim(rtrim(number_format((float) $coordinate, 6, '.', ''), '0'), '.');
    }

    private function instruction(string $type, string $modifier, string $name): string
    {
        $direction = [
            'uturn' => 'Faça inversão de marcha',
            'sharp right' => 'Vire acentuadamente à direita',
            'right' => 'Vire à direita',
            'slight right' => 'Vire ligeiramente à direita',
            'straight' => 'Siga em frente',
            'slight left' => 'Vire ligeiramente à esquerda',
            'left' => 'Vire à esquerda',
            'sharp left' => 'Vire acentuadamente à esquerda',
        ][$modifier] ?? null;

        $prefix = match ($type) {
            'depart' => 'Partida',
            'arrive' => 'Chegada ao destino',
            'turn', 'continue', 'new name', 'merge', 'on ramp', 'off ramp', 'fork' => $direction ?? 'Continue',
            'roundabout', 'rotary' => 'Entre na rotunda',
            'exit roundabout', 'exit rotary' => 'Saia da rotunda',
            'end of road' => $direction ?? 'Fim da estrada',
            default => $direction ?? 'Continue',
        };

        return $name === '' ? $prefix : "{$prefix} para {$name}";
    }
}

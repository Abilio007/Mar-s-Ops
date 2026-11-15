<?php

namespace App\Services;

use App\Events\MarsProximityAlert;
use App\Models\HeartBeat;
use App\Models\MarsHazard;
use App\Models\User;
use App\Services\PushNotificationService;

class MarsAlertService
{
    private const WARNING_DISTANCE_KM = 50;

    private const ALARM_DISTANCE_KM = 15;

    private const DISTANCE_TOLERANCE_KM = 0.000001;

    public function __construct(private PushNotificationService $pushNotifications) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluate(User $user, HeartBeat $heartBeat): array
    {
        $hazards = MarsHazard::query()
            ->where('category', 'weather')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->get();

        $activeAlerts = [];
        $nearbyHazardIds = [];

        foreach ($hazards as $hazard) {
            $distance = MarsGeodesy::distanceKm(
                $heartBeat->latitude,
                $heartBeat->longitude,
                $hazard->latitude,
                $hazard->longitude,
            );

            if ($distance > self::WARNING_DISTANCE_KM + self::DISTANCE_TOLERANCE_KM) {
                continue;
            }

            $nearbyHazardIds[] = $hazard->id;
            $severity = $distance <= self::ALARM_DISTANCE_KM + self::DISTANCE_TOLERANCE_KM
                ? 'alarm'
                : 'warning';
            $alert = $user->marsAlerts()->firstOrNew(['mars_hazard_id' => $hazard->id]);
            $shouldBroadcast = ! $alert->exists
                || $alert->status === 'clear'
                || $alert->severity !== $severity;

            $alert->fill([
                'severity' => $severity,
                'distance_km' => round($distance, 2),
                'status' => 'active',
                'last_notified_at' => $shouldBroadcast ? now() : $alert->last_notified_at,
            ]);
            $alert->save();
            $alert->setRelation('hazard', $hazard);

            if ($shouldBroadcast) {
                event(new MarsProximityAlert($alert));
                $this->pushNotifications->send(
                    $user,
                    'riskAlerts',
                    'Novo alerta de risco',
                    'Consulte os alertas na aplicação.',
                );
            }

            $activeAlerts[] = $alert->toArray();
        }

        $staleAlerts = $user->marsAlerts()
            ->where('status', 'active')
            ->when($nearbyHazardIds !== [], fn ($query) => $query->whereNotIn('mars_hazard_id', $nearbyHazardIds))
            ->with('hazard')
            ->get();

        foreach ($staleAlerts as $alert) {
            $alert->forceFill([
                'status' => 'clear',
                'last_notified_at' => now(),
            ])->save();
            event(new MarsProximityAlert($alert));
            $this->pushNotifications->send(
                $user,
                'riskAlerts',
                'Alerta de risco atualizado',
                'Consulte os alertas na aplicação.',
            );
        }

        return $activeAlerts;
    }
}

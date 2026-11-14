<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarsAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $alerts = $request->user()
            ->marsAlerts()
            ->where('status', 'active')
            ->whereHas('hazard', function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('hazard')
            ->latest()
            ->get();

        return response()->json(['data' => $alerts]);
    }

    public function acknowledge(Request $request, string $alertId): JsonResponse
    {
        $alert = $request->user()->marsAlerts()->whereKey($alertId)->firstOrFail();
        $alert->forceFill([
            'acknowledged_at' => $alert->acknowledged_at ? null : now(),
        ])->save();

        return response()->json([
            'data' => [
                'id' => $alert->id,
                'acknowledged' => $alert->acknowledged_at !== null,
            ],
        ]);
    }
}

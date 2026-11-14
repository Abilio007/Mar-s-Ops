<?php

namespace App\Http\Controllers;

use App\Models\MarsRoute;
use App\Services\MarsRoutePlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarsRouteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->marsRoutes()->latest()->paginate(25),
        );
    }

    public function active(Request $request): JsonResponse
    {
        $route = $request->user()->marsRoutes()->where('status', 'active')->latest()->first();

        return response()->json(['data' => $route]);
    }

    public function show(Request $request, string $marsRouteId): JsonResponse
    {
        return response()->json(['data' => $this->ownedRoute($request, $marsRouteId)]);
    }

    public function plan(Request $request, MarsRoutePlanningService $marsRoutePlanningService): JsonResponse
    {
        $validated = $request->validate([
            'start.latitude' => ['required', 'numeric', 'between:-90,90'],
            'start.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $start = $validated['start'];
        $destination = $validated['destination'];
        $plan = $marsRoutePlanningService->plan($start, $destination);

        $route = $request->user()->marsRoutes()->create([
            'id' => (string) Str::uuid(),
            'start_latitude' => $start['latitude'],
            'start_longitude' => $start['longitude'],
            'destination_latitude' => $destination['latitude'],
            'destination_longitude' => $destination['longitude'],
            'candidates' => $plan['candidates'],
            'data_coverage' => $plan['data_coverage'],
        ]);

        return response()->json(['data' => $route], 201);
    }

    public function select(Request $request, string $marsRouteId): JsonResponse
    {
        $route = $this->ownedRoute($request, $marsRouteId);
        abort_if($route->status === 'completed', 409, 'A completed route cannot be restarted.');

        $validated = $request->validate([
            'candidate_index' => ['required', 'integer', 'min:0'],
            'acknowledge_hazards' => ['sometimes', 'boolean'],
        ]);
        abort_if(
            $validated['candidate_index'] >= count($route->candidates),
            422,
            'The selected candidate does not exist.',
        );
        $candidate = $route->candidates[$validated['candidate_index']];
        abort_if(
            ! empty($candidate['hazards_crossed']) && ! ($validated['acknowledge_hazards'] ?? false),
            422,
            'This route intersects mapped hazard zones. Explicitly acknowledge the hazards to continue.',
        );

        $route = DB::transaction(function () use ($request, $route, $validated): MarsRoute {
            $request->user()->marsRoutes()
                ->where('status', 'active')
                ->where('id', '!=', $route->id)
                ->update(['status' => 'cancelled']);

            $route->forceFill([
                'selected_index' => $validated['candidate_index'],
                'status' => 'active',
                'progress_percent' => 0,
                'started_at' => now(),
                'completed_at' => null,
            ])->save();

            return $route;
        });

        return response()->json(['data' => $route]);
    }

    public function complete(Request $request, string $marsRouteId): JsonResponse
    {
        return $this->finish($request, $marsRouteId, 'completed');
    }

    public function abort(Request $request, string $marsRouteId): JsonResponse
    {
        return $this->finish($request, $marsRouteId, 'cancelled');
    }

    private function finish(Request $request, string $marsRouteId, string $status): JsonResponse
    {
        $route = $this->ownedRoute($request, $marsRouteId);
        abort_if($route->status !== 'active', 409, 'Only an active route can be ended.');

        $route->forceFill([
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
            'progress_percent' => $status === 'completed' ? 100 : $route->progress_percent,
        ])->save();

        return response()->json(['data' => $route]);
    }

    private function ownedRoute(Request $request, string $marsRouteId): MarsRoute
    {
        return $request->user()->marsRoutes()->findOrFail($marsRouteId);
    }
}

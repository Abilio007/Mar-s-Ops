<?php

use App\Ai\Agents\AIRouterPlanner;
use App\Ai\Agents\AIUserAssistent;
use App\Events\MarsProximityAlert;
use App\Events\MarsRouteProgressUpdated;
use App\Models\MarsHazard;
use App\Models\MarsMission;
use App\Models\MarsRegion;
use App\Models\MarsTerrainCell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Services\MarsGeodesy;

uses(RefreshDatabase::class);

test('users can register, use their bearer token, and revoke it', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Mars Explorer',
        'email' => 'explorer@example.test',
        'password' => 'a-very-long-password',
        'password_confirmation' => 'a-very-long-password',
    ])->assertCreated();

    $token = $response->json('token');

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'explorer@example.test');

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('authenticated users can continue their own assistant conversation', function () {
    AIUserAssistent::fake(['First answer', 'Follow-up answer']);
    $user = User::factory()->create();

    $first = $this->actingAs($user)
        ->postJson('/api/assistant/chat', ['message' => 'Where is Jezero crater?'])
        ->assertOk()
        ->assertJsonPath('data.message', 'First answer');

    $conversationId = $first->json('data.conversation_id');
    expect($conversationId)->not->toBeNull();

    $this->postJson('/api/assistant/chat', [
        'message' => 'Can you expand on that?',
        'conversation_id' => $conversationId,
    ])
        ->assertOk()
        ->assertJsonPath('data.message', 'Fake response for prompt: Can you expand on that?')
        ->assertJsonPath('data.conversation_id', $conversationId);

    $this->getJson("/api/assistant/conversation?conversation_id={$conversationId}")
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.2.text', 'Can you expand on that?');

    $otherUser = User::factory()->create();
    $this->actingAs($otherUser)
        ->postJson('/api/assistant/chat', [
            'message' => 'Try to access another conversation',
            'conversation_id' => $conversationId,
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson("/api/assistant/conversations/{$conversationId}")
        ->assertOk();

    $this->assertDatabaseCount('agent_conversations', 0);
    $this->assertDatabaseCount('agent_conversation_messages', 0);
});

test('region catalogue and mission records are served from the backend', function () {
    MarsRegion::query()->create([
        'id' => 'jezero',
        'name' => 'Cratera Jezero',
        'latitude' => 18.38,
        'longitude' => 77.58,
        'source_name' => 'NASA JPL Mars Trek',
        'source_url' => 'https://trek.nasa.gov/mars/',
    ]);
    MarsRegion::query()->create([
        'id' => 'gale',
        'name' => 'Cratera Gale',
        'latitude' => -5.4,
        'longitude' => 137.8,
        'source_name' => 'NASA JPL Mars Trek',
        'source_url' => 'https://trek.nasa.gov/mars/',
    ]);

    $this->getJson('/api/data/regions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 'gale');
    $this->getJson('/api/data/catalog')
        ->assertOk()
        ->assertJsonPath('data.missionTypes.0.value', 'scientific');

    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/missions', [
        'name' => 'Jezero Survey',
        'originId' => 'jezero',
        'destinationId' => 'gale',
        'waypoints' => [],
        'departureDate' => '2026-12-01',
        'arrivalDate' => '2026-12-10',
        'participants' => 4,
        'missionType' => 'scientific',
        'objective' => 'Collect samples.',
        'riskTolerance' => 'low',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'planned')
        ->assertJsonPath('data.progress', 0)
        ->assertJsonPath('data.estimatedDays', max(1, (int) ceil(
            MarsGeodesy::distanceKm(18.38, 77.58, -5.4, 137.8) / 85 / 24.62,
        )));

    $missionId = $response->json('data.id');
    $this->getJson('/api/missions')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs(User::factory()->create())
        ->deleteJson("/api/missions/{$missionId}")
        ->assertNotFound();

    $this->actingAs($user)
        ->patchJson("/api/missions/{$missionId}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
    $this->postJson('/api/heartbeats', ['latitude' => -5.4, 'longitude' => 137.8])
        ->assertCreated();
    $this->getJson('/api/missions')
        ->assertOk()
        ->assertJsonPath('data.0.progress', 99);

    $this->deleteJson("/api/missions/{$missionId}")->assertOk();
    $this->assertDatabaseCount('mars_missions', 0);
});

test('alert acknowledgements are persisted and scoped to their owner', function () {
    $hazard = MarsHazard::query()->create([
        'external_id' => 'ack-hazard',
        'source_name' => 'pds_weather',
        'category' => 'weather',
        'title' => 'Dust event',
        'latitude' => 0,
        'longitude' => 0,
        'radius_km' => 0,
        'source_url' => 'https://pds-geosciences.wustl.edu/missions/insight/',
        'expires_at' => now()->addHour(),
    ]);
    $user = User::factory()->create();
    $alert = $user->marsAlerts()->create([
        'mars_hazard_id' => $hazard->id,
        'severity' => 'warning',
        'distance_km' => 20,
    ]);

    $this->actingAs($user)
        ->postJson("/api/alerts/{$alert->id}/acknowledge")
        ->assertOk()
        ->assertJsonPath('data.acknowledged', true);
    $this->assertNotNull($alert->fresh()->acknowledged_at);

    $this->actingAs(User::factory()->create())
        ->postJson("/api/alerts/{$alert->id}/acknowledge")
        ->assertNotFound();
});

test('heartbeats create warning and alarm alerts at the requested distances', function () {
    Event::fake([MarsProximityAlert::class, MarsRouteProgressUpdated::class]);
    $user = User::factory()->create();
    MarsHazard::query()->create([
        'external_id' => 'archived-dust-001',
        'source_name' => 'pds_weather',
        'category' => 'weather',
        'title' => 'Archived weather without an expiry',
        'latitude' => 0,
        'longitude' => 0,
        'radius_km' => 0,
        'source_url' => 'https://pds-geosciences.wustl.edu/missions/insight/',
        'observed_at' => now()->subYear(),
    ]);
    $hazard = MarsHazard::query()->create([
        'external_id' => 'dust-001',
        'source_name' => 'pds_weather',
        'category' => 'weather',
        'title' => 'Dust event',
        'latitude' => 0,
        'longitude' => 0,
        'radius_km' => 0,
        'source_url' => 'https://pds-geosciences.wustl.edu/missions/insight/',
        'observed_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    $longitudeAtFiftyKilometers = rad2deg(50 / 3389.5);
    $longitudeAtFifteenKilometers = rad2deg(15 / 3389.5);
    $longitudeJustOutsideFiftyKilometers = rad2deg(50.001 / 3389.5);
    $longitudeJustOutsideFifteenKilometers = rad2deg(15.001 / 3389.5);

    $this->actingAs($user)
        ->postJson('/api/heartbeats', ['latitude' => 0, 'longitude' => $longitudeJustOutsideFiftyKilometers])
        ->assertCreated()
        ->assertJsonCount(0, 'alerts');

    $this->postJson('/api/heartbeats', ['latitude' => 0, 'longitude' => $longitudeAtFiftyKilometers])
        ->assertCreated()
        ->assertJsonPath('alerts.0.severity', 'warning')
        ->assertJsonPath('heartbeat_interval_seconds', 60);

    $this->postJson('/api/heartbeats', ['latitude' => 0, 'longitude' => $longitudeJustOutsideFifteenKilometers])
        ->assertCreated()
        ->assertJsonPath('alerts.0.severity', 'warning');

    $this->postJson('/api/heartbeats', ['latitude' => 0, 'longitude' => $longitudeAtFifteenKilometers])
        ->assertCreated()
        ->assertJsonPath('alerts.0.severity', 'alarm');

    $this->assertDatabaseHas('heart_beats', ['user_id' => $user->id]);
    $this->assertDatabaseHas('mars_alerts', [
        'user_id' => $user->id,
        'mars_hazard_id' => $hazard->id,
        'severity' => 'alarm',
        'status' => 'active',
    ]);
    $this->getJson('/api/alerts')
        ->assertOk()
        ->assertJsonPath('data.0.severity', 'alarm');

    Event::assertDispatchedTimes(MarsProximityAlert::class, 2);
});

test('expired hazards are excluded from active alert responses', function () {
    $user = User::factory()->create();
    $hazard = MarsHazard::query()->create([
        'external_id' => 'expired-weather-001',
        'source_name' => 'pds_weather',
        'category' => 'weather',
        'title' => 'Expired dust event',
        'latitude' => 0,
        'longitude' => 0,
        'radius_km' => 0,
        'source_url' => 'https://pds-geosciences.wustl.edu/missions/insight/',
        'expires_at' => now()->subMinute(),
    ]);
    $user->marsAlerts()->create([
        'mars_hazard_id' => $hazard->id,
        'severity' => 'alarm',
        'distance_km' => 1,
        'status' => 'active',
    ]);

    $this->actingAs($user)->getJson('/api/alerts')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('AI-generated routes use imported NASA data and can be selected and followed', function () {
    Event::fake([MarsProximityAlert::class, MarsRouteProgressUpdated::class]);
    AIRouterPlanner::fake([[
        'candidates' => [
            [
                'name' => 'left_detour',
                'path' => [
                    ['latitude' => 0, 'longitude' => -0.5],
                    ['latitude' => 0.15, 'longitude' => 0],
                    ['latitude' => 0, 'longitude' => 0.5],
                ],
                'score' => 96,
                'risk_level' => 'low',
                'science_value' => 70,
                'rationale' => 'The left detour avoids the mapped crater.',
                'uncertainties' => ['No high-resolution elevation raster was provided.'],
            ],
            [
                'name' => 'right_detour',
                'path' => [
                    ['latitude' => 0, 'longitude' => -0.5],
                    ['latitude' => -0.15, 'longitude' => 0],
                    ['latitude' => 0, 'longitude' => 0.5],
                ],
                'score' => 82,
                'risk_level' => 'moderate',
                'science_value' => 75,
                'rationale' => 'The right detour is longer and has limited terrain coverage.',
                'uncertainties' => ['Terrain samples are sparse.'],
            ],
            [
                'name' => 'direct',
                'path' => [
                    ['latitude' => 0, 'longitude' => -0.5],
                    ['latitude' => 0, 'longitude' => 0.5],
                ],
                'score' => 15,
                'risk_level' => 'high',
                'science_value' => 90,
                'rationale' => 'The direct route intersects the crater hazard zone.',
                'uncertainties' => [],
            ],
        ],
    ]]);
    $user = User::factory()->create();
    MarsHazard::query()->create([
        'external_id' => 'crater-001',
        'source_name' => 'usgs_geology',
        'category' => 'geology',
        'title' => 'Crater hazard zone',
        'latitude' => 0,
        'longitude' => 0,
        'radius_km' => 2,
        'source_url' => 'https://astrogeology-usgs.hub.arcgis.com/pages/interactive-maps-mars',
    ]);
    MarsTerrainCell::query()->create([
        'external_id' => 'mola-cell-001',
        'source_name' => 'mars_trek',
        'latitude' => 0,
        'longitude' => 0,
        'elevation_m' => 100,
        'slope_degrees' => 30,
        'roughness_m' => 2,
        'cell_size_km' => 10,
        'source_url' => 'https://trek.nasa.gov/mars/',
    ]);

    $plan = $this->actingAs($user)->postJson('/api/routes/plan', [
        'start' => ['latitude' => 0, 'longitude' => -0.5],
        'destination' => ['latitude' => 0, 'longitude' => 0.5],
    ])->assertCreated();

    $routeId = $plan->json('data.id');
    expect($plan->json('data.candidates.0.name'))->toBe('left_detour');
    expect($plan->json('data.candidates.0.score'))->toBe(96);
    expect($plan->json('data.candidates.0.rationale'))->toContain('crater');
    expect(collect($plan->json('data.candidates'))->firstWhere('name', 'direct')['hazards_crossed'])->not->toBe([]);
    expect(collect($plan->json('data.candidates'))->firstWhere('name', 'direct')['terrain_samples'])->toBe(1);
    expect($plan->json('data.data_coverage.terrain_verified'))->toBeFalse();

    AIRouterPlanner::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Crater hazard zone')
        && str_contains($prompt->prompt, 'slope_degrees'));

    $this->postJson("/api/routes/{$routeId}/select", ['candidate_index' => 2])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This route intersects mapped hazard zones. Explicitly acknowledge the hazards to continue.');

    $this->postJson("/api/routes/{$routeId}/select", ['candidate_index' => 0])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->postJson("/api/routes/{$routeId}/select", [
        'candidate_index' => 2,
        'acknowledge_hazards' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.selected_index', 2);

    $this->postJson('/api/heartbeats', ['latitude' => 0, 'longitude' => -0.5])
        ->assertCreated()
        ->assertJsonPath('route_progress.status', 'active');

    Event::assertDispatched(MarsRouteProgressUpdated::class);

    AIRouterPlanner::fake([[
        'candidates' => [
            [
                'name' => 'already_at_destination',
                'path' => [
                    ['latitude' => 0, 'longitude' => 1],
                    ['latitude' => 0, 'longitude' => 1],
                ],
                'score' => 100,
                'risk_level' => 'low',
                'science_value' => 0,
                'rationale' => 'The starting point is already the destination.',
                'uncertainties' => ['No terrain samples were provided.'],
            ],
        ],
    ]]);

    $this->postJson('/api/routes/plan', [
        'start' => ['latitude' => 0, 'longitude' => 1],
        'destination' => ['latitude' => 0, 'longitude' => 1],
    ])
        ->assertCreated()
        ->assertJsonCount(1, 'data.candidates')
        ->assertJsonPath('data.candidates.0.name', 'already_at_destination');
});

test('hazard ingestion requires a token and accepts only trusted source URLs', function () {
    config(['services.mars.ingest_token' => 'test-ingest-token']);
    $record = [
        'external_id' => 'dust-002',
        'source_name' => 'pds_weather',
        'category' => 'weather',
        'title' => 'Archived dust observation',
        'latitude' => 12.5,
        'longitude' => 45.5,
        'radius_km' => 20,
        'source_url' => 'https://pds-geosciences.wustl.edu/missions/insight/',
        'observed_at' => now()->toIso8601String(),
        'expires_at' => now()->addDay()->toIso8601String(),
    ];

    $this->postJson('/api/data/hazards', ['records' => [$record]])
        ->assertUnauthorized();

    $this->withHeader('X-Mars-Data-Token', 'test-ingest-token')
        ->postJson('/api/data/hazards', ['records' => [$record]])
        ->assertOk()
        ->assertJsonPath('upserted', 1);

    $record['source_url'] = 'https://example.test/forged-source';
    $this->withHeader('X-Mars-Data-Token', 'test-ingest-token')
        ->postJson('/api/data/hazards', ['records' => [$record]])
        ->assertUnprocessable();

    $this->assertDatabaseCount('mars_hazards', 1);

    $this->withHeader('X-Mars-Data-Token', 'test-ingest-token')
        ->postJson('/api/data/terrain', ['records' => [[
            'external_id' => 'mola-cell-001',
            'source_name' => 'mars_trek',
            'latitude' => 10,
            'longitude' => 20,
            'elevation_m' => 1200,
            'slope_degrees' => 7.5,
            'roughness_m' => 1.2,
            'cell_size_km' => 1,
            'source_url' => 'https://trek.nasa.gov/mars/',
        ]]])
        ->assertOk()
        ->assertJsonPath('upserted', 1);

    $this->assertDatabaseCount('mars_terrain_cells', 1);
});

test('the obsolete NASA image proxy endpoint is no longer exposed', function () {
    $this->getJson('/api/data/images?query=perseverance')->assertNotFound();
});

test('invalid AI routes are rejected instead of silently using a fallback', function () {
    AIRouterPlanner::fake([['candidates' => []]]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/routes/plan', [
            'start' => ['latitude' => -4.5, 'longitude' => 137.4],
            'destination' => ['latitude' => -4.6, 'longitude' => 137.6],
        ])
        ->assertStatus(502);

    AIRouterPlanner::fake([[
        'candidates' => [[
            'name' => 'unsupported_detour',
            'path' => [
                ['latitude' => -4.5, 'longitude' => 137.4],
                ['latitude' => 20, 'longitude' => 137.5],
                ['latitude' => -4.6, 'longitude' => 137.6],
            ],
            'score' => 90,
            'risk_level' => 'low',
            'science_value' => 50,
            'rationale' => 'This route claims an unsupported detour.',
            'uncertainties' => [],
        ]],
    ]]);

    $this->actingAs($user)
        ->postJson('/api/routes/plan', [
            'start' => ['latitude' => -4.5, 'longitude' => 137.4],
            'destination' => ['latitude' => -4.6, 'longitude' => 137.6],
        ])
        ->assertStatus(502);

    $this->assertDatabaseCount('mars_routes', 0);
});

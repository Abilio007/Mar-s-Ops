<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HeartBeatController;
use App\Http\Controllers\MarsAssistantController;
use App\Http\Controllers\MarsAlertController;
use App\Http\Controllers\MarsDataController;
use App\Http\Controllers\MarsHazardController;
use App\Http\Controllers\MarsMissionController;
use App\Http\Controllers\MarsRegionController;
use App\Http\Controllers\MarsRouteController;
use App\Http\Controllers\MarsTerrainController;
use App\Http\Middleware\MarsDataIngestToken;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/data/sources', [MarsDataController::class, 'sources']);
Route::get('/data/catalog', [MarsDataController::class, 'catalog']);
Route::get('/data/regions', [MarsRegionController::class, 'index']);
Route::post('/data/hazards', [MarsHazardController::class, 'ingest'])
    ->middleware([MarsDataIngestToken::class, 'throttle:10,1']);
Route::post('/data/terrain', [MarsTerrainController::class, 'ingest'])
    ->middleware([MarsDataIngestToken::class, 'throttle:10,1']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/assistant/conversation', [MarsAssistantController::class, 'history']);
    Route::post('/assistant/chat', [MarsAssistantController::class, 'chat'])->middleware('throttle:10,1');
    Route::delete('/assistant/conversations/{conversationId}', [MarsAssistantController::class, 'destroyConversation'])
        ->middleware('throttle:10,1');
    Route::get('/data/hazards', [MarsHazardController::class, 'index']);
    Route::get('/data/terrain', [MarsTerrainController::class, 'index']);
    Route::get('/alerts', [MarsAlertController::class, 'index']);
    Route::post('/alerts/{alertId}/acknowledge', [MarsAlertController::class, 'acknowledge']);

    Route::get('/missions', [MarsMissionController::class, 'index']);
    Route::post('/missions', [MarsMissionController::class, 'store']);
    Route::put('/missions/{missionId}', [MarsMissionController::class, 'update']);
    Route::delete('/missions/{missionId}', [MarsMissionController::class, 'destroy']);
    Route::patch('/missions/{missionId}/status', [MarsMissionController::class, 'updateStatus']);
    Route::patch('/missions/{missionId}/checklist/{itemId}', [MarsMissionController::class, 'updateChecklistItem']);

    Route::get('/heartbeats', [HeartBeatController::class, 'index']);
    Route::get('/heartbeats/latest', [HeartBeatController::class, 'latest']);
    Route::post('/heartbeats', [HeartBeatController::class, 'store'])->middleware('throttle:10,1');

    Route::post('/routes/plan', [MarsRouteController::class, 'plan'])->middleware('throttle:10,1');
    Route::get('/routes', [MarsRouteController::class, 'index']);
    Route::get('/routes/active', [MarsRouteController::class, 'active']);
    Route::get('/routes/{marsRouteId}', [MarsRouteController::class, 'show']);
    Route::post('/routes/{marsRouteId}/select', [MarsRouteController::class, 'select']);
    Route::post('/routes/{marsRouteId}/complete', [MarsRouteController::class, 'complete']);
    Route::post('/routes/{marsRouteId}/abort', [MarsRouteController::class, 'abort']);
});

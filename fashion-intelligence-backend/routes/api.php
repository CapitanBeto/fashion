<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TrendController;
use App\Http\Controllers\Api\ExperimentController;
use App\Http\Controllers\Api\SourceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fashion Intelligence — API Routes
|--------------------------------------------------------------------------
|
| All routes are versioned under /api/v1.
| Authentication via Laravel Sanctum (Bearer token).
|
*/

// ─── Public ──────────────────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('system/health', [ExperimentController::class, 'health']);

    // ─── Authenticated ────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me',      [AuthController::class, 'me']);

        // ── Trends ──────────────────────────────────────────────────────────
        Route::prefix('trends')->group(function () {
            Route::get('/',                          [TrendController::class, 'index']);
            Route::get('/rising',                    [TrendController::class, 'rising']);
            Route::get('/opportunities',             [TrendController::class, 'topOpportunities']);
            Route::get('/{trend}',                   [TrendController::class, 'show']);
            Route::get('/{trend}/snapshots',         [TrendController::class, 'snapshots']);
            Route::get('/{trend}/opportunities',     [TrendController::class, 'opportunities']);
        });

        // ── Experiments ─────────────────────────────────────────────────────
        Route::prefix('experiments')->group(function () {
            Route::get('/',                          [ExperimentController::class, 'index']);
            Route::post('/{key}/run',                [ExperimentController::class, 'run']);
            Route::get('/{key}/runs',                [ExperimentController::class, 'runs']);
            Route::get('/{key}/status',              [ExperimentController::class, 'status']);
            Route::post('/{key}/rescore',            [ExperimentController::class, 'rescore']);
        });

        // ── Scrape Runs ─────────────────────────────────────────────────────
        Route::get('scrape-runs/{scrapeRun}', [ExperimentController::class, 'showRun']);

        // ── Sources ─────────────────────────────────────────────────────────
        Route::prefix('sources')->group(function () {
            Route::get('/',                              [SourceController::class, 'index']);
            Route::post('/',                             [SourceController::class, 'store']);
            Route::patch('/{source}',                    [SourceController::class, 'update']);
            Route::get('/{source}/targets',              [SourceController::class, 'targets']);
            Route::post('/{source}/targets',             [SourceController::class, 'addTarget']);
            Route::patch('/targets/{target}',            [SourceController::class, 'updateTarget']);
        });

    });
});

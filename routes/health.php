<?php

use ArtisanPackUI\CMSFramework\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
|
| These routes provide various health check endpoints for monitoring
| the ArtisanPack UI CMS Framework application status, dependencies,
| and overall system health.
|
*/

// Basic ping endpoint - lightweight availability check
Route::get('/ping', [HealthCheckController::class, 'ping'])
    ->name('health.ping');

// Comprehensive health check endpoint
Route::get('/health', [HealthCheckController::class, 'health'])
    ->name('health.check');

// Database-specific health check
Route::get('/health/database', [HealthCheckController::class, 'database'])
    ->name('health.database');

// Dependencies health check (cache, queue, storage)
Route::get('/health/dependencies', [HealthCheckController::class, 'dependencies'])
    ->name('health.dependencies');

// Kubernetes/Docker readiness probe
Route::get('/ready', [HealthCheckController::class, 'ready'])
    ->name('health.ready');

// Kubernetes/Docker liveness probe  
Route::get('/live', [HealthCheckController::class, 'live'])
    ->name('health.live');

// Alternative endpoints for different naming conventions
Route::get('/healthz', [HealthCheckController::class, 'health'])
    ->name('health.healthz');

Route::get('/readyz', [HealthCheckController::class, 'ready'])
    ->name('health.readyz');

Route::get('/livez', [HealthCheckController::class, 'live'])
    ->name('health.livez');
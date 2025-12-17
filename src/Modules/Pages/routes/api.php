<?php

/**
 * Pages Module API Routes
 *
 * @since 2.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Controllers\PageCategoryController;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Controllers\PageController;
use ArtisanPackUI\CMSFramework\Modules\Pages\Http\Controllers\PageTagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pages API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('pages')->middleware('auth')->group(function () {
    Route::get('/', [PageController::class, 'index']);
    Route::post('/', [PageController::class, 'store']);
    Route::get('/tree', [PageController::class, 'tree']);
    Route::post('/reorder', [PageController::class, 'reorder']);
    Route::post('/{id}/move', [PageController::class, 'move']);
    Route::get('/{id}', [PageController::class, 'show']);
    Route::put('/{id}', [PageController::class, 'update']);
    Route::delete('/{id}', [PageController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Page Categories API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('page-categories')->middleware('auth')->group(function () {
    Route::get('/', [PageCategoryController::class, 'index']);
    Route::post('/', [PageCategoryController::class, 'store']);
    Route::get('/{id}', [PageCategoryController::class, 'show']);
    Route::put('/{id}', [PageCategoryController::class, 'update']);
    Route::delete('/{id}', [PageCategoryController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Page Tags API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('page-tags')->middleware('auth')->group(function () {
    Route::get('/', [PageTagController::class, 'index']);
    Route::post('/', [PageTagController::class, 'store']);
    Route::get('/{id}', [PageTagController::class, 'show']);
    Route::put('/{id}', [PageTagController::class, 'update']);
    Route::delete('/{id}', [PageTagController::class, 'destroy']);
});

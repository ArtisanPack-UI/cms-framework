<?php

/**
 * ContentTypes Module API Routes
 *
 * @since 2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\ContentTypes
 */

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Controllers\ContentTypeController;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Controllers\CustomFieldController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Content Types API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('content-types')->middleware('auth')->group(function () {
    Route::get('/', [ContentTypeController::class, 'index']);
    Route::post('/', [ContentTypeController::class, 'store']);
    Route::get('/{slug}', [ContentTypeController::class, 'show']);
    Route::put('/{slug}', [ContentTypeController::class, 'update']);
    Route::delete('/{slug}', [ContentTypeController::class, 'destroy']);
    Route::get('/{slug}/custom-fields', [ContentTypeController::class, 'customFields']);
});

/*
|--------------------------------------------------------------------------
| Custom Fields API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('custom-fields')->middleware('auth')->group(function () {
    Route::get('/', [CustomFieldController::class, 'index']);
    Route::post('/', [CustomFieldController::class, 'store']);
    Route::get('/{id}', [CustomFieldController::class, 'show']);
    Route::put('/{id}', [CustomFieldController::class, 'update']);
    Route::delete('/{id}', [CustomFieldController::class, 'destroy']);
});

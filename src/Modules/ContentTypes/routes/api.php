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
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Controllers\TaxonomyController;
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

/*
|--------------------------------------------------------------------------
| Taxonomies API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('taxonomies')->middleware('auth')->group(function () {
    Route::get('/', [TaxonomyController::class, 'index']);
    Route::post('/', [TaxonomyController::class, 'store']);
    Route::get('/{slug}', [TaxonomyController::class, 'show']);
    Route::put('/{slug}', [TaxonomyController::class, 'update']);
    Route::delete('/{slug}', [TaxonomyController::class, 'destroy']);
    Route::get('/content-type/{contentTypeSlug}', [TaxonomyController::class, 'byContentType']);
});

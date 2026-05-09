<?php

declare(strict_types=1);

/**
 * API Routes for the CMS Framework Settings Module.
 *
 * This file defines the API routes for settings management operations,
 * providing RESTful endpoints for CRUD operations on settings resources.
 *
 * @since   1.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Http\Controllers\SettingController;
use ArtisanPackUI\CMSFramework\Modules\Settings\Http\Controllers\SiteSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    // The site-meta endpoints have to register before the apiResource so
    // `/settings/site` doesn't get caught by `/settings/{setting}` with
    // the literal key `site`.
    Route::get('settings/site', [SiteSettingController::class, 'show'])->name('settings.site.show');
    Route::put('settings/site', [SiteSettingController::class, 'update'])->name('settings.site.update');

    Route::apiResource('settings', SettingController::class);
});

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

    // By-key bulk update. Registered before the apiResource so it is matched
    // ahead of the ID/key-addressed `PUT settings/{setting}` update, and routes
    // each value through SettingsManager (applying sanitizers + types) instead
    // of writing to the model directly.
    Route::put('settings', [SettingController::class, 'bulkUpdate'])->name('settings.bulk-update');

    Route::apiResource('settings', SettingController::class);
});

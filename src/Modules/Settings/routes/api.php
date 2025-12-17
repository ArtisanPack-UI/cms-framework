<?php

/**
 * API Routes for the CMS Framework Settings Module.
 *
 * This file defines the API routes for settings management operations,
 * providing RESTful endpoints for CRUD operations on settings resources.
 *
 * @since   1.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::apiResource('settings', SettingController::class);
});

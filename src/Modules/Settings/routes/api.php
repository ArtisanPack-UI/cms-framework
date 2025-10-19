<?php

/**
 * API Routes for the CMS Framework Settings Module.
 *
 * This file defines the API routes for user management operations,
 * providing RESTful endpoints for CRUD operations on user resources.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Settings
 */

use Illuminate\Support\Facades\Route;
use ArtisanPackUI\CMSFramework\Modules\Settings\Http\Controllers\SettingController;

Route::apiResource( 'settings', SettingController::class );
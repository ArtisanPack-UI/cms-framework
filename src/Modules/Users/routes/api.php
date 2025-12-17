<?php

/**
 * API Routes for the CMS Framework Users Module.
 *
 * This file defines the API routes for user management operations,
 * providing RESTful endpoints for CRUD operations on user resources.
 *
 * @since   1.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\PermissionController;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\RoleController;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::apiResource('users', UserController::class);
Route::apiResource('roles', RoleController::class);
Route::apiResource('permissions', PermissionController::class);

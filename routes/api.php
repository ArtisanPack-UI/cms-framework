<?php

use ArtisanPackUI\CMSFramework\Http\Controllers\UserController;
use ArtisanPackUI\CMSFramework\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

// User routes
Route::apiResource('users', UserController::class);

// Role routes
Route::apiResource('roles', RoleController::class);

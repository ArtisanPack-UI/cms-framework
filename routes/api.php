<?php

use ArtisanPackUI\CMSFramework\Http\Controllers\RoleController;
use ArtisanPackUI\CMSFramework\Http\Controllers\SettingController;
use ArtisanPackUI\CMSFramework\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware( 'api' )->prefix( 'cms' )->group( function () {
    // User routes
    Route::apiResource( 'users', UserController::class );

    // Role routes
    Route::apiResource( 'roles', RoleController::class );

    // Settings routs
    Route::apiResource( 'settings', SettingController::class );
} );

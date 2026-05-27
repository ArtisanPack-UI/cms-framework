<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\PermissionController;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\RoleController;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// `auth` (no guard suffix) lets the consuming app pick its own
// preferred guard via `auth.defaults.guard` — sanctum, session, or
// otherwise. The bug fix here is that the apiResource routes are now
// **gated**; the original implementation registered them without any
// auth middleware at all.
Route::middleware( 'auth' )->group( function (): void {
    Route::apiResource( 'users', UserController::class );
    Route::apiResource( 'roles', RoleController::class );
    Route::apiResource( 'permissions', PermissionController::class );

    Route::post( 'users/bulk', [UserController::class, 'bulk'] );
} );

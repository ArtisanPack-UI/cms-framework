<?php

declare( strict_types=1 );

/**
 * Dynamic Content module API routes.
 *
 * @since 2.4.0
 */

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Controllers\DynamicContentRecordController;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Controllers\DynamicContentResolverController;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Controllers\DynamicContentTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix( 'dynamic-content' )->middleware( 'auth' )->group( function (): void {
    Route::get( 'types', [ DynamicContentTypeController::class, 'index' ] );
    Route::get( 'types/registered', [ DynamicContentTypeController::class, 'registered' ] );
    Route::post( 'types', [ DynamicContentTypeController::class, 'store' ] );
    Route::get( 'types/{type}', [ DynamicContentTypeController::class, 'show' ] );
    Route::put( 'types/{type}', [ DynamicContentTypeController::class, 'update' ] );
    Route::delete( 'types/{type}', [ DynamicContentTypeController::class, 'destroy' ] );

    Route::get( 'types/{type}/records', [ DynamicContentRecordController::class, 'index' ] );
    Route::post( 'types/{type}/records', [ DynamicContentRecordController::class, 'store' ] );
    Route::get( 'types/{type}/records/{record}', [ DynamicContentRecordController::class, 'show' ] );
    Route::put( 'types/{type}/records/{record}', [ DynamicContentRecordController::class, 'update' ] );
    Route::delete( 'types/{type}/records/{record}', [ DynamicContentRecordController::class, 'destroy' ] );

    Route::post( 'resolve', [ DynamicContentResolverController::class, 'resolve' ] )
        ->middleware( 'throttle:60,1' );
} );

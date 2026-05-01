<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\TemplatePartsController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\TemplatesController;
use Illuminate\Support\Facades\Route;

Route::prefix( 'api/v1' )
    ->middleware( 'auth' )
    ->group( function (): void {
        Route::prefix( 'templates' )->group( function (): void {
            Route::get( '/', [ TemplatesController::class, 'index' ] )->name( 'api.templates.index' );
            Route::post( '/', [ TemplatesController::class, 'store' ] )->name( 'api.templates.store' );
            Route::get( '{slug}', [ TemplatesController::class, 'show' ] )->name( 'api.templates.show' );
            Route::put( '{slug}', [ TemplatesController::class, 'update' ] )->name( 'api.templates.update' );
            Route::delete( '{slug}', [ TemplatesController::class, 'destroy' ] )->name( 'api.templates.destroy' );
        } );

        Route::prefix( 'template-parts' )->group( function (): void {
            Route::get( '/', [ TemplatePartsController::class, 'index' ] )->name( 'api.template-parts.index' );
            Route::post( '/', [ TemplatePartsController::class, 'store' ] )->name( 'api.template-parts.store' );
            Route::get( '{slug}', [ TemplatePartsController::class, 'show' ] )->name( 'api.template-parts.show' );
            Route::put( '{slug}', [ TemplatePartsController::class, 'update' ] )->name( 'api.template-parts.update' );
            Route::delete( '{slug}', [ TemplatePartsController::class, 'destroy' ] )->name( 'api.template-parts.destroy' );
        } );
    } );

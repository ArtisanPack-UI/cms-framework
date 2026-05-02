<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\BlockPatternsController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\BlocksController;
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

        Route::prefix( 'blocks' )->group( function (): void {
            Route::get( '/', [ BlocksController::class, 'index' ] )->name( 'api.blocks.index' );
            Route::post( '/', [ BlocksController::class, 'store' ] )->name( 'api.blocks.store' );
            Route::get( '{slug}', [ BlocksController::class, 'show' ] )->name( 'api.blocks.show' );
            Route::put( '{slug}', [ BlocksController::class, 'update' ] )->name( 'api.blocks.update' );
            Route::delete( '{slug}', [ BlocksController::class, 'destroy' ] )->name( 'api.blocks.destroy' );
        } );

        Route::prefix( 'block-patterns/patterns' )->group( function (): void {
            Route::get( '/', [ BlockPatternsController::class, 'index' ] )->name( 'api.block-patterns.index' );
            Route::post( '/', [ BlockPatternsController::class, 'store' ] )->name( 'api.block-patterns.store' );
            Route::get( '{slug}', [ BlockPatternsController::class, 'show' ] )->name( 'api.block-patterns.show' );
            Route::put( '{slug}', [ BlockPatternsController::class, 'update' ] )->name( 'api.block-patterns.update' );
            Route::delete( '{slug}', [ BlockPatternsController::class, 'destroy' ] )->name( 'api.block-patterns.destroy' );
        } );
    } );

<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\BlockPatternsController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\BlocksController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\GlobalStylesController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\MenuItemsController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\MenuLocationsController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\MenusController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\TemplatePartsController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\TemplatesController;
use Illuminate\Support\Facades\Route;

Route::prefix( 'api/v1' )
    ->middleware( 'auth:sanctum' )
    ->group( function (): void {
        Route::prefix( 'templates' )->group( function (): void {
            Route::get( '/', [TemplatesController::class, 'index'] )->name( 'api.templates.index' );
            Route::post( '/', [TemplatesController::class, 'store'] )->name( 'api.templates.store' );
            Route::get( '{slug}', [TemplatesController::class, 'show'] )->name( 'api.templates.show' );
            Route::put( '{slug}', [TemplatesController::class, 'update'] )->name( 'api.templates.update' );
            Route::delete( '{slug}', [TemplatesController::class, 'destroy'] )->name( 'api.templates.destroy' );
        } );

        Route::prefix( 'template-parts' )->group( function (): void {
            Route::get( '/', [TemplatePartsController::class, 'index'] )->name( 'api.template-parts.index' );
            Route::post( '/', [TemplatePartsController::class, 'store'] )->name( 'api.template-parts.store' );
            Route::get( '{slug}', [TemplatePartsController::class, 'show'] )->name( 'api.template-parts.show' );
            Route::put( '{slug}', [TemplatePartsController::class, 'update'] )->name( 'api.template-parts.update' );
            Route::delete( '{slug}', [TemplatePartsController::class, 'destroy'] )->name( 'api.template-parts.destroy' );
        } );

        Route::prefix( 'blocks' )->group( function (): void {
            Route::get( '/', [BlocksController::class, 'index'] )->name( 'api.blocks.index' );
            Route::post( '/', [BlocksController::class, 'store'] )->name( 'api.blocks.store' );
            Route::get( '{slug}', [BlocksController::class, 'show'] )->name( 'api.blocks.show' );
            Route::put( '{slug}', [BlocksController::class, 'update'] )->name( 'api.blocks.update' );
            Route::delete( '{slug}', [BlocksController::class, 'destroy'] )->name( 'api.blocks.destroy' );
        } );

        Route::prefix( 'block-patterns/patterns' )->group( function (): void {
            Route::get( '/', [BlockPatternsController::class, 'index'] )->name( 'api.block-patterns.index' );
            Route::post( '/', [BlockPatternsController::class, 'store'] )->name( 'api.block-patterns.store' );
            Route::get( '{slug}', [BlockPatternsController::class, 'show'] )->name( 'api.block-patterns.show' );
            Route::put( '{slug}', [BlockPatternsController::class, 'update'] )->name( 'api.block-patterns.update' );
            Route::delete( '{slug}', [BlockPatternsController::class, 'destroy'] )->name( 'api.block-patterns.destroy' );
        } );

        Route::prefix( 'global-styles' )->group( function (): void {
            Route::get( 'variations', [GlobalStylesController::class, 'variations'] )->name( 'api.global-styles.variations' );
            Route::get( 'css', [GlobalStylesController::class, 'css'] )->name( 'api.global-styles.css' );
            Route::get( '/', [GlobalStylesController::class, 'show'] )->name( 'api.global-styles.show' );
            Route::put( '/', [GlobalStylesController::class, 'update'] )->name( 'api.global-styles.update' );
            Route::delete( '/', [GlobalStylesController::class, 'destroy'] )->name( 'api.global-styles.destroy' );
        } );

        Route::prefix( 'menus' )->group( function (): void {
            Route::get( '/', [MenusController::class, 'index'] )->name( 'api.menus.index' );
            Route::post( '/', [MenusController::class, 'store'] )->name( 'api.menus.store' );
            Route::get( '{idOrSlug}', [MenusController::class, 'show'] )->name( 'api.menus.show' );
            Route::put( '{idOrSlug}', [MenusController::class, 'update'] )->name( 'api.menus.update' );
            Route::delete( '{idOrSlug}', [MenusController::class, 'destroy'] )->name( 'api.menus.destroy' );
        } );

        Route::prefix( 'menu-items' )->group( function (): void {
            Route::get( '/', [MenuItemsController::class, 'index'] )->name( 'api.menu-items.index' );
            Route::post( '/', [MenuItemsController::class, 'store'] )->name( 'api.menu-items.store' );
            Route::get( '{id}', [MenuItemsController::class, 'show'] )->name( 'api.menu-items.show' )->whereNumber( 'id' );
            Route::put( '{id}', [MenuItemsController::class, 'update'] )->name( 'api.menu-items.update' )->whereNumber( 'id' );
            Route::delete( '{id}', [MenuItemsController::class, 'destroy'] )->name( 'api.menu-items.destroy' )->whereNumber( 'id' );
        } );

        Route::prefix( 'menu-locations' )->group( function (): void {
            Route::get( '/', [MenuLocationsController::class, 'index'] )->name( 'api.menu-locations.index' );
            Route::put( '{location}', [MenuLocationsController::class, 'update'] )->name( 'api.menu-locations.update' );
            Route::delete( '{location}', [MenuLocationsController::class, 'destroy'] )->name( 'api.menu-locations.destroy' );
        } );
    } );

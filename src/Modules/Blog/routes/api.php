<?php

declare( strict_types = 1 );

/**
 * Blog Module API Routes
 *
 * @since 1.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers\PostCategoryController;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers\PostController;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers\PostTagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Posts API Routes
|--------------------------------------------------------------------------
*/

Route::prefix( 'posts' )->middleware( 'auth' )->group( function (): void {
    Route::get( '/', [PostController::class, 'index'] );
    Route::post( '/', [PostController::class, 'store'] );
    Route::get( '/{id}', [PostController::class, 'show'] );
    Route::put( '/{id}', [PostController::class, 'update'] );
    Route::delete( '/{id}', [PostController::class, 'destroy'] );
} );

/*
|--------------------------------------------------------------------------
| Post Archive Routes
|--------------------------------------------------------------------------
*/

Route::prefix( 'posts/archives' )->middleware( 'auth' )->group( function (): void {
    Route::get( '/date/{year}/{month?}/{day?}', [PostController::class, 'archiveByDate'] );
    Route::get( '/author/{authorId}', [PostController::class, 'archiveByAuthor'] );
    Route::get( '/category/{slug}', [PostController::class, 'archiveByCategory'] );
    Route::get( '/tag/{slug}', [PostController::class, 'archiveByTag'] );
} );

/*
|--------------------------------------------------------------------------
| Post Categories API Routes
|--------------------------------------------------------------------------
*/

Route::prefix( 'post-categories' )->middleware( 'auth' )->group( function (): void {
    Route::get( '/', [PostCategoryController::class, 'index'] );
    Route::post( '/', [PostCategoryController::class, 'store'] );
    Route::get( '/{id}', [PostCategoryController::class, 'show'] );
    Route::put( '/{id}', [PostCategoryController::class, 'update'] );
    Route::delete( '/{id}', [PostCategoryController::class, 'destroy'] );
} );

/*
|--------------------------------------------------------------------------
| Post Tags API Routes
|--------------------------------------------------------------------------
*/

Route::prefix( 'post-tags' )->middleware( 'auth' )->group( function (): void {
    Route::get( '/', [PostTagController::class, 'index'] );
    Route::post( '/', [PostTagController::class, 'store'] );
    Route::get( '/{id}', [PostTagController::class, 'show'] );
    Route::put( '/{id}', [PostTagController::class, 'update'] );
    Route::delete( '/{id}', [PostTagController::class, 'destroy'] );
} );

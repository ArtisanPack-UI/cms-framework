<?php

declare( strict_types=1 );

/**
 * CMS Framework AI API Routes.
 *
 * Mounted at `/api/v1/cms/ai` by CMSFrameworkServiceProvider so that
 * React and Vue front-ends can trigger the five cms.* AI agents
 * (post title, excerpt, tags, category, slug) via plain HTTP.
 *
 * @since 2.3.0
 */

use ArtisanPackUI\CMSFramework\Http\Controllers\Ai\AiController;
use Illuminate\Support\Facades\Route;

Route::middleware( 'auth' )->group( function (): void {
    Route::get( '/features', [ AiController::class, 'features' ] );
    Route::post( '/post-title', [ AiController::class, 'postTitle' ] );
    Route::post( '/excerpt', [ AiController::class, 'excerpt' ] );
    Route::post( '/suggest-tags', [ AiController::class, 'suggestTags' ] );
    Route::post( '/suggest-category', [ AiController::class, 'suggestCategory' ] );
    Route::post( '/suggest-slug', [ AiController::class, 'suggestSlug' ] );
} );

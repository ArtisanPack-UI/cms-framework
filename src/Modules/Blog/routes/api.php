<?php

declare( strict_types=1 );

/**
 * Blog Module API Routes
 *
 * @since 1.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers\CommentController;
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
    Route::post( '/bulk', [PostController::class, 'bulk'] );
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

/*
|--------------------------------------------------------------------------
| Comments API Routes
|--------------------------------------------------------------------------
|
| Read endpoints (`index`, `show`) are public — `CommentPolicy` filters
| the approved set so guest visitors can fetch comments without an auth
| token. `store` is also publicly reachable (the policy's
| `comments.create.public` filter defaults to allow); guest commenters
| supply `author_name` / `author_email` / `author_url` and the
| controller defaults the resulting comment to `pending` so a
| moderator can approve it. Update / delete are auth-gated.
|
| The public `POST /comments` route is throttled via the
| `throttle:comments` named limiter (defined in
| `BlogServiceProvider::registerCommentsRateLimiter()`) to keep
| unauthenticated callers from bulk-inserting against `post_comments`.
| Default buckets: 10/min for guests (keyed by IP), 60/min for
| authenticated users (keyed by user id) — overridable via the
| `comments.rate-limit.guest` / `comments.rate-limit.authenticated`
| hooks filters.
|
*/

Route::prefix( 'comments' )->group( function (): void {
    Route::get( '/', [CommentController::class, 'index'] );
    Route::get( '/{comment}', [CommentController::class, 'show'] );
    Route::post( '/', [CommentController::class, 'store'] )
        ->middleware( 'throttle:comments' );

    Route::middleware( 'auth' )->group( function (): void {
        Route::put( '/{comment}', [CommentController::class, 'update'] );
        Route::patch( '/{comment}', [CommentController::class, 'update'] );
        Route::delete( '/{comment}', [CommentController::class, 'destroy'] );
    } );
} );

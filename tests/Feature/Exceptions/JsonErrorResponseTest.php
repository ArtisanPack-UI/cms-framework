<?php

declare( strict_types=1 );

/**
 * Tests for standardized JSON error response format.
 *
 * Verifies that all CMS Framework exceptions render as a consistent
 * JSON error format when the request expects JSON.
 *
 * @since 1.1.0
 */

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;
use ArtisanPackUI\CMSFramework\Exceptions\NotFoundException;
use ArtisanPackUI\CMSFramework\Exceptions\UnauthorizedException;
use ArtisanPackUI\CMSFramework\Exceptions\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    // Register test routes that throw each exception type.
    Route::middleware( ['api'] )->group( function (): void {
        Route::get( '/test/server-error', function (): void {
            throw new CMSFrameworkException( 'Something went wrong.' );
        } );

        Route::get( '/test/not-found', function (): void {
            throw NotFoundException::resource( 'Post', 'my-post' );
        } );

        Route::get( '/test/not-found-model', function (): void {
            throw NotFoundException::model( 'App\Models\Post', 42 );
        } );

        Route::get( '/test/unauthorized-action', function (): void {
            throw UnauthorizedException::forAction( 'delete this post' );
        } );

        Route::get( '/test/unauthorized-resource', function (): void {
            throw UnauthorizedException::forResource( 'posts', 'edit' );
        } );

        Route::get( '/test/unauthorized-permission', function (): void {
            throw UnauthorizedException::requiresPermission( 'manage_posts' );
        } );

        Route::get( '/test/validation-error', function (): void {
            throw ValidationException::withErrors(
                'The given data was invalid.',
                [
                    'title' => ['The title field is required.'],
                    'slug'  => ['The slug has already been taken.'],
                ],
            );
        } );

        Route::get( '/test/validation-error-no-fields', function (): void {
            throw new ValidationException( 'Validation failed.' );
        } );
    } );
} );

// --- Base CMSFrameworkException ---

test( 'base exception renders as 500 server error with consistent JSON format', function (): void {
    $response = $this->getJson( '/test/server-error' );

    $response->assertStatus( 500 );
    $response->assertExactJson( [
        'error' => [
            'code'    => 'SERVER_ERROR',
            'message' => 'Something went wrong.',
        ],
    ] );
} );

test( 'base exception has correct error code and status code', function (): void {
    $exception = new CMSFrameworkException( 'Test error.' );

    expect( $exception->getErrorCode() )->toBe( 'SERVER_ERROR' );
    expect( $exception->getStatusCode() )->toBe( 500 );
} );

// --- NotFoundException ---

test( 'not found exception renders as 404 with consistent JSON format', function (): void {
    $response = $this->getJson( '/test/not-found' );

    $response->assertStatus( 404 );
    $response->assertExactJson( [
        'error' => [
            'code'    => 'NOT_FOUND',
            'message' => "Post 'my-post' not found.",
        ],
    ] );
} );

test( 'not found model exception renders as 404 with model details', function (): void {
    $response = $this->getJson( '/test/not-found-model' );

    $response->assertStatus( 404 );
    $response->assertExactJson( [
        'error' => [
            'code'    => 'NOT_FOUND',
            'message' => 'Model App\Models\Post with ID 42 not found.',
        ],
    ] );
} );

test( 'not found exception has correct error code and status code', function (): void {
    $exception = NotFoundException::resource( 'Page', 'about' );

    expect( $exception->getErrorCode() )->toBe( 'NOT_FOUND' );
    expect( $exception->getStatusCode() )->toBe( 404 );
} );

// --- UnauthorizedException ---

test( 'unauthorized action exception renders as 403 with consistent JSON format', function (): void {
    $response = $this->getJson( '/test/unauthorized-action' );

    $response->assertStatus( 403 );
    $response->assertExactJson( [
        'error' => [
            'code'    => 'FORBIDDEN',
            'message' => 'You are not authorized to delete this post.',
        ],
    ] );
} );

test( 'unauthorized resource exception renders as 403', function (): void {
    $response = $this->getJson( '/test/unauthorized-resource' );

    $response->assertStatus( 403 );
    $response->assertExactJson( [
        'error' => [
            'code'    => 'FORBIDDEN',
            'message' => 'You are not authorized to edit posts.',
        ],
    ] );
} );

test( 'unauthorized permission exception renders as 403', function (): void {
    $response = $this->getJson( '/test/unauthorized-permission' );

    $response->assertStatus( 403 );
    $response->assertExactJson( [
        'error' => [
            'code'    => 'FORBIDDEN',
            'message' => "This action requires the 'manage_posts' permission.",
        ],
    ] );
} );

test( 'unauthorized exception has correct error code and status code', function (): void {
    $exception = UnauthorizedException::forAction( 'test' );

    expect( $exception->getErrorCode() )->toBe( 'FORBIDDEN' );
    expect( $exception->getStatusCode() )->toBe( 403 );
} );

// --- ValidationException ---

test( 'validation exception renders as 422 with field-level errors', function (): void {
    $response = $this->getJson( '/test/validation-error' );

    $response->assertStatus( 422 );
    $response->assertExactJson( [
        'error' => [
            'code'    => 'VALIDATION_ERROR',
            'message' => 'The given data was invalid.',
            'errors'  => [
                'title' => ['The title field is required.'],
                'slug'  => ['The slug has already been taken.'],
            ],
        ],
    ] );
} );

test( 'validation exception without field errors omits errors key', function (): void {
    $response = $this->getJson( '/test/validation-error-no-fields' );

    $response->assertStatus( 422 );
    $response->assertExactJson( [
        'error' => [
            'code'    => 'VALIDATION_ERROR',
            'message' => 'Validation failed.',
        ],
    ] );
} );

test( 'validation exception has correct error code and status code', function (): void {
    $exception = ValidationException::withErrors( 'Invalid.', ['name' => ['Required.']] );

    expect( $exception->getErrorCode() )->toBe( 'VALIDATION_ERROR' );
    expect( $exception->getStatusCode() )->toBe( 422 );
    expect( $exception->getErrors() )->toBe( ['name' => ['Required.']] );
    expect( $exception->hasError( 'name' ) )->toBeTrue();
    expect( $exception->hasError( 'email' ) )->toBeFalse();
} );

// --- Render method returns null for non-JSON requests ---

test( 'exceptions return null for non-JSON requests to allow default rendering', function (): void {
    $exception = new CMSFrameworkException( 'Test error.' );
    $request   = Request::create( '/test', 'GET' );

    $result = $exception->render( $request );

    expect( $result )->toBeNull();
} );

test( 'exceptions return JsonResponse for JSON requests', function (): void {
    $exception = new CMSFrameworkException( 'Test error.' );
    $request   = Request::create( '/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json'] );

    $result = $exception->render( $request );

    expect( $result )->toBeInstanceOf( JsonResponse::class );
    expect( $result->getStatusCode() )->toBe( 500 );
} );

// --- Module-specific exceptions inherit base behavior ---

test( 'module exceptions that extend CMSFrameworkException inherit JSON rendering', function (): void {
    // Create a test route for a generic subclass
    Route::middleware( ['api'] )->get( '/test/module-error', function (): void {
        throw new class( 'Module error occurred.') extends CMSFrameworkException {};
    });

    $response = $this->getJson( '/test/module-error');

    $response->assertStatus( 500);
    $response->assertJsonStructure( [
        'error' => [
            'code',
            'message',
        ],
    ]);
    $response->assertJsonPath( 'error.code', 'SERVER_ERROR');
    $response->assertJsonPath( 'error.message', 'Module error occurred.');
});

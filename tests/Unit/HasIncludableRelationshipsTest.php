<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Http\Controllers\Concerns\HasIncludableRelationships;
use Illuminate\Http\Request;

beforeEach( function (): void {
    $this->controller = new class {
        use HasIncludableRelationships;

        protected array $includableRelationships = ['author', 'categories', 'tags'];

        protected array $defaultIncludes = ['author', 'categories'];
    };
} );

test( 'returns default includes when no include parameter is present', function (): void {
    $request = Request::create( '/test', 'GET' );

    $result = invokeMethod( $this->controller, 'getRequestedIncludes', [$request] );

    expect( $result )->toBe( ['author', 'categories'] );
} );

test( 'returns requested valid includes when include parameter is present', function (): void {
    $request = Request::create( '/test', 'GET', ['include' => 'author,tags'] );

    $result = invokeMethod( $this->controller, 'getRequestedIncludes', [$request] );

    expect( $result )->toBe( ['author', 'tags'] );
} );

test( 'filters out invalid includes', function (): void {
    $request = Request::create( '/test', 'GET', ['include' => 'author,nonexistent,tags'] );

    $result = invokeMethod( $this->controller, 'getRequestedIncludes', [$request] );

    expect( $result )->toBe( ['author', 'tags'] );
} );

test( 'returns empty array when include parameter is empty string', function (): void {
    $request = Request::create( '/test', 'GET', ['include' => ''] );

    $result = invokeMethod( $this->controller, 'getRequestedIncludes', [$request] );

    expect( $result )->toBe( [] );
} );

test( 'returns empty array when all includes are invalid', function (): void {
    $request = Request::create( '/test', 'GET', ['include' => 'foo,bar,baz'] );

    $result = invokeMethod( $this->controller, 'getRequestedIncludes', [$request] );

    expect( $result )->toBe( [] );
} );

test( 'trims whitespace from include values', function (): void {
    $request = Request::create( '/test', 'GET', ['include' => ' author , tags '] );

    $result = invokeMethod( $this->controller, 'getRequestedIncludes', [$request] );

    expect( $result )->toBe( ['author', 'tags'] );
} );

test( 'returns single valid include', function (): void {
    $request = Request::create( '/test', 'GET', ['include' => 'categories'] );

    $result = invokeMethod( $this->controller, 'getRequestedIncludes', [$request] );

    expect( $result )->toBe( ['categories'] );
} );

test( 'returns all allowed includes when all requested', function (): void {
    $request = Request::create( '/test', 'GET', ['include' => 'author,categories,tags'] );

    $result = invokeMethod( $this->controller, 'getRequestedIncludes', [$request] );

    expect( $result )->toBe( ['author', 'categories', 'tags'] );
} );

test( 'getIncludableRelationships returns the property value', function (): void {
    $result = invokeMethod( $this->controller, 'getIncludableRelationships', [] );

    expect( $result )->toBe( ['author', 'categories', 'tags'] );
} );

test( 'getDefaultIncludes returns the property value', function (): void {
    $result = invokeMethod( $this->controller, 'getDefaultIncludes', [] );

    expect( $result )->toBe( ['author', 'categories'] );
} );

test( 'returns empty arrays when properties are not defined', function (): void {
    $controller = new class {
        use HasIncludableRelationships;
    };

    $includable = invokeMethod( $controller, 'getIncludableRelationships', [] );
    $defaults   = invokeMethod( $controller, 'getDefaultIncludes', []);

    expect( $includable)->toBe( []);
    expect( $defaults)->toBe( []);
});

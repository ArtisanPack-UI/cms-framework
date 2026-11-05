<?php

declare( strict_types=1 );

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

/*
|--------------------------------------------------------------------------
| Test Case & Traits
|--------------------------------------------------------------------------
|
| This is where you can bind your base TestCase and any traits you want
| to apply to groups of tests.
|
*/

// This tells Pest that all tests in the 'Feature' directory
// should use your custom TestCase as their foundation.
uses( ArtisanPackUI\CMSFramework\Tests\TestCase::class )->in( 'Feature' );

// This tells Pest to apply the RefreshDatabase trait to all
// tests in the 'Feature' directory, resetting the database for each test.
uses( Illuminate\Foundation\Testing\RefreshDatabase::class )->in( 'Feature' );

uses( ArtisanPackUI\CMSFramework\Tests\TestCase::class )->in( 'Unit' );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend( 'toBeOne', function () {
    return $this->toBe( 1 );
} );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// Load test helpers
require_once __DIR__ . '/Helpers/TestHelpers.php';

/*
| Under `pest --parallel`, give each process its own `bootstrap/cache` files.
| Plugin and theme tests write `routes-v7.php` into Testbench's shared
| skeleton, and cache clears remove `services.php`, `packages.php` and the
| compiled views, so another process booting or rendering at that moment
| loads the wrong routes or finds its files gone (#458). Set before any
| application boots; Laravel reads the cache paths through
| `Application::normalizeCachePath()` and the view path through `view.compiled`.
*/
$parallelToken = getenv( 'TEST_TOKEN' );

if ( is_scalar( $parallelToken ) && '' !== (string) $parallelToken ) {
    foreach ( [
        'APP_CONFIG_CACHE'   => 'config',
        'APP_EVENTS_CACHE'   => 'events',
        'APP_PACKAGES_CACHE' => 'packages',
        'APP_ROUTES_CACHE'   => 'routes-v7',
        'APP_SERVICES_CACHE' => 'services',
    ] as $variable => $file ) {
        $_SERVER[ $variable ] = $_ENV[ $variable ] = "bootstrap/cache/{$file}-test-{$parallelToken}.php";
    }

    // Theme activation and updates run a real `view:clear`, which would
    // delete compiled views another process is about to include. The
    // directory gets a random name and is created exclusively, owner-only:
    // tokens repeat across runs, so a predictable path in the shared temp
    // directory could be pre-seeded with a compiled view that Blade would
    // then include. It is removed when the worker exits.
    $compiledViews = sys_get_temp_dir() . '/cms-framework-views-' . bin2hex( random_bytes( 16 ) ) . "-{$parallelToken}";

    if ( ! mkdir( $compiledViews, 0700 ) ) {
        throw new RuntimeException( "Could not create the compiled-view directory {$compiledViews}." );
    }

    register_shutdown_function( static function () use ( $compiledViews ): void {
        Illuminate\Support\Facades\File::deleteDirectory( $compiledViews );
    } );

    $_SERVER['VIEW_COMPILED_PATH'] = $_ENV['VIEW_COMPILED_PATH'] = $compiledViews;
}

unset( $parallelToken, $variable, $file, $compiledViews );

function something(): void
{
    // ..
}

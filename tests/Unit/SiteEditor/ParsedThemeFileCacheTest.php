<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ParsedThemeFileCache;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeFileBlockParser;
use ArtisanPackUI\CMSFramework\Tests\Support\BlockMarkupHydratorStub;
use Illuminate\Support\Facades\Cache;

beforeEach( function (): void {
    $this->markup = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->';
    $this->calls  = 0;
    $this->parser = function (): array {
        $this->calls++;

        return ['parsed' => $this->calls];
    };
} );

/**
 * Drop the scoped cache instance, as the container does between requests.
 */
function startNextRequest(): void
{
    app()->forgetScopedInstances();
}

describe( 'ParsedThemeFileCache::remember()', function (): void {
    it( 'parses each distinct contents once per request', function (): void {
        $cache = app( ParsedThemeFileCache::class );

        $first  = $cache->remember( 'blocks', 'a', '', $this->parser );
        $second = $cache->remember( 'blocks', 'a', '', $this->parser );
        $other  = $cache->remember( 'blocks', 'b', '', $this->parser );

        expect( $first )->toBe( ['parsed' => 1] )
            ->and( $second )->toBe( ['parsed' => 1] )
            ->and( $other )->toBe( ['parsed' => 2] )
            ->and( $this->calls )->toBe( 2 );
    } );

    it( 'serves later requests from the cache store', function (): void {
        app( ParsedThemeFileCache::class )->remember( 'blocks', 'a', '', $this->parser );

        startNextRequest();

        expect( app( ParsedThemeFileCache::class )->remember( 'blocks', 'a', '', $this->parser ) )->toBe( ['parsed' => 1] )
            ->and( $this->calls )->toBe( 1 );
    } );

    it( 'keeps kinds and signatures in separate entries', function (): void {
        $cache = app( ParsedThemeFileCache::class );

        $cache->remember( 'blocks', 'a', '', $this->parser );
        $cache->remember( 'pattern', 'a', '', $this->parser );
        $cache->remember( 'blocks', 'a', 'hydrator', $this->parser );

        expect( $this->calls )->toBe( 3 );
    } );

    it( 'keeps a result the parser flags as unstorable out of the store but memoises it', function (): void {
        $parser = function ( bool &$store ): array {
            $this->calls++;
            $store = false;

            return ['parsed' => $this->calls];
        };

        $cache = app( ParsedThemeFileCache::class );
        $cache->remember( 'blocks', 'a', '', $parser );
        $cache->remember( 'blocks', 'a', '', $parser );

        expect( $this->calls )->toBe( 1 );

        startNextRequest();

        app( ParsedThemeFileCache::class )->remember( 'blocks', 'a', '', $parser );

        expect( $this->calls )->toBe( 2 );
    } );

    it( 'skips the cache store when parsedFileCacheEnabled is off', function (): void {
        config()->set( 'cms.themes.parsedFileCacheEnabled', false );

        Cache::spy();

        app( ParsedThemeFileCache::class )->remember( 'blocks', 'a', '', $this->parser );
        app( ParsedThemeFileCache::class )->remember( 'blocks', 'a', '', $this->parser );

        expect( $this->calls )->toBe( 1 );

        Cache::shouldNotHaveReceived( 'get' );
        Cache::shouldNotHaveReceived( 'put' );
    } );

    it( 'stores entries for the configured TTL, defaulting when it is not positive', function ( mixed $configured, int $expected ): void {
        config()->set( 'cms.themes.parsedFileCacheTtl', $configured );

        Cache::spy();

        app( ParsedThemeFileCache::class )->remember( 'blocks', 'a', '', $this->parser );

        Cache::shouldHaveReceived( 'put' )->once()->withArgs( fn ( string $key, array $value, int $ttl ): bool => $ttl === $expected );
    } )->with( [
        'configured' => [120, 120],
        'string'     => ['600', 600],
        'zero'       => [0, ParsedThemeFileCache::DEFAULT_TTL],
        'negative'   => [-5, ParsedThemeFileCache::DEFAULT_TTL],
    ] );
    it( 'stops calling an unavailable cache store for the rest of the request', function (): void {
        Cache::shouldReceive( 'get' )->once()->andThrow( new RuntimeException( 'Connection refused' ) );
        Cache::shouldReceive( 'put' )->never();

        $cache = app( ParsedThemeFileCache::class );

        foreach ( ['a', 'b', 'c'] as $contents ) {
            $cache->remember( 'blocks', $contents, '', $this->parser );
        }

        expect( $this->calls )->toBe( 3 )
            ->and( $cache->remember( 'blocks', 'a', '', $this->parser ) )->toBe( ['parsed' => 1] );
    } );

    it( 'stops writing to a cache store whose first write fails', function (): void {
        Cache::shouldReceive( 'get' )->once()->andReturnNull();
        Cache::shouldReceive( 'put' )->once()->andThrow( new RuntimeException( 'no such table: cache' ) );

        $cache = app( ParsedThemeFileCache::class );
        $cache->remember( 'blocks', 'a', '', $this->parser );
        $cache->remember( 'blocks', 'b', '', $this->parser );

        expect( $this->calls )->toBe( 2 );
    } );

    it( 'parses without caching when the cache store is unavailable', function (): void {
        Cache::shouldReceive( 'get' )->andThrow( new RuntimeException( 'no such table: cache' ) );
        Cache::shouldReceive( 'put' )->andThrow( new RuntimeException( 'no such table: cache' ) );

        expect( app( ParsedThemeFileCache::class )->remember( 'blocks', 'a', '', $this->parser ) )->toBe( ['parsed' => 1] );

        startNextRequest();

        expect( app( ParsedThemeFileCache::class )->remember( 'blocks', 'a', '', $this->parser ) )->toBe( ['parsed' => 2] );
    } );
} );

describe( 'ThemeFileBlockParser::parseCached()', function (): void {
    it( 'matches parse() and hydrates unchanged markup only once across requests', function (): void {
        $stub = new BlockMarkupHydratorStub();
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, $stub );

        $expected       = ThemeFileBlockParser::parse( $this->markup );
        $stub->received = [];

        expect( ThemeFileBlockParser::parseCached( $this->markup ) )->toBe( $expected )
            ->and( ThemeFileBlockParser::parseCached( $this->markup ) )->toBe( $expected );

        startNextRequest();

        expect( ThemeFileBlockParser::parseCached( $this->markup ) )->toBe( $expected )
            ->and( $stub->received )->toHaveCount( 1 );
    } );

    it( 'returns an empty tree for blank markup without touching the cache', function (): void {
        Cache::spy();

        expect( ThemeFileBlockParser::parseCached( "  \n" ) )->toBe( [] );

        Cache::shouldNotHaveReceived( 'get' );
    } );

    it( 'does not serve a parse_blocks() tree once a hydrator is available', function (): void {
        $fallback = ThemeFileBlockParser::parseCached( $this->markup );

        startNextRequest();
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, new BlockMarkupHydratorStub() );

        $hydrated = ThemeFileBlockParser::parseCached( $this->markup );

        expect( $fallback[0] )->toHaveKey( 'blockName' )
            ->and( $hydrated[0]['name'] )->toBe( 'core/paragraph' )
            ->and( $hydrated[0]['attributes'] )->toBe( ['content' => 'hydrated'] );
    } );

    it( 'retries hydration on the next request after the hydrator throws', function (): void {
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, new BlockMarkupHydratorStub( shouldThrow: true ) );

        expect( ThemeFileBlockParser::parseCached( $this->markup )[0]['blockName'] )->toBe( 'core/paragraph' );

        startNextRequest();
        $stub = new BlockMarkupHydratorStub();
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, $stub );

        expect( ThemeFileBlockParser::parseCached( $this->markup )[0]['attributes'] )->toBe( ['content' => 'hydrated'] )
            ->and( $stub->received )->toHaveCount( 1 );
    } );
} );

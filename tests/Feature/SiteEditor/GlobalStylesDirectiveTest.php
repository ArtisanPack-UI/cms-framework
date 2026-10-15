<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission\GlobalStylesEmitter;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    $this->themesPath = base_path( 'themes' );
    $this->themeSlug  = 'test-theme';
    $this->themeRoot  = $this->themesPath . '/' . $this->themeSlug;

    File::ensureDirectoryExists( $this->themeRoot );
    File::put( $this->themeRoot . '/theme.json', json_encode( [
        'name'    => 'Test',
        'slug'    => $this->themeSlug,
        'version' => '1.0.0',
    ] ) );

    config()->set( 'cms.themes.cacheEnabled', false );
} );

afterEach( function (): void {
    File::deleteDirectory( $this->themeRoot );
} );

it( 'drops `</style>` payloads so user input cannot break out of the style block', function (): void {
    $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'name'   => 'Test',
            'slug'   => $this->themeSlug,
            'styles' => [
                'color' => [
                    // Stored payload that, naively concatenated into a `<style>`
                    // block, would close the tag and execute arbitrary markup.
                    'background' => 'red;}</style><script>alert(1)</script>',
                ],
            ],
        ] );
    } );

    GlobalStyles::create( ['theme' => $this->themeSlug] );

    $rendered = Blade::render( '@cmsGlobalStyles' );

    // The `</style` literal must not appear inside the emitted block (case
    // insensitive — HTML5 closes raw-text tags case-insensitively). The
    // emitter drops the unsafe value outright (#385).
    $stripped = substr( $rendered, 0, strrpos( $rendered, '</style>' ) );

    expect( stripos( $stripped, '</style' ) )->toBe( false )
        ->and( $stripped )->not->toContain( '<script>' )
        ->and( $stripped )->not->toContain( 'alert(1)' );
} );

it( 'escapes `</` sequences in emitter output as a second line of defense', function (): void {
    $this->mock( GlobalStylesEmitter::class, function ( $mock ): void {
        $mock->shouldReceive( 'emit' )->andReturn( ':root { --x: red; }</style><script>alert(1)</script>' );
    } );

    $rendered = Blade::render( '@cmsGlobalStyles' );

    $stripped = substr( $rendered, 0, strrpos( $rendered, '</style>' ) );

    expect( stripos( $stripped, '</style' ) )->toBe( false )
        ->and( $stripped )->toContain( '<\\/style>' )
        ->and( $stripped )->toContain( '<\\/script>' );
} );

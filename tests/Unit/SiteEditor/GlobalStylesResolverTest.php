<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedGlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    $this->themesPath = base_path( 'themes' );
    $this->themeSlug  = 'test-theme';
    $this->themeRoot  = $this->themesPath . '/' . $this->themeSlug;
    $this->stylesDir  = $this->themeRoot . '/styles';

    File::ensureDirectoryExists( $this->themeRoot );

    File::put( $this->themeRoot . '/theme.json', json_encode( [
        'name'     => 'Test',
        'slug'     => $this->themeSlug,
        'version'  => '1.0.0',
        'settings' => [
            'color' => [
                'palette' => [
                    ['slug' => 'primary', 'color' => '#000000'],
                    ['slug' => 'accent',  'color' => '#ff0000'],
                ],
            ],
        ],
        'styles' => [
            'color'      => ['background' => '#ffffff', 'text' => '#222222'],
            'typography' => ['fontSize' => '16px'],
        ],
    ] ) );

    config()->set( 'cms.themes.cacheEnabled', false );

    $themeManager = $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'name'     => 'Test',
            'slug'     => $this->themeSlug,
            'settings' => [
                'color' => [
                    'palette' => [
                        ['slug' => 'primary', 'color' => '#000000'],
                        ['slug' => 'accent',  'color' => '#ff0000'],
                    ],
                ],
            ],
            'styles' => [
                'color'      => ['background' => '#ffffff', 'text' => '#222222'],
                'typography' => ['fontSize' => '16px'],
            ],
        ] );
    } );

    $this->resolver = new GlobalStylesResolver( $themeManager );
} );

afterEach( function (): void {
    File::deleteDirectory( $this->themeRoot );
} );

describe( 'GlobalStylesResolver::resolve()', function (): void {
    it( 'returns theme defaults when no DB row exists', function (): void {
        $resolved = $this->resolver->resolve();

        expect( $resolved )->toBeInstanceOf( ResolvedGlobalStyles::class )
            ->and( $resolved->theme )->toBe( $this->themeSlug )
            ->and( $resolved->hasUserCustomization )->toBeFalse()
            ->and( $resolved->variation )->toBeNull()
            ->and( $resolved->styles['color']['background'] )->toBe( '#ffffff' )
            ->and( $resolved->styles['typography']['fontSize'] )->toBe( '16px' )
            ->and( $resolved->settings['color']['palette'] )->toHaveCount( 2 );
    } );

    it( 'merges DB row over theme defaults (user wins)', function (): void {
        GlobalStyles::create( [
            'theme'    => $this->themeSlug,
            'styles'   => ['color' => ['background' => '#000000']],
            'settings' => ['color' => ['palette' => [['slug' => 'primary', 'color' => '#abcdef']]]],
        ] );

        $resolved = $this->resolver->resolve();

        expect( $resolved->hasUserCustomization )->toBeTrue()
            ->and( $resolved->styles['color']['background'] )->toBe( '#000000' )
            ->and( $resolved->styles['color']['text'] )->toBe( '#222222' )
            ->and( $resolved->styles['typography']['fontSize'] )->toBe( '16px' )
            // Numeric arrays (palette list) replace wholesale, matching WP semantics.
            ->and( $resolved->settings['color']['palette'] )->toHaveCount( 1 )
            ->and( $resolved->settings['color']['palette'][0]['color'] )->toBe( '#abcdef' );
    } );

    it( 'applies a variation between defaults and user customization', function (): void {
        File::ensureDirectoryExists( $this->stylesDir );
        File::put( $this->stylesDir . '/dark.json', json_encode( [
            'slug'   => 'dark',
            'title'  => 'Dark',
            'styles' => [
                'color' => ['background' => '#111111', 'text' => '#eeeeee'],
            ],
        ] ) );

        GlobalStyles::create( [
            'theme'     => $this->themeSlug,
            'variation' => 'dark',
            // No user style overrides; variation should win over defaults.
        ] );

        $resolved = $this->resolver->resolve();

        expect( $resolved->variation )->toBe( 'dark' )
            ->and( $resolved->styles['color']['background'] )->toBe( '#111111' )
            ->and( $resolved->styles['color']['text'] )->toBe( '#eeeeee' );
    } );

    it( 'lets user customization override variation values', function (): void {
        File::ensureDirectoryExists( $this->stylesDir );
        File::put( $this->stylesDir . '/dark.json', json_encode( [
            'slug'   => 'dark',
            'styles' => ['color' => ['background' => '#111111']],
        ] ) );

        GlobalStyles::create( [
            'theme'     => $this->themeSlug,
            'variation' => 'dark',
            'styles'    => ['color' => ['background' => '#222222']],
        ] );

        $resolved = $this->resolver->resolve();

        expect( $resolved->styles['color']['background'] )->toBe( '#222222' );
    } );

    it( 'returns null when no theme is active', function (): void {
        $themeManager = $this->mock( ThemeManager::class, function ( $mock ): void {
            $mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
        } );

        $resolver = new GlobalStylesResolver( $themeManager );

        expect( $resolver->resolve() )->toBeNull();
    } );
} );

describe( 'GlobalStylesResolver::variations()', function (): void {
    it( 'reads variation files from the theme styles directory', function (): void {
        File::ensureDirectoryExists( $this->stylesDir );
        File::put( $this->stylesDir . '/dark.json', json_encode( [
            'slug'   => 'dark',
            'title'  => 'Dark',
            'styles' => ['color' => ['background' => '#111111']],
        ] ) );
        File::put( $this->stylesDir . '/sepia.json', json_encode( [
            'title'  => 'Sepia',
            'styles' => ['color' => ['background' => '#f4ecd8']],
        ] ) );

        $variations = $this->resolver->variations();

        expect( $variations )->toHaveCount( 2 );

        $slugs = array_column( $variations, 'slug' );
        expect( $slugs )->toBe( ['dark', 'sepia'] );

        $sepia = array_values( array_filter( $variations, fn ( $v ) => 'sepia' === $v['slug'] ) )[0];
        expect( $sepia['title'] )->toBe( 'Sepia' );
    } );

    it( 'returns an empty array when the styles directory is missing', function (): void {
        expect( $this->resolver->variations() )->toBe( [] );
    } );
} );

describe( 'GlobalStylesResolver::update() / revert()', function (): void {
    it( 'creates a row on first update and updates it on subsequent calls', function (): void {
        $first = $this->resolver->update( ['styles' => ['color' => ['background' => '#aaaaaa']]] );

        expect( $first )->toBeInstanceOf( GlobalStyles::class )
            ->and( GlobalStyles::query()->count() )->toBe( 1 );

        $second = $this->resolver->update( ['styles' => ['color' => ['background' => '#bbbbbb']]] );

        expect( $second->id )->toBe( $first->id )
            ->and( GlobalStyles::query()->count() )->toBe( 1 )
            ->and( $second->styles['color']['background'] )->toBe( '#bbbbbb' );
    } );

    it( 'reverts by deleting the DB row and restoring file-only authority', function (): void {
        GlobalStyles::create( [
            'theme'  => $this->themeSlug,
            'styles' => ['color' => ['background' => '#000000']],
        ] );

        $reverted = $this->resolver->revert();

        expect( $reverted )->toBeTrue()
            ->and( GlobalStyles::query()->count() )->toBe( 0 )
            ->and( $this->resolver->resolve()->hasUserCustomization )->toBeFalse()
            ->and( $this->resolver->resolve()->styles['color']['background'] )->toBe( '#ffffff' );
    } );

    it( 'returns false from revert when there is no row', function (): void {
        expect( $this->resolver->revert() )->toBeFalse();
    } );

    it( 'preserves explicit null updates so PUT can clear stored fields', function (): void {
        $row = GlobalStyles::create( [
            'theme'     => $this->themeSlug,
            'variation' => 'dark',
            'styles'    => ['color' => ['background' => '#000000']],
        ] );

        // Simulate the controller's PUT { variation: null } — `validated()` returns
        // an array with the key present and the value null. The resolver must
        // forward that null to clear the column, not strip it as "absent."
        $this->resolver->update( ['variation' => null] );

        $row->refresh();

        expect( $row->variation )->toBeNull()
            // Keys not present in the payload must keep their existing values.
            ->and( $row->styles['color']['background'] )->toBe( '#000000' );
    } );

    it( 'leaves keys absent from the payload untouched on update', function (): void {
        $row = GlobalStyles::create( [
            'theme'  => $this->themeSlug,
            'styles' => ['color' => ['background' => '#000000']],
            'title'  => 'Original',
        ] );

        $this->resolver->update( ['styles' => ['color' => ['background' => '#abcdef']]] );

        $row->refresh();

        expect( $row->styles['color']['background'] )->toBe( '#abcdef' )
            ->and( $row->title )->toBe( 'Original' );
    } );
} );

describe( 'ResolvedGlobalStyles::contentHash()', function (): void {
    it( 'changes when settings change', function (): void {
        $first = $this->resolver->resolve();

        $this->resolver->update( ['styles' => ['color' => ['background' => '#deadbe']]]);

        $second = $this->resolver->resolve();

        expect( $first->contentHash())->not->toBe( $second->contentHash());
    });

    it( 'is stable across calls when input is unchanged', function (): void {
        $a = $this->resolver->resolve()->contentHash();
        $b = $this->resolver->resolve()->contentHash();

        expect( $a)->toBe( $b);
    });
});

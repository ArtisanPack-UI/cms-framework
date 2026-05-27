<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Validation\WpThemeJsonValidationResult;
use ArtisanPackUI\CMSFramework\Modules\Themes\Validation\WpThemeJsonValidator;

beforeEach( function (): void {
    $this->validator = new WpThemeJsonValidator;
} );

describe( 'pre-Phase-H themes (cms-framework manifest fields only)', function (): void {
    it( 'passes a manifest carrying only legacy fields', function (): void {
        $manifest = [
            'name'        => 'Digital Shopfront',
            'slug'        => 'digital-shopfront',
            'version'     => '1.0.0',
            'description' => 'A reference theme.',
            'author'      => 'Jacob Martella',
            'screenshot'  => 'screenshot.png',
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result )
            ->toBeInstanceOf( WpThemeJsonValidationResult::class )
            ->and( $result->valid )->toBeTrue()
            ->and( $result->offendingKey )->toBeNull();
    } );

    it( 'passes an empty manifest', function (): void {
        $result = $this->validator->validate( [] );

        expect( $result->valid )->toBeTrue();
    } );
} );

describe( 'WP-shape keys', function (): void {
    it( 'accepts a manifest with a full WP-shape settings + styles block', function (): void {
        $manifest = [
            'name'     => 'Phase H Theme',
            'slug'     => 'phase-h',
            'version'  => '1.0.0',
            'settings' => [
                'color' => [
                    'palette' => [
                        [
                            'slug'  => 'primary',
                            'name'  => 'Primary',
                            'color' => '#3b82f6',
                        ],
                    ],
                ],
                'typography' => [
                    'fontSizes' => [
                        [
                            'slug' => 'small',
                            'name' => 'Small',
                            'size' => '0.875rem',
                        ],
                    ],
                ],
            ],
            'styles' => [
                'color' => [
                    'background' => '#ffffff',
                    'text'       => '#111827',
                ],
            ],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeTrue()
            ->and( $result->offendingKey )->toBeNull();
    } );

    it( 'accepts customTemplates, templateParts, and patterns arrays', function (): void {
        $manifest = [
            'slug'            => 'phase-h',
            'customTemplates' => [
                [
                    'name'  => 'page-with-sidebar',
                    'title' => 'Page with sidebar',
                ],
            ],
            'templateParts' => [
                [
                    'name'  => 'header',
                    'title' => 'Header',
                    'area'  => 'header',
                ],
            ],
            'patterns' => ['my-namespace/cta'],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeTrue();
    } );

    it( 'rejects an invalid settings.color.palette entry and names the offending key', function (): void {
        $manifest = [
            'slug'     => 'broken',
            'settings' => [
                'color' => [
                    // palette must be an array of objects; here we pass a string.
                    'palette' => 'not-an-array',
                ],
            ],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeFalse()
            ->and( $result->offendingKey )->toContain( 'settings' )
            ->and( $result->message )->not->toBeEmpty();
    } );

    it( 'rejects when styles is the wrong type', function (): void {
        $manifest = [
            'slug'   => 'broken-styles',
            'styles' => 'not-an-object',
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeFalse()
            ->and( $result->offendingKey )->not->toBeNull();
    } );
} );

describe( 'menus.locations cms-framework extension', function (): void {
    it( 'accepts a flat object of string keys and string labels', function (): void {
        $manifest = [
            'slug'  => 'with-menus',
            'menus' => [
                'locations' => [
                    'primary' => 'Primary Menu',
                    'footer'  => 'Footer Menu',
                ],
            ],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeTrue();
    } );

    it( 'accepts menus without locations (empty extension)', function (): void {
        $manifest = [
            'slug'  => 'with-empty-menus',
            'menus' => [],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeTrue();
    } );

    it( 'rejects menus when not an object', function (): void {
        $manifest = [
            'slug'  => 'broken-menus',
            'menus' => 'not-an-object',
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeFalse()
            ->and( $result->offendingKey )->toBe( 'menus' );
    } );

    it( 'rejects menus when it is a list (numeric keys)', function (): void {
        $manifest = [
            'slug'  => 'broken-menus',
            'menus' => ['primary', 'footer'],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeFalse()
            ->and( $result->offendingKey )->toBe( 'menus' );
    } );

    it( 'rejects unknown sibling keys under menus', function (): void {
        $manifest = [
            'slug'  => 'broken-menus',
            'menus' => [
                'locations' => ['primary' => 'Primary Menu'],
                'colors'    => ['red', 'blue'],
            ],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeFalse()
            ->and( $result->offendingKey )->toBe( 'menus' )
            ->and( $result->message )->toContain( 'colors' );
    } );

    it( 'rejects menus with only unknown keys (no locations)', function (): void {
        $manifest = [
            'slug'  => 'broken-menus',
            'menus' => [
                'fonts' => ['Inter'],
            ],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeFalse()
            ->and( $result->offendingKey )->toBe( 'menus' )
            ->and( $result->message )->toContain( 'fonts' );
    } );

    it( 'rejects menus.locations when it is a list (numeric keys)', function (): void {
        $manifest = [
            'slug'  => 'broken-menus',
            'menus' => [
                'locations' => ['primary', 'footer'],
            ],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeFalse()
            ->and( $result->offendingKey )->toBe( 'menus.locations' );
    } );

    it( 'rejects a menus.locations entry whose label is not a string', function (): void {
        $manifest = [
            'slug'  => 'broken-menus',
            'menus' => [
                'locations' => [
                    'primary' => ['not', 'a', 'string'],
                ],
            ],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeFalse()
            ->and( $result->offendingKey )->toBe( 'menus.locations.primary' );
    } );
} );

describe( 'configurable schema version', function (): void {
    it( 'throws when the configured schema version has no bundled file', function (): void {
        config()->set( 'cms.themes.wpThemeJsonSchemaVersion', '99' );

        $manifest = [
            'slug'     => 'any',
            'settings' => ['color' => ['background' => true]],
        ];

        expect( fn () => $this->validator->validate( $manifest ) )
            ->toThrow( RuntimeException::class, 'wp-theme-json-v99' );
    } );

    it( 'reads the configured version when validating WP-shape keys', function (): void {
        // Default is '3'. Re-asserting it is loadable is sufficient.
        config()->set( 'cms.themes.wpThemeJsonSchemaVersion', '3' );

        $manifest = [
            'slug'     => 'configurable',
            'settings' => ['appearanceTools' => true],
        ];

        $result = $this->validator->validate( $manifest );

        expect( $result->valid )->toBeTrue();
    } );
});

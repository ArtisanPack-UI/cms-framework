<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\FieldType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Registries\CustomFieldTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Support\FieldTypeDefinition;

test( 'registry pre-registers every built-in FieldType enum case', function (): void {
    $registry = new CustomFieldTypeRegistry;

    $slugs = $registry->slugs();

    foreach ( FieldType::cases() as $case ) {
        expect( $slugs )->toContain( $case->value );
        expect( $registry->get( $case->value ) )->toBeInstanceOf( FieldTypeDefinition::class );
    }
} );

test( 'registry allows registering new plugin field types via register()', function (): void {
    $registry = new CustomFieldTypeRegistry;

    $registry->register( new FieldTypeDefinition(
        slug       : 'map_picker',
        label      : 'Map Picker',
        columnType : 'string',
    ) );

    expect( $registry->has( 'map_picker' ) )->toBeTrue();
    expect( $registry->get( 'map_picker' )->label )->toBe( 'Map Picker' );
} );

test( 'registry surfaces filter-registered field types via all()', function (): void {
    $registry = new CustomFieldTypeRegistry;

    addFilter( 'ap.contentTypes.registeredFieldTypes', function ( array $types ): array {
        $types['image_gallery'] = [
            'slug'        => 'image_gallery',
            'label'       => 'Image Gallery',
            'column_type' => 'text',
        ];

        return $types;
    } );

    expect( $registry->has( 'image_gallery' ) )->toBeTrue();
    expect( $registry->get( 'image_gallery' )->columnType )->toBe( 'text' );
} );

test( 'registry accepts FieldTypeDefinition instances from filter', function (): void {
    $registry = new CustomFieldTypeRegistry;

    addFilter( 'ap.contentTypes.registeredFieldTypes', function ( array $types ): array {
        $types['color_picker'] = new FieldTypeDefinition(
            slug       : 'color_picker',
            label      : 'Color Picker',
            columnType : 'string',
        );

        return $types;
    } );

    expect( $registry->get( 'color_picker' ) )->not->toBeNull();
    expect( $registry->get( 'color_picker' )->label )->toBe( 'Color Picker' );
} );

test( 'apRegisterFieldType helper registers on the shared singleton', function (): void {
    $definition = apRegisterFieldType( 'phone_verified', [
        'label'       => 'Verified Phone',
        'column_type' => 'string',
    ] );

    expect( $definition )->toBeInstanceOf( FieldTypeDefinition::class );
    expect( $definition->slug )->toBe( 'phone_verified' );
    expect( apGetFieldType( 'phone_verified' ) )->not->toBeNull();
    expect( apRegisteredFieldTypes() )->toHaveKey( 'phone_verified' );
} );

test( 'registry silently drops filter entries with non-scalar slug or label', function (): void {
    $registry = new CustomFieldTypeRegistry;

    addFilter( 'ap.contentTypes.registeredFieldTypes', function ( array $types ): array {
        $types['bad_slug']   = ['slug' => ['nested', 'array'], 'label' => 'Bad'];
        $types['bad_label']  = ['slug' => 'bad_label', 'label' => (object) ['ha' => 'ha']];
        $types['empty_slug'] = ['slug' => '   ', 'label' => 'Blank'];
        $types['good_one']   = ['slug' => 'good_one', 'label' => 'Good'];

        return $types;
    } );

    $slugs = $registry->slugs();

    expect( $slugs )->toContain( 'good_one' );
    expect( $slugs )->not->toContain( 'bad_slug' );
    expect( $slugs )->not->toContain( 'bad_label' );
    expect( $slugs )->not->toContain( '' );
    expect( $slugs )->not->toContain( '   ' );
} );

test( 'FieldTypeDefinition fromArray maps camelCase and snake_case keys', function (): void {
    $definition = FieldTypeDefinition::fromArray( [
        'slug'              => 'rating_stars',
        'label'             => 'Rating',
        'columnType'        => 'integer',
        'validationRules'   => ['integer', 'between:1,5'],
        'editorComponent'   => 'RatingEditor',
        'rendererComponent' => 'RatingRenderer',
        'meta'              => ['icon' => 'star'],
    ] );

    expect( $definition->slug )->toBe( 'rating_stars' );
    expect( $definition->columnType )->toBe( 'integer' );
    expect( $definition->validationRules )->toBe( ['integer', 'between:1,5'] );
    expect( $definition->editorComponent )->toBe( 'RatingEditor' );
    expect( $definition->rendererComponent )->toBe( 'RatingRenderer' );
    expect( $definition->meta )->toBe( ['icon' => 'star'] );
} );

<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Requests\ContentTypeRequest;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Resources\ContentTypeResource;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

/**
 * @param  array<string, mixed>  $overrides
 *
 * @return array<string, mixed>
 */
function packageContentTypeData( array $overrides = [] ): array
{
    return array_merge( [
        'name'        => 'Package',
        'slug'        => 'package',
        'table_name'  => 'packages',
        'model_class' => 'App\\Models\\Package',
    ], $overrides );
}

test( 'a content type can be created with singular, plural and label overrides', function (): void {
    $contentType = ( new ContentTypeManager )->createContentType( packageContentTypeData( [
        'singular_name' => 'Package',
        'plural_name'   => 'Packages',
        'labels'        => [ 'add_new_item' => 'Publish a Package' ],
    ] ) );

    $fresh = ContentType::find( $contentType->id );

    expect( $fresh->singular_name )->toBe( 'Package' )
        ->and( $fresh->plural_name )->toBe( 'Packages' )
        ->and( $fresh->labels )->toBe( [ 'add_new_item' => 'Publish a Package' ] );
} );

test( 'a content type can be updated with singular, plural and label overrides', function (): void {
    $manager = new ContentTypeManager;
    $manager->createContentType( packageContentTypeData() );

    $updated = $manager->updateContentType( 'package', [
        'singular_name' => 'Bundle',
        'plural_name'   => 'Bundles',
        'labels'        => [ 'all_items' => 'Every Bundle' ],
    ] );

    expect( $updated->getSingularLabel() )->toBe( 'Bundle' )
        ->and( $updated->getPluralLabel() )->toBe( 'Bundles' )
        ->and( $updated->getLabel( 'all_items' ) )->toBe( 'Every Bundle' )
        ->and( $updated->name )->toBe( 'Package' );
} );

test( 'creating with only a name keeps working and derives labels from it', function (): void {
    $contentType = ( new ContentTypeManager )->createContentType( packageContentTypeData() );

    expect( $contentType->singular_name )->toBeNull()
        ->and( $contentType->getSingularLabel() )->toBe( 'Package' )
        ->and( $contentType->getPluralLabel() )->toBe( 'Packages' );
} );

test( 'the plural label falls back to the pluralized singular label', function (): void {
    $contentType = new ContentType( [ 'name' => 'Inventory', 'singular_name' => 'Case Study' ] );

    expect( $contentType->getPluralLabel() )->toBe( 'Case Studies' );
} );

test( 'blank singular and plural values fall back like null', function (): void {
    $contentType = new ContentType( [ 'name' => 'Package', 'singular_name' => '  ', 'plural_name' => '' ] );

    expect( $contentType->getSingularLabel() )->toBe( 'Package' )
        ->and( $contentType->getPluralLabel() )->toBe( 'Packages' );
} );

test( 'default labels are derived from the singular and plural labels', function (): void {
    $contentType = new ContentType( [ 'name' => 'Package' ] );

    expect( $contentType->getLabels() )->toMatchArray( [
        'singular_name'      => 'Package',
        'plural_name'        => 'Packages',
        'menu_name'          => 'Packages',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Package',
        'new_item'           => 'New Package',
        'edit_item'          => 'Edit Package',
        'view_item'          => 'View Package',
        'view_items'         => 'View Packages',
        'all_items'          => 'All Packages',
        'search_items'       => 'Search Packages',
        'not_found'          => 'No Packages found.',
        'not_found_in_trash' => 'No Packages found in Trash.',
        'parent_item_colon'  => 'Parent Package:',
        'archives'           => 'Package Archives',
    ] );
} );

test( 'label overrides replace defaults and extra keys pass through', function (): void {
    $contentType = new ContentType( [
        'name'   => 'Package',
        'labels' => [ 'edit_item' => 'Revise Package', 'featured_image' => 'Package Logo' ],
    ] );

    expect( $contentType->getLabel( 'edit_item' ) )->toBe( 'Revise Package' )
        ->and( $contentType->getLabel( 'featured_image' ) )->toBe( 'Package Logo' )
        ->and( $contentType->getLabel( 'add_new_item' ) )->toBe( 'Add New Package' );
} );

test( 'blank, non-string and numerically keyed overrides are ignored', function (): void {
    $contentType = new ContentType( [
        'name'   => 'Package',
        'labels' => [ 'edit_item' => '', 'all_items' => null, 'view_item' => [ 'nested' ], 0 => 'Orphan' ],
    ] );

    expect( $contentType->getLabel( 'edit_item' ) )->toBe( 'Edit Package' )
        ->and( $contentType->getLabel( 'all_items' ) )->toBe( 'All Packages' )
        ->and( $contentType->getLabel( 'view_item' ) )->toBe( 'View Package' )
        ->and( $contentType->getLabels() )->not->toHaveKey( 0 );
} );

test( 'an unknown label key returns null', function (): void {
    expect( ( new ContentType( [ 'name' => 'Package' ] ) )->getLabel( 'nope' ) )->toBeNull();
} );

test( 'filter-registered content types resolve their labels', function (): void {
    $manager = new ContentTypeManager;
    $manager->register( packageContentTypeData( [ 'slug' => 'filtered', 'plural_name' => 'Packagez' ] ) );

    $contentType = $manager->getContentType( 'filtered' );

    expect( $contentType->getSingularLabel() )->toBe( 'Package' )
        ->and( $contentType->getPluralLabel() )->toBe( 'Packagez' );
} );

test( 'the API resource exposes stored and resolved labels', function (): void {
    $contentType = ContentType::create( packageContentTypeData( [
        'plural_name' => 'Packages',
        'labels'      => [ 'all_items' => 'Every Package' ],
    ] ) );
    $contentType->setAttribute( 'custom_fields_count', 0 );

    $payload = ( new ContentTypeResource( $contentType ) )->toArray( new Request );

    expect( $payload['singular_name'] )->toBeNull()
        ->and( $payload['plural_name'] )->toBe( 'Packages' )
        ->and( $payload['labels'] )->toBe( [ 'all_items' => 'Every Package' ] )
        ->and( $payload['resolved_labels']['singular_name'] )->toBe( 'Package' )
        ->and( $payload['resolved_labels']['all_items'] )->toBe( 'Every Package' )
        ->and( $payload['resolved_labels']['edit_item'] )->toBe( 'Edit Package' );
} );

test( 'the request accepts valid label fields', function (): void {
    $validator = Validator::make( packageContentTypeData( [
        'singular_name' => 'Package',
        'plural_name'   => 'Packages',
        'labels'        => [ 'add_new_item' => 'Add New Package', 'not_found' => null ],
    ] ), ( new ContentTypeRequest )->rules() );

    expect( $validator->passes() )->toBeTrue();
} );

test( 'the request rejects invalid label fields', function ( array $overrides, string $errorKey ): void {
    $validator = Validator::make(
        packageContentTypeData( $overrides ),
        ( new ContentTypeRequest )->rules(),
    );

    expect( $validator->errors()->has( $errorKey ) )->toBeTrue();
} )->with( [
    'singular too long'   => [ [ 'singular_name' => str_repeat( 'a', 256 ) ], 'singular_name' ],
    'plural not a string' => [ [ 'plural_name' => [ 'Packages' ] ], 'plural_name' ],
    'labels not an array' => [ [ 'labels' => 'Packages' ], 'labels' ],
    'label not a string'  => [ [ 'labels' => [ 'edit_item' => [ 'x' ] ] ], 'labels.edit_item' ],
    'label list array'    => [ [ 'labels' => [ 'Edit Package' ] ], 'labels' ],
    'label key not snake' => [ [ 'labels' => [ 'Edit Item' => 'Revise' ] ], 'labels' ],
    'label key too long'  => [ [ 'labels' => [ str_repeat( 'a', 65 ) => 'Revise' ] ], 'labels' ],
    'too many labels'     => [ [ 'labels' => array_fill_keys( array_map( fn ( int $i ): string => 'key_' . $i, range( 1, 51 ) ), 'x' ) ], 'labels' ],
] );

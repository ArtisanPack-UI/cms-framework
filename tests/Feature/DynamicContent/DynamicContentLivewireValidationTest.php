<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire\CollectionEditor;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire\FieldBuilder;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentRecordManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;

beforeEach( function (): void {
    app()->register( LivewireServiceProvider::class );

    $this->user = TestUser::create( [
        'name'     => 'Admin',
        'email'    => 'a@example.com',
        'password' => bcrypt( 'x' ),
    ] );

    Gate::define( 'manage_dynamic_content', fn () => true );

    $this->actingAs( $this->user );
} );

test( 'field builder rejects an unregistered field type', function (): void {
    Livewire::test( FieldBuilder::class )
        ->set( 'slug', 'brand' )
        ->set( 'name', 'Brand' )
        ->set( 'cardinality', 'singleton' )
        ->set( 'fields', [
            [ 'slug' => 'name', 'label' => 'Name', 'type' => 'not_a_real_type', 'required' => false, 'default' => null, 'options' => [] ],
        ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'fields.0.type' ] );

    expect( DynamicContentType::where( 'slug', 'brand' )->exists() )->toBeFalse();
} );

test( 'field builder rejects a field missing its label', function (): void {
    Livewire::test( FieldBuilder::class )
        ->set( 'slug', 'brand' )
        ->set( 'name', 'Brand' )
        ->set( 'cardinality', 'singleton' )
        ->set( 'fields', [
            [ 'slug' => 'name', 'label' => '', 'type' => 'text', 'required' => false, 'default' => null, 'options' => [] ],
        ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'fields.0.label' ] );

    expect( DynamicContentType::where( 'slug', 'brand' )->exists() )->toBeFalse();
} );

test( 'field builder persists a valid type and its fields', function (): void {
    Livewire::test( FieldBuilder::class )
        ->set( 'slug', 'brand' )
        ->set( 'name', 'Brand' )
        ->set( 'cardinality', 'singleton' )
        ->set( 'fields', [
            [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => false, 'default' => null, 'options' => [] ],
        ] )
        ->call( 'save' )
        ->assertHasNoErrors();

    $type = DynamicContentType::with( 'fields' )->where( 'slug', 'brand' )->first();

    expect( $type )->not->toBeNull()
        ->and( $type->fields )->toHaveCount( 1 )
        ->and( $type->fields->first()->slug )->toBe( 'name' );
} );

test( 'collection editor rejects an over-long record label', function (): void {
    $type = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'people',
        'name'        => 'People',
        'cardinality' => 'collection',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    Livewire::test( CollectionEditor::class, [ 'typeId' => $type->id ] )
        ->call( 'create' )
        ->set( 'editingLabel', str_repeat( 'a', 256 ) )
        ->set( 'editingValues', [ 'name' => 'Ada' ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'editingLabel' ] );

    expect( $type->records()->count() )->toBe( 0 );
} );

test( 'collection editor persists a valid record', function (): void {
    $type = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'people',
        'name'        => 'People',
        'cardinality' => 'collection',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    Livewire::test( CollectionEditor::class, [ 'typeId' => $type->id ] )
        ->call( 'create' )
        ->set( 'editingLabel', 'Ada Lovelace' )
        ->set( 'editingValues', [ 'name' => 'Ada' ] )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $type->records()->count() )->toBe( 1 );
} );

// --- Authorization / tamper-resistance (items 1.3 + 1.4) ---

test( 'collection editor forbids rewriting the locked typeId', function (): void {
    $collection = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'people',
        'name'        => 'People',
        'cardinality' => 'collection',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );
    $singleton = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'brand',
        'name'        => 'Brand',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    expect( fn () => Livewire::test( CollectionEditor::class, [ 'typeId' => $collection->id ] )
        ->set( 'typeId', $singleton->id ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} );

test( 'collection editor denies a user without the update capability', function (): void {
    $collection = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'people',
        'name'        => 'People',
        'cardinality' => 'collection',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    Gate::define( 'manage_dynamic_content', fn () => false );

    Livewire::test( CollectionEditor::class, [ 'typeId' => $collection->id ] )
        ->assertForbidden();
} );

test( 'collection editor refuses to save a record belonging to another type', function (): void {
    $typeA = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'people',
        'name'        => 'People',
        'cardinality' => 'collection',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );
    $typeB = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'places',
        'name'        => 'Places',
        'cardinality' => 'collection',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    $recordB = app( DynamicContentRecordManager::class )->create( $typeB, [
        'label'  => 'Paris',
        'values' => [ 'name' => 'Paris' ],
    ] );

    Livewire::test( CollectionEditor::class, [ 'typeId' => $typeA->id ] )
        ->set( 'editingRecordId', $recordB->id )
        ->set( 'editingLabel', 'Tampered' )
        ->set( 'editingValues', [ 'name' => 'Tampered' ] )
        ->call( 'save' )
        ->assertNotFound();

    expect( $recordB->fresh()->label )->toBe( 'Paris' );
} );

test( 'field builder rejects a duplicate slug with a validation error, not a 500', function (): void {
    app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'brand',
        'name'        => 'Brand',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    Livewire::test( FieldBuilder::class )
        ->set( 'slug', 'brand' )
        ->set( 'name', 'Another Brand' )
        ->set( 'cardinality', 'singleton' )
        ->set( 'fields', [
            [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => false, 'default' => null, 'options' => [] ],
        ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'slug' ] );

    expect( DynamicContentType::where( 'slug', 'brand' )->count() )->toBe( 1 );
} );

test( 'field builder rejects changing the slug of an existing type', function (): void {
    $type = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'brand',
        'name'        => 'Brand',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    Livewire::test( FieldBuilder::class, [ 'typeId' => $type->id ] )
        ->set( 'slug', 'rebranded' )
        ->call( 'save' )
        ->assertHasErrors( [ 'slug' ] );

    expect( $type->fresh()->slug )->toBe( 'brand' );
} );

test( 'field builder forbids nulling the locked typeId post-mount', function (): void {
    $type = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'brand',
        'name'        => 'Brand',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    expect( fn () => Livewire::test( FieldBuilder::class, [ 'typeId' => $type->id ] )
        ->set( 'typeId', null ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} );

test( 'field builder denies creating a type to a user without the create capability', function (): void {
    Gate::define( 'manage_dynamic_content', fn () => false );

    Livewire::test( FieldBuilder::class )
        ->assertForbidden();
} );

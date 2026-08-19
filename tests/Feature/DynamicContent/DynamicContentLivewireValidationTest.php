<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire\CollectionEditor;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire\FieldBuilder;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;
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

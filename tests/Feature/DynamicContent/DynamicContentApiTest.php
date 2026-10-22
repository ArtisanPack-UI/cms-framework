<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Providers\DynamicContentServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
    $this->user = TestUser::create( [
        'name'     => 'Admin',
        'email'    => 'a@example.com',
        'password' => bcrypt( 'x' ),
    ] );

    // The migration run seeds the capability, and rbac's `Gate::before`
    // lets a seeded permission decide on its own. Drop it so these
    // tests' `Gate::define` stays in control. Delete through the model so
    // rbac's permission-name cache is flushed too.
    Permission::where( 'slug', DynamicContentServiceProvider::CAPABILITY )->get()->each->delete();

    Gate::define( 'manage_dynamic_content', fn () => true );
} );

test( 'authenticated user can list types', function (): void {
    app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'business_info',
        'name'        => 'Business Info',
        'cardinality' => 'singleton',
        'fields'      => [],
    ] );

    $this->actingAs( $this->user )
        ->getJson( '/api/v1/dynamic-content/types' )
        ->assertOk()
        ->assertJsonStructure( [ 'data' => [ [ 'slug', 'name', 'cardinality', 'source' ] ] ] );
} );

test( 'unauthenticated user is rejected', function (): void {
    $this->getJson( '/api/v1/dynamic-content/types' )->assertUnauthorized();
} );

test( 'resolve preview endpoint returns rendered content', function (): void {
    $type = app( DynamicContentTypeManager::class )->create( [
        'slug'        => 'brand',
        'name'        => 'Brand',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    app( ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentRecordManager::class )
        ->create( $type, [ 'values' => [ 'name' => 'Acme' ] ] );

    $this->actingAs( $this->user )
        ->postJson( '/api/v1/dynamic-content/resolve', [ 'content' => 'Hi from {{brand.name}}' ] )
        ->assertOk()
        ->assertJsonPath( 'data.rendered', 'Hi from Acme' );
} );

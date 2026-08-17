<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\DependencyNotSatisfiedException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginConflictException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\DependencyOrder\BootOrderRecorder;
use ArtisanPackUI\CMSFramework\Tests\Support\DependencyOrder\DependencyProvider;
use ArtisanPackUI\CMSFramework\Tests\Support\DependencyOrder\DependentProvider;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->manager = app( PluginManager::class );
    $this->admin   = grantPermissions( TestUser::factory()->create(), 'manage-plugins' );
} );

/**
 * Persist a plugin record with an optional requires/conflicts manifest.
 */
function makePlugin( string $slug, array $overrides = [], array $meta = [] ): Plugin
{
    return Plugin::create( array_merge( [
        'slug'      => $slug,
        'name'      => ucfirst( $slug ),
        'version'   => '1.0.0',
        'is_active' => false,
        'meta'      => array_merge( ['slug' => $slug], $meta ),
    ], $overrides ) );
}

describe( 'Activation dependency gate', function (): void {
    it( 'refuses to activate a plugin whose dependency is not installed', function (): void {
        makePlugin( 'google-web-tools', [], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        try {
            $this->manager->activate( 'google-web-tools' );
            $this->fail( 'Expected DependencyNotSatisfiedException.' );
        } catch ( DependencyNotSatisfiedException $e ) {
            expect( $e->pluginSlug )->toBe( 'google-web-tools' )
                ->and( $e->result?->missing )->toBe( ['google-oauth'] );
        }

        expect( Plugin::where( 'slug', 'google-web-tools' )->first()->is_active )->toBeFalse();
    } );

    it( 'refuses to activate a plugin whose dependency is installed but inactive', function (): void {
        makePlugin( 'google-oauth', ['is_active' => false] );
        makePlugin( 'google-web-tools', [], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        expect( fn () => $this->manager->activate( 'google-web-tools' ) )
            ->toThrow( DependencyNotSatisfiedException::class );
    } );

    it( 'activates once the dependency is active and the constraint matches', function (): void {
        makePlugin( 'google-oauth', ['is_active' => true, 'version' => '1.4.0'] );
        makePlugin( 'google-web-tools', [], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        expect( $this->manager->activate( 'google-web-tools' ) )->toBeTrue();
        expect( Plugin::where( 'slug', 'google-web-tools' )->first()->is_active )->toBeTrue();
    } );

    it( 'refuses activation on a version mismatch', function (): void {
        makePlugin( 'google-oauth', ['is_active' => true, 'version' => '0.9.0'] );
        makePlugin( 'google-web-tools', [], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        try {
            $this->manager->activate( 'google-web-tools' );
            $this->fail( 'Expected DependencyNotSatisfiedException.' );
        } catch ( DependencyNotSatisfiedException $e ) {
            expect( $e->result?->versionMismatch )->toBe( [
                ['slug' => 'google-oauth', 'required' => '^1.0', 'installed' => '0.9.0'],
            ] );
        }
    } );

    it( 'refuses activation when a conflict is installed and in range', function (): void {
        makePlugin( 'legacy-forms', ['is_active' => true, 'version' => '2.0.0'] );
        makePlugin( 'advanced-forms', [], ['conflicts' => ['legacy-forms' => '*']] );

        try {
            $this->manager->activate( 'advanced-forms' );
            $this->fail( 'Expected PluginConflictException.' );
        } catch ( PluginConflictException $e ) {
            expect( $e->pluginSlug )->toBe( 'advanced-forms' )
                ->and( $e->conflicts )->toBe( [
                    ['slug' => 'legacy-forms', 'constraint' => '*', 'installed' => '2.0.0'],
                ] );
        }
    } );

    it( 'refuses activation when another installed plugin declares the conflict (reverse)', function (): void {
        // Bypass attempt: activate the non-declaring plugin second. The conflict
        // `advanced-forms` asserted must still block `legacy-forms`.
        makePlugin( 'advanced-forms', ['is_active' => true, 'version' => '2.0.0'], ['conflicts' => ['legacy-forms' => '*']] );
        makePlugin( 'legacy-forms', ['version' => '1.0.0'] );

        try {
            $this->manager->activate( 'legacy-forms' );
            $this->fail( 'Expected PluginConflictException.' );
        } catch ( PluginConflictException $e ) {
            expect( collect( $e->conflicts )->pluck( 'slug' )->all() )->toContain( 'advanced-forms' );
        }
    } );

    it( 'refuses activation when the cms-framework constraint is unmet', function (): void {
        makePlugin( 'needs-newer', [], ['requires' => ['cms-framework' => '^999.0']] );

        try {
            $this->manager->activate( 'needs-newer' );
            $this->fail( 'Expected DependencyNotSatisfiedException.' );
        } catch ( DependencyNotSatisfiedException $e ) {
            expect( collect( $e->result?->versionMismatch )->pluck( 'slug' )->all() )
                ->toContain( 'cms-framework' );
        }
    } );

    it( 'treats a self-dependency as an unsatisfiable requirement', function (): void {
        makePlugin( 'selfish', [], ['requires' => ['plugins' => ['selfish' => '^1.0']]] );

        expect( fn () => $this->manager->activate( 'selfish' ) )
            ->toThrow( DependencyNotSatisfiedException::class );
    } );
} );

describe( 'Boot-time load order', function (): void {
    it( 'registers active plugin providers dependencies-first', function (): void {
        BootOrderRecorder::reset();

        // Dependent is inserted first (lower id), so an id-ordered load would
        // boot it before its dependency.
        makePlugin( 'dependent-plugin', [
            'is_active'        => true,
            'service_provider' => DependentProvider::class,
        ], ['requires' => ['plugins' => ['dependency-plugin' => '^1.0']]] );

        makePlugin( 'dependency-plugin', [
            'is_active'        => true,
            'service_provider' => DependencyProvider::class,
        ] );

        $this->manager->loadActivePlugins();

        expect( BootOrderRecorder::$order )->toBe( ['dependency-plugin', 'dependent-plugin'] );
    } );
} );

describe( 'Deactivation dependent guard', function (): void {
    it( 'refuses to deactivate a plugin with active dependents', function (): void {
        makePlugin( 'google-oauth', ['is_active' => true] );
        makePlugin( 'google-web-tools', ['is_active' => true], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        try {
            $this->manager->deactivate( 'google-oauth' );
            $this->fail( 'Expected DependencyNotSatisfiedException.' );
        } catch ( DependencyNotSatisfiedException $e ) {
            expect( $e->dependents )->toBe( ['google-web-tools'] );
        }

        expect( Plugin::where( 'slug', 'google-oauth' )->first()->is_active )->toBeTrue();
    } );

    it( 'allows deactivation once the dependent is inactive', function (): void {
        makePlugin( 'google-oauth', ['is_active' => true] );
        makePlugin( 'google-web-tools', ['is_active' => false], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        expect( $this->manager->deactivate( 'google-oauth' ) )->toBeTrue();
    } );

    it( 'force-deactivates from delete even with active dependents', function (): void {
        makePlugin( 'google-oauth', ['is_active' => true] );
        makePlugin( 'google-web-tools', ['is_active' => true], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        expect( $this->manager->delete( 'google-oauth', false ) )->toBeTrue();
        expect( Plugin::where( 'slug', 'google-oauth' )->exists() )->toBeFalse();
    } );
} );

describe( 'Manager query helpers', function (): void {
    it( 'reports dependents and deactivation eligibility', function (): void {
        makePlugin( 'google-oauth', ['is_active' => true] );
        makePlugin( 'google-web-tools', ['is_active' => true], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        expect( $this->manager->getDependents( 'google-oauth' ) )->toBe( ['google-web-tools'] )
            ->and( $this->manager->canDeactivate( 'google-oauth' ) )->toBeFalse()
            ->and( $this->manager->canDeactivate( 'google-web-tools' ) )->toBeTrue();
    } );

    it( 'computes a dependency-first activation order', function (): void {
        makePlugin( 'google-oauth' );
        makePlugin( 'google-web-tools', [], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        expect( $this->manager->getActivationOrder( ['google-web-tools'] ) )
            ->toBe( ['google-oauth', 'google-web-tools'] );
    } );

    it( 'resolves requires from the manifest for a plugin not yet in the database', function (): void {
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath . '/diskonly' );
        File::put( $pluginsPath . '/diskonly/plugin.json', json_encode( [
            'slug'     => 'diskonly',
            'name'     => 'Disk Only',
            'version'  => '1.0.0',
            'requires' => ['plugins' => ['missing-dep' => '^1.0']],
        ] ) );

        $result = $this->manager->checkDependencies( 'diskonly' );

        expect( $result->missing )->toBe( ['missing-dep'] );

        File::deleteDirectory( $pluginsPath . '/diskonly' );
    } );
} );

describe( 'Dependency API', function (): void {
    it( 'returns a 409 with a structured payload when a dependency is missing', function (): void {
        makePlugin( 'google-web-tools', [], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        $response = $this->actingAs( $this->admin )
            ->postJson( '/api/v1/plugins/google-web-tools/activate' );

        $response->assertStatus( 409 )
            ->assertJsonPath( 'code', 'plugin_dependencies_unsatisfied' )
            ->assertJsonPath( 'plugin', 'google-web-tools' )
            ->assertJsonPath( 'dependencies.missing.0', 'google-oauth' );
    } );

    it( 'returns a 409 with a conflict payload', function (): void {
        makePlugin( 'legacy-forms', ['is_active' => true, 'version' => '2.0.0'] );
        makePlugin( 'advanced-forms', [], ['conflicts' => ['legacy-forms' => '*']] );

        $response = $this->actingAs( $this->admin )
            ->postJson( '/api/v1/plugins/advanced-forms/activate' );

        $response->assertStatus( 409 )
            ->assertJsonPath( 'code', 'plugin_conflict' )
            ->assertJsonPath( 'conflicts.0.slug', 'legacy-forms' );
    } );

    it( 'returns a 409 when deactivating a plugin with active dependents', function (): void {
        makePlugin( 'google-oauth', ['is_active' => true] );
        makePlugin( 'google-web-tools', ['is_active' => true], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        $response = $this->actingAs( $this->admin )
            ->postJson( '/api/v1/plugins/google-oauth/deactivate' );

        $response->assertStatus( 409 )
            ->assertJsonPath( 'code', 'plugin_has_active_dependents' )
            ->assertJsonPath( 'dependents.0', 'google-web-tools' );
    } );

    it( 'lists dependents for a plugin', function (): void {
        makePlugin( 'google-oauth', ['is_active' => true] );
        makePlugin( 'google-web-tools', ['is_active' => true], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        $response = $this->actingAs( $this->admin )
            ->getJson( '/api/v1/plugins/google-oauth/dependents' );

        $response->assertOk()
            ->assertJsonPath( 'dependents.0', 'google-web-tools' )
            ->assertJsonPath( 'can_deactivate', false );
    } );

    it( 'returns an activation order for a batch check', function (): void {
        makePlugin( 'google-oauth' );
        makePlugin( 'google-web-tools', [], ['requires' => ['plugins' => ['google-oauth' => '^1.0']]] );

        $response = $this->actingAs( $this->admin )
            ->postJson( '/api/v1/plugins/check-dependencies', [
                'plugins' => ['google-web-tools', 'google-oauth'],
            ] );

        $response->assertOk()
            ->assertJsonPath( 'order', ['google-oauth', 'google-web-tools'] );
    } );

    it( 'returns a 422 with the cycle for circular dependencies', function (): void {
        makePlugin( 'plugin-a', [], ['requires' => ['plugins' => ['plugin-b' => '^1.0']]] );
        makePlugin( 'plugin-b', [], ['requires' => ['plugins' => ['plugin-a' => '^1.0']]] );

        $response = $this->actingAs( $this->admin )
            ->postJson( '/api/v1/plugins/check-dependencies', [
                'plugins' => ['plugin-a'],
            ] );

        $response->assertStatus( 422 )
            ->assertJsonPath( 'code', 'circular_dependency' );
    } );
} );

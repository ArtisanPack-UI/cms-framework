<?php

declare( strict_types=1 );

/**
 * Feature tests for the WP-shape `/api/v1/settings/site` endpoint.
 *
 * Exercises GET/PUT round-trip, default fallback, partial updates, and
 * `apGetSetting()` parity for the in-process callers (visual-editor's
 * `core/site-*` block resolvers).
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Tests\Feature;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    config( ['artisanpack.cms-framework.user_model' => 'App\\Models\\User'] );

    if ( ! class_exists( 'App\\Models\\User' ) ) {
        eval( '
            namespace App\\Models {
                use Illuminate\\Foundation\\Auth\\User as Authenticatable;
                use ArtisanPackUI\\CMSFramework\\Modules\\Users\\Models\\Concerns\\HasRolesAndPermissions;

                class User extends Authenticatable {
                    use HasRolesAndPermissions;
                    protected $table = "users";
                    protected $fillable = ["name", "email", "password"];
                    protected $hidden = ["password"];
                }
            }
        ' );
    }

    $this->user = User::create( [
        'name'     => 'Site Admin',
        'email'    => 'site-admin@example.com',
        'password' => bcrypt( 'password' ),
    ] );

    $role = Role::create( ['name' => 'Admin', 'slug' => 'admin'] );
    $this->user->roles()->attach( $role );
} );

function grantSiteSettingsManage(): void
{
    Gate::define( 'settings.manage', fn ( User $user ) => true );
}

test( 'unauthenticated user cannot read site meta', function (): void {
    getJson( '/api/v1/settings/site' )->assertUnauthorized();
} );

test( 'user without permission cannot read site meta', function (): void {
    actingAs( $this->user )
        ->getJson( '/api/v1/settings/site' )
        ->assertForbidden();
} );

test( 'GET returns the registered defaults on a fresh install', function (): void {
    grantSiteSettingsManage();

    // The site.title and site.url defaults capture config('app.name')
    // and config('app.url') at boot time — which under Testbench means
    // the framework defaults ('Laravel' and 'http://localhost'). The
    // test asserts pass-through behavior, not the specific values; a
    // real install will surface whatever the host configured.
    actingAs( $this->user )
        ->getJson( '/api/v1/settings/site' )
        ->assertOk()
        ->assertExactJson( [
            'title'       => config( 'app.name' ),
            'description' => '',
            'url'         => config( 'app.url' ),
            'site_logo'   => null,
            'site_icon'   => null,
        ] );
} );

test( 'GET reflects values that have been persisted to the settings table', function (): void {
    grantSiteSettingsManage();

    Setting::create( ['key' => 'site.title',   'value' => 'My CMS'] );
    Setting::create( ['key' => 'site.tagline', 'value' => 'Hello, world.'] );
    Setting::create( ['key' => 'site.logo_id', 'value' => 42] );

    actingAs( $this->user )
        ->getJson( '/api/v1/settings/site' )
        ->assertOk()
        ->assertJson( [
            'title'       => 'My CMS',
            'description' => 'Hello, world.',
            'site_logo'   => 42,
        ] );
} );

test( 'PUT round-trips a full WP-shape update', function (): void {
    grantSiteSettingsManage();

    $payload = [
        'title'       => 'Round-trip Site',
        'description' => 'Tagline goes here',
        'url'         => 'https://round-trip.example',
        'site_logo'   => 7,
        'site_icon'   => 11,
    ];

    actingAs( $this->user )
        ->putJson( '/api/v1/settings/site', $payload )
        ->assertOk()
        ->assertExactJson( $payload );

    $this->assertDatabaseHas( 'settings', ['key' => 'site.title', 'value' => 'Round-trip Site'] );
    $this->assertDatabaseHas( 'settings', ['key' => 'site.tagline', 'value' => 'Tagline goes here'] );
    $this->assertDatabaseHas( 'settings', ['key' => 'site.url', 'value' => 'https://round-trip.example'] );
    $this->assertDatabaseHas( 'settings', ['key' => 'site.logo_id', 'value' => '7'] );
    $this->assertDatabaseHas( 'settings', ['key' => 'site.icon_id', 'value' => '11'] );

    expect( apGetSetting( 'site.title' ) )->toBe( 'Round-trip Site' );
    expect( apGetSetting( 'site.logo_id' ) )->toBe( 7 );
} );

test( 'PUT only updates the fields supplied in the body', function (): void {
    grantSiteSettingsManage();

    Setting::create( ['key' => 'site.title',   'value' => 'Original Title'] );
    Setting::create( ['key' => 'site.tagline', 'value' => 'Original Tagline'] );

    actingAs( $this->user )
        ->putJson( '/api/v1/settings/site', ['title' => 'Updated Title'] )
        ->assertOk()
        ->assertJson( [
            'title'       => 'Updated Title',
            'description' => 'Original Tagline',
        ] );

    $this->assertDatabaseHas( 'settings', ['key' => 'site.title', 'value' => 'Updated Title'] );
    $this->assertDatabaseHas( 'settings', ['key' => 'site.tagline', 'value' => 'Original Tagline'] );
} );

test( 'PUT rejects an invalid URL', function (): void {
    grantSiteSettingsManage();

    actingAs( $this->user )
        ->putJson( '/api/v1/settings/site', ['url' => 'not-a-url'] )
        ->assertStatus( 422 )
        ->assertJsonValidationErrors( ['url'] );
} );

test( 'PUT does not re-run viewAny authorization when capabilities are split', function (): void {
    // Diverge the policy filters so view and update bind to separate
    // caps — a host can plausibly do this when delegating settings
    // admin to a write-only role. The user holds the write cap but
    // not the read cap; PUT must still return the updated payload
    // rather than 403'ing on an internal viewAny re-check.
    addFilter( 'settings.viewAny', fn () => 'settings.read' );
    addFilter( 'settings.update', fn () => 'settings.write' );

    Gate::define( 'settings.write', fn ( User $user ) => true );
    Gate::define( 'settings.read', fn ( User $user ) => false );

    actingAs( $this->user )
        ->putJson( '/api/v1/settings/site', ['title' => 'Write-only Title'] )
        ->assertOk()
        ->assertJsonFragment( ['title' => 'Write-only Title'] );
});

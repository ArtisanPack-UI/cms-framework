<?php

declare( strict_types=1 );

/**
 * Feature tests for the by-key bulk-update endpoint `PUT /api/v1/settings`.
 *
 * The generic ID/key CRUD writes straight to the model and skips the
 * registered sanitizer + `SettingType`. This endpoint routes every value
 * through `SettingsManager::updateSetting()` instead, so these tests assert
 * the sanitizer runs, the type is serialized, unregistered keys are rejected,
 * and the `SettingPolicy` gates access.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Tests\Feature;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

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
        'name'     => 'Settings Admin',
        'email'    => 'settings-admin@example.com',
        'password' => bcrypt( 'password' ),
    ] );

    $role = Role::create( ['name' => 'Admin', 'slug' => 'admin'] );
    $this->user->roles()->attach( $role );

    $this->manager = app( SettingsManager::class );
} );

function grantBulkSettingsManage(): void
{
    Gate::define( 'settings.manage', fn ( User $user ) => true );
}

test( 'unauthenticated user cannot bulk-update settings', function (): void {
    putJson( '/api/v1/settings', ['settings' => ['site.title' => 'Nope']] )
        ->assertUnauthorized();
} );

test( 'user without permission cannot bulk-update settings', function (): void {
    actingAs( $this->user )
        ->putJson( '/api/v1/settings', ['settings' => ['site.title' => 'Nope']] )
        ->assertForbidden();
} );

test( 'it applies the registered sanitizer callback', function (): void {
    grantBulkSettingsManage();

    $this->manager->registerSetting( 'blog.title', '', fn ( $value ) => trim( $value ), SettingType::String );

    actingAs( $this->user )
        ->putJson( '/api/v1/settings', ['settings' => ['blog.title' => '  Spaced Out  ']] )
        ->assertOk()
        ->assertJson( ['settings' => ['blog.title' => 'Spaced Out']] );

    $this->assertDatabaseHas( 'settings', ['key' => 'blog.title', 'value' => 'Spaced Out'] );
} );

test( 'it serializes the value using the registered type', function (): void {
    grantBulkSettingsManage();

    $this->manager->registerSetting( 'blog.per_page', 10, 'sanitizeInt', SettingType::Integer );

    $response = actingAs( $this->user )
        ->putJson( '/api/v1/settings', ['settings' => ['blog.per_page' => '25']] )
        ->assertOk();

    // The response echoes the cast value, so the integer round-trips as an int.
    // Index the literal dotted key rather than a dot-path lookup, which would
    // otherwise read it as nested `settings.blog.per_page`.
    expect( $response->json( 'settings' )['blog.per_page'] )->toBe( 25 );

    $this->assertDatabaseHas( 'settings', [
        'key'   => 'blog.per_page',
        'value' => '25',
        'type'  => 'integer',
    ] );
} );

test( 'stored values match SettingsManager::updateSetting output', function (): void {
    grantBulkSettingsManage();

    $this->manager->registerSetting( 'blog.per_page', 10, 'sanitizeInt', SettingType::Integer );

    actingAs( $this->user )
        ->putJson( '/api/v1/settings', ['settings' => ['blog.per_page' => '42']] )
        ->assertOk();

    $viaEndpoint = Setting::where( 'key', 'blog.per_page' )->first();

    // Drive the same key through the manager directly and confirm the stored
    // representation is identical to what the endpoint produced.
    $this->manager->updateSetting( 'blog.per_page', '42' );
    $viaManager = Setting::where( 'key', 'blog.per_page' )->first();

    expect( $viaManager->getRawOriginal( 'value' ) )->toBe( $viaEndpoint->getRawOriginal( 'value' ) );
    expect( $viaManager->getRawOriginal( 'type' ) )->toBe( $viaEndpoint->getRawOriginal( 'type' ) );
} );

test( 'it updates several registered keys in one request', function (): void {
    grantBulkSettingsManage();

    $this->manager->registerSetting( 'blog.title', '', 'sanitizeText', SettingType::String );
    $this->manager->registerSetting( 'blog.per_page', 10, 'sanitizeInt', SettingType::Integer );

    actingAs( $this->user )
        ->putJson( '/api/v1/settings', [
            'settings' => [
                'blog.title'    => 'My Blog',
                'blog.per_page' => '15',
            ],
        ] )
        ->assertOk()
        ->assertJson( [
            'settings' => [
                'blog.title'    => 'My Blog',
                'blog.per_page' => 15,
            ],
        ] );

    $this->assertDatabaseHas( 'settings', ['key' => 'blog.title', 'value' => 'My Blog'] );
    $this->assertDatabaseHas( 'settings', ['key' => 'blog.per_page', 'value' => '15'] );
} );

test( 'it rejects an unregistered key', function (): void {
    grantBulkSettingsManage();

    actingAs( $this->user )
        ->putJson( '/api/v1/settings', ['settings' => ['totally.unregistered' => 'value']] )
        ->assertStatus( 422 )
        ->assertJsonValidationErrors( ['settings.totally.unregistered'] );

    $this->assertDatabaseMissing( 'settings', ['key' => 'totally.unregistered'] );
} );

test( 'it rejects a request with no settings', function (): void {
    grantBulkSettingsManage();

    actingAs( $this->user )
        ->putJson( '/api/v1/settings', ['settings' => []] )
        ->assertStatus( 422 )
        ->assertJsonValidationErrors( ['settings'] );
} );

test( 'it rejects a request missing the settings key entirely', function (): void {
    grantBulkSettingsManage();

    actingAs( $this->user )
        ->putJson( '/api/v1/settings', [] )
        ->assertStatus( 422 )
        ->assertJsonValidationErrors( ['settings'] );
});

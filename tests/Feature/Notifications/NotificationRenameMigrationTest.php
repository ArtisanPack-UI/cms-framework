<?php

declare( strict_types=1 );

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses( Illuminate\Foundation\Testing\RefreshDatabase::class );

/**
 * Load the rename migration's anonymous class instance from its file.
 */
function renameMigration(): object
{
    $path = glob( __DIR__ . '/../../../src/Modules/Notifications/database/migrations/*_rename_notification_tables_to_cms_prefix.php' )[0];

    return require $path;
}

/**
 * Drop the `cms_`-prefixed notification tables (children first) so a legacy
 * schema can be staged in their place.
 */
function dropCmsNotificationTables(): void
{
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists( 'cms_notification_user' );
    Schema::dropIfExists( 'cms_notification_preferences' );
    Schema::dropIfExists( 'cms_notifications' );
    Schema::enableForeignKeyConstraints();
}

test( 'up renames a legacy schema and preserves its data', function (): void {
    dropCmsNotificationTables();

    Schema::create( 'notifications', function ( Blueprint $table ): void {
        $table->id();
        $table->string( 'title' );
    } );
    Schema::create( 'notification_user', function ( Blueprint $table ): void {
        $table->id();
    } );
    Schema::create( 'notification_preferences', function ( Blueprint $table ): void {
        $table->id();
    } );

    DB::table( 'notifications' )->insert( ['title' => 'Legacy row'] );

    renameMigration()->up();

    expect( Schema::hasTable( 'cms_notifications' ) )->toBeTrue()
        ->and( Schema::hasTable( 'cms_notification_user' ) )->toBeTrue()
        ->and( Schema::hasTable( 'cms_notification_preferences' ) )->toBeTrue()
        ->and( Schema::hasTable( 'notifications' ) )->toBeFalse()
        ->and( DB::table( 'cms_notifications' )->value( 'title' ) )->toBe( 'Legacy row' );
} );

test( 'up is a no-op on a fresh install where cms_ tables already exist', function (): void {
    // The framework migrations already created the cms_ tables in setUp and
    // left the legacy names free, so up() must rename nothing.
    expect( Schema::hasTable( 'cms_notifications' ) )->toBeTrue()
        ->and( Schema::hasTable( 'notifications' ) )->toBeFalse();

    renameMigration()->up();

    expect( Schema::hasTable( 'cms_notifications' ) )->toBeTrue()
        ->and( Schema::hasTable( 'notifications' ) )->toBeFalse();
} );

test( 'down is a non-destructive no-op that leaves the cms_ tables in place', function (): void {
    renameMigration()->down();

    expect( Schema::hasTable( 'cms_notifications' ) )->toBeTrue()
        ->and( Schema::hasTable( 'cms_notification_user' ) )->toBeTrue()
        ->and( Schema::hasTable( 'cms_notification_preferences' ) )->toBeTrue()
        ->and( Schema::hasTable( 'notifications' ) )->toBeFalse();
} );

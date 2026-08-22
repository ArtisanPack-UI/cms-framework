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
        $table->text( 'content' );
        $table->boolean( 'send_email' )->default( false );
    } );
    Schema::create( 'notification_user', function ( Blueprint $table ): void {
        $table->id();
    } );
    Schema::create( 'notification_preferences', function ( Blueprint $table ): void {
        $table->id();
    } );

    DB::table( 'notifications' )->insert( ['title' => 'Legacy row', 'content' => 'Body'] );

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

test( 'up does not rename Laravel\'s own notifications table', function (): void {
    dropCmsNotificationTables();

    // Stage the shape Laravel's database notification channel uses. There are
    // no cms_ tables at all, so the naive `hasTable` guard alone would rename
    // this into `cms_notifications` and strand the CMS model.
    Schema::create( 'notifications', function ( Blueprint $table ): void {
        $table->uuid( 'id' )->primary();
        $table->string( 'type' );
        $table->morphs( 'notifiable' );
        $table->text( 'data' );
        $table->timestamp( 'read_at' )->nullable();
        $table->timestamps();
    } );

    DB::table( 'notifications' )->insert( [
        'id'              => '11111111-1111-1111-1111-111111111111',
        'type'            => 'App\\Notifications\\Welcome',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id'   => 1,
        'data'            => '{}',
    ] );

    renameMigration()->up();

    expect( Schema::hasTable( 'notifications' ) )->toBeTrue()
        ->and( Schema::hasTable( 'cms_notifications' ) )->toBeFalse()
        ->and( DB::table( 'notifications' )->count() )->toBe( 1 );
} );

test( 'up is idempotent after a real legacy rename', function (): void {
    dropCmsNotificationTables();

    Schema::create( 'notifications', function ( Blueprint $table ): void {
        $table->id();
        $table->string( 'title' );
        $table->text( 'content' );
        $table->boolean( 'send_email' )->default( false );
    } );
    Schema::create( 'notification_user', function ( Blueprint $table ): void {
        $table->id();
    } );
    Schema::create( 'notification_preferences', function ( Blueprint $table ): void {
        $table->id();
    } );

    DB::table( 'notifications' )->insert( ['title' => 'Legacy row', 'content' => 'Body'] );

    renameMigration()->up();
    renameMigration()->up();

    expect( Schema::hasTable( 'cms_notifications' ) )->toBeTrue()
        ->and( Schema::hasTable( 'notifications' ) )->toBeFalse()
        ->and( DB::table( 'cms_notifications' )->count() )->toBe( 1 );
} );

test( 'up preserves the pivot foreign key pointing at the renamed table', function (): void {
    dropCmsNotificationTables();

    Schema::create( 'notifications', function ( Blueprint $table ): void {
        $table->id();
        $table->string( 'title' );
        $table->text( 'content' );
        $table->boolean( 'send_email' )->default( false );
    } );
    Schema::create( 'notification_user', function ( Blueprint $table ): void {
        $table->id();
        $table->foreignId( 'notification_id' )->constrained( 'notifications' )->cascadeOnDelete();
        $table->unsignedBigInteger( 'user_id' );
    } );

    $notificationId = DB::table( 'notifications' )->insertGetId( [
        'title'   => 'Legacy row',
        'content' => 'Body',
    ] );
    DB::table( 'notification_user' )->insert( [
        'notification_id' => $notificationId,
        'user_id'         => 1,
    ] );

    renameMigration()->up();

    expect( Schema::hasTable( 'cms_notifications' ) )->toBeTrue()
        ->and( Schema::hasTable( 'cms_notification_user' ) )->toBeTrue()
        ->and( DB::table( 'cms_notification_user' )->count() )->toBe( 1 );

    // SQLite rewrites foreign-key references when a table is renamed, so the
    // pivot FK must now point at `cms_notifications`. ( Runtime cascade cannot
    // be asserted here: the test connection leaves PRAGMA foreign_keys off and
    // RefreshDatabase's per-test transaction blocks toggling it. )
    $foreignKeys = DB::select( 'PRAGMA foreign_key_list( cms_notification_user )' );

    expect( $foreignKeys )->not->toBeEmpty()
        ->and( $foreignKeys[0]->table )->toBe( 'cms_notifications' );
} );

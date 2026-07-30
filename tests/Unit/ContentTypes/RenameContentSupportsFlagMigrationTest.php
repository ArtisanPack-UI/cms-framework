<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use Illuminate\Support\Facades\DB;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'the up migration rewrites content to editor', function (): void {
    $ct = ContentType::create( [
        'name'          => 'Legacy Type',
        'slug'          => 'legacy',
        'table_name'    => 'legacies',
        'model_class'   => 'App\\Models\\Legacy',
        'hierarchical'  => false,
        'has_archive'   => false,
        'public'        => true,
        'show_in_admin' => true,
        'supports'      => [ 'title', 'content', 'excerpt' ],
    ] );

    rerunRenameSupportsMigration( 'up' );

    expect( ContentType::find( $ct->id )->supports )->toBe( [ 'title', 'editor', 'excerpt' ] );
} );

test( 'the up migration collapses duplicate flags when content coexists with editor', function (): void {
    // A row carrying both flags — e.g. a plugin pre-registered `editor`
    // before the migration ran — must not persist `['editor', 'editor']`.
    DB::table( 'content_types' )->insert( [
        'name'          => 'Mixed Type',
        'slug'          => 'mixed',
        'table_name'    => 'mixes',
        'model_class'   => 'App\\Models\\Mix',
        'hierarchical'  => false,
        'has_archive'   => false,
        'public'        => true,
        'show_in_admin' => true,
        'supports'      => json_encode( [ 'title', 'content', 'editor', 'seo' ] ),
        'created_at'    => now(),
        'updated_at'    => now(),
    ] );

    rerunRenameSupportsMigration( 'up' );

    $supports = ContentType::where( 'slug', 'mixed' )->firstOrFail()->supports;

    expect( $supports )->toBe( [ 'title', 'editor', 'seo' ] );
} );

test( 'the down migration reverts editor to content', function (): void {
    $ct = ContentType::create( [
        'name'          => 'Legacy Type',
        'slug'          => 'legacy',
        'table_name'    => 'legacies',
        'model_class'   => 'App\\Models\\Legacy',
        'hierarchical'  => false,
        'has_archive'   => false,
        'public'        => true,
        'show_in_admin' => true,
        'supports'      => [ 'title', 'editor', 'excerpt' ],
    ] );

    rerunRenameSupportsMigration( 'down' );

    expect( ContentType::find( $ct->id )->supports )->toBe( [ 'title', 'content', 'excerpt' ] );
} );

test( 'null and empty supports arrays are left alone', function (): void {
    ContentType::create( [
        'name'          => 'Null Supports',
        'slug'          => 'nulls',
        'table_name'    => 'nulls',
        'model_class'   => 'App\\Models\\Nothing',
        'hierarchical'  => false,
        'has_archive'   => false,
        'public'        => true,
        'show_in_admin' => true,
        'supports'      => null,
    ] );
    ContentType::create( [
        'name'          => 'Empty Supports',
        'slug'          => 'empties',
        'table_name'    => 'empties',
        'model_class'   => 'App\\Models\\Empty',
        'hierarchical'  => false,
        'has_archive'   => false,
        'public'        => true,
        'show_in_admin' => true,
        'supports'      => [],
    ] );

    rerunRenameSupportsMigration( 'up' );

    expect( ContentType::where( 'slug', 'nulls' )->firstOrFail()->supports )->toBeNull();
    expect( ContentType::where( 'slug', 'empties' )->firstOrFail()->supports )->toBe( [] );
} );

/**
 * Re-run just the flag-rename migration ( already applied by `migrate` in the
 * beforeEach hook ) so the test controls the pre-migration seed state.
 */
function rerunRenameSupportsMigration( string $direction ): void
{
    $path     = realpath( __DIR__ . '/../../../src/Modules/ContentTypes/database/migrations/2026_07_30_120000_rename_content_supports_flag_to_editor.php' );
    $instance = require $path;

    if ( 'up' === $direction ) {
        $instance->up();
        return;
    }

    $instance->down();
}

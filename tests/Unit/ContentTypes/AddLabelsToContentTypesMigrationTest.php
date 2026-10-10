<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

/**
 * Insert a raw `content_types` row, bypassing the model.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertLegacyContentTypeRow( string $name, string $slug, array $overrides = [] ): int
{
    return DB::table( 'content_types' )->insertGetId( array_merge( [
        'name'        => $name,
        'slug'        => $slug,
        'table_name'  => str_replace( '-', '_', $slug ),
        'model_class' => 'App\\Models\\Thing',
        'created_at'  => now(),
        'updated_at'  => now(),
    ], $overrides ) );
}

/**
 * Re-run the labels migration ( already applied by `migrate` in the
 * beforeEach hook ) so the test controls the pre-migration seed state.
 */
function rerunAddLabelsMigration( string $direction ): void
{
    $path     = realpath( __DIR__ . '/../../../src/Modules/ContentTypes/database/migrations/2026_10_10_000000_add_labels_to_content_types_table.php' );
    $instance = require $path;

    if ( 'up' === $direction ) {
        $instance->up();

        return;
    }

    $instance->down();
}

test( 'the migration adds the label columns', function (): void {
    expect( Schema::hasColumns( 'content_types', [ 'singular_name', 'plural_name', 'labels' ] ) )->toBeTrue();
} );

test( 'the up migration backfills singular and plural names from name', function (): void {
    rerunAddLabelsMigration( 'down' );

    insertLegacyContentTypeRow( 'Package', 'package' );
    insertLegacyContentTypeRow( 'Case Study', 'case-study' );

    rerunAddLabelsMigration( 'up' );

    $package   = DB::table( 'content_types' )->where( 'slug', 'package' )->first();
    $caseStudy = DB::table( 'content_types' )->where( 'slug', 'case-study' )->first();

    expect( $package->singular_name )->toBe( 'Package' )
        ->and( $package->plural_name )->toBe( 'Packages' )
        ->and( $package->labels )->toBeNull()
        ->and( $caseStudy->singular_name )->toBe( 'Case Study' )
        ->and( $caseStudy->plural_name )->toBe( 'Case Studies' );
} );

test( 'the backfill leaves already-set labels alone', function (): void {
    insertLegacyContentTypeRow( 'Package', 'package', [
        'singular_name' => 'Bundle',
        'plural_name'   => null,
    ] );
    insertLegacyContentTypeRow( 'Person', 'person', [
        'singular_name' => null,
        'plural_name'   => 'Folks',
    ] );

    $migration = require realpath( __DIR__ . '/../../../src/Modules/ContentTypes/database/migrations/2026_10_10_000000_add_labels_to_content_types_table.php' );
    ( fn () => $this->backfillLabels() )->call( $migration );

    $package = DB::table( 'content_types' )->where( 'slug', 'package' )->first();
    $person  = DB::table( 'content_types' )->where( 'slug', 'person' )->first();

    expect( $package->singular_name )->toBe( 'Bundle' )
        ->and( $package->plural_name )->toBe( 'Bundles' )
        ->and( $person->singular_name )->toBe( 'Person' )
        ->and( $person->plural_name )->toBe( 'Folks' );
} );

test( 'the down migration drops the label columns', function (): void {
    rerunAddLabelsMigration( 'down' );

    expect( Schema::hasColumn( 'content_types', 'singular_name' ) )->toBeFalse()
        ->and( Schema::hasColumn( 'content_types', 'plural_name' ) )->toBeFalse()
        ->and( Schema::hasColumn( 'content_types', 'labels' ) )->toBeFalse();
} );

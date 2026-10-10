<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Add singular / plural labels and an optional `labels` override set to
 * `content_types` so consumers can render "Packages", "Add New Package" and
 * "Edit Package" instead of reusing the single `name` everywhere.
 *
 * Existing rows are backfilled with `singular_name = name` and
 * `plural_name = Str::plural( name )`. `labels` stays null — the model
 * derives the full label set from the singular / plural forms.
 *
 * @since 2.13.0
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table( 'content_types', function ( Blueprint $table ): void {
            $table->string( 'singular_name' )->nullable()->after( 'name' );
            $table->string( 'plural_name' )->nullable()->after( 'singular_name' );
            $table->json( 'labels' )->nullable()->after( 'plural_name' );
        } );

        $this->backfillLabels();
    }

    public function down(): void
    {
        Schema::table( 'content_types', function ( Blueprint $table ): void {
            $table->dropColumn( [ 'singular_name', 'plural_name', 'labels' ] );
        } );
    }

    /**
     * Row-by-row backfill — pluralization happens in PHP, so it can't be a
     * single UPDATE. Only null columns are filled, which keeps a re-run from
     * clobbering labels set after the first run.
     */
    private function backfillLabels(): void
    {
        DB::table( 'content_types' )
            ->select( [ 'id', 'name', 'singular_name', 'plural_name' ] )
            ->orderBy( 'id' )
            ->chunkById( 200, function ( $rows ): void {
                foreach ( $rows as $row ) {
                    $updates = [];

                    if ( null === $row->singular_name ) {
                        $updates['singular_name'] = $row->name;
                    }

                    if ( null === $row->plural_name ) {
                        $updates['plural_name'] = Str::plural( $row->singular_name ?? $row->name );
                    }

                    if ( [] !== $updates ) {
                        DB::table( 'content_types' )->where( 'id', $row->id )->update( $updates );
                    }
                }
            } );
    }
};

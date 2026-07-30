<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename the `content` supports flag to `editor` on every persisted content
 * type row so the canonical vocabulary in {@see ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\SupportsFeature}
 * ( which drops `content` in favor of `editor` ) has no stale carryover.
 *
 * The underlying DB column driven by that flag ( `content` on the record
 * table ) is unchanged — this migration is purely about the flag string
 * stored inside the JSON `supports` column on `content_types`.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->rewriteSupportsFlag( 'content', 'editor' );
    }

    public function down(): void
    {
        $this->rewriteSupportsFlag( 'editor', 'content' );
    }

    /**
     * Row-by-row rewrite of the `content_types.supports` JSON column so we
     * don't rely on driver-specific JSON functions ( sqlite CI and MySQL
     * production have to behave the same ).
     */
    private function rewriteSupportsFlag( string $from, string $to ): void
    {
        if ( ! Schema::hasTable( 'content_types' ) ) {
            return;
        }

        DB::table( 'content_types' )
            ->select( ['id', 'supports'] )
            ->orderBy( 'id' )
            ->chunkById( 200, function ( $rows ) use ( $from, $to ): void {
                foreach ( $rows as $row ) {
                    $decoded = is_string( $row->supports ) ? json_decode( $row->supports, true ) : $row->supports;

                    if ( ! is_array( $decoded ) ) {
                        continue;
                    }

                    $rewritten = [];
                    $changed   = false;

                    foreach ( $decoded as $flag ) {
                        if ( $flag === $from ) {
                            $rewritten[] = $to;
                            $changed     = true;
                            continue;
                        }

                        $rewritten[] = $flag;
                    }

                    // Dedup after rewrite so a row that already carried the
                    // target flag alongside the legacy one — e.g. a plugin
                    // that pre-registered `editor` while the DB still held
                    // `content` — collapses to a single canonical entry
                    // instead of persisting `['editor', 'editor']`. Order
                    // is preserved: array_unique keeps the first occurrence.
                    $deduped = array_values( array_unique( $rewritten ) );

                    if ( $changed || $deduped !== $rewritten ) {
                        DB::table( 'content_types' )
                            ->where( 'id', $row->id )
                            ->update( ['supports' => json_encode( $deduped )] );
                    }
                }
            } );
    }
};

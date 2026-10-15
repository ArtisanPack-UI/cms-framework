<?php

/**
 * Add the WordPress pattern-header columns to `block_patterns`.
 *
 * `keywords`, `inserter`, `template_types`, `post_types` and
 * `viewport_width` mirror the `Keywords:`, `Inserter:`, `Template Types:`,
 * `Post Types:` and `Viewport Width:` headers of theme pattern files, so a
 * user pattern (including one cloned from a theme pattern) keeps the
 * same inserter metadata.
 *
 * @since      2.15.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Columns this migration adds, in order.
     *
     * @var array<int, string>
     */
    private array $columns = ['keywords', 'inserter', 'template_types', 'post_types', 'viewport_width'];

    public function up(): void
    {
        if ( ! Schema::hasTable( 'block_patterns' ) || Schema::hasColumn( 'block_patterns', 'keywords' ) ) {
            return;
        }

        Schema::table( 'block_patterns', function ( Blueprint $table ): void {
            $table->json( 'keywords' )->nullable()->after( 'block_types' );
            $table->boolean( 'inserter' )->default( true )->after( 'keywords' );
            $table->json( 'template_types' )->nullable()->after( 'inserter' );
            $table->json( 'post_types' )->nullable()->after( 'template_types' );
            $table->unsignedSmallInteger( 'viewport_width' )->nullable()->after( 'post_types' );
        } );
    }

    public function down(): void
    {
        if ( ! Schema::hasTable( 'block_patterns' ) || ! Schema::hasColumn( 'block_patterns', 'keywords' ) ) {
            return;
        }

        Schema::table( 'block_patterns', function ( Blueprint $table ): void {
            $table->dropColumn( $this->columns );
        } );
    }
};

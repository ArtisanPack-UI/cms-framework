<?php

/**
 * Add a `block_attributes` JSON column to `menu_items`.
 *
 * Stores the navigation-block attributes that have no dedicated column
 * (e.g. the visual editor's `artisanpackVisibility`, `artisanpackAnimations`,
 * or block bindings) so they survive a round trip through the menu store.
 *
 * @since      2.11.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if ( ! Schema::hasTable( 'menu_items' ) || Schema::hasColumn( 'menu_items', 'block_attributes' ) ) {
            return;
        }

        Schema::table( 'menu_items', function ( Blueprint $table ): void {
            $table->json( 'block_attributes' )->nullable()->after( 'kind' );
        } );
    }

    public function down(): void
    {
        if ( ! Schema::hasTable( 'menu_items' ) || ! Schema::hasColumn( 'menu_items', 'block_attributes' ) ) {
            return;
        }

        Schema::table( 'menu_items', function ( Blueprint $table ): void {
            $table->dropColumn( 'block_attributes' );
        } );
    }
};

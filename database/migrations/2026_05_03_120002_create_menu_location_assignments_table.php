<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'menu_location_assignments', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'theme' );
            $table->string( 'location' );
            $table->foreignId( 'menu_id' )->constrained( 'menus' )->cascadeOnDelete();
            $table->timestamps();

            $table->unique( ['theme', 'location'] );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'menu_location_assignments' );
    }
};

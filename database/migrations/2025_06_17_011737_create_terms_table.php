<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'terms', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'name' );
            $table->string( 'slug' )->unique();
            $table->foreignId( 'taxonomy_id' )->constrained( 'taxonomies' )->onDelete( 'cascade' );
            $table->foreignId( 'parent_id' )->nullable()->constrained( 'terms' )->onDelete( 'set null' );
            $table->timestamps();

            $table->unique( [ 'slug', 'taxonomy_id' ] );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'terms' );
    }
};

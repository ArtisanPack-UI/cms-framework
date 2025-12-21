<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create( 'content_types', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'name' );
            $table->string( 'slug' )->unique();
            $table->string( 'table_name' );
            $table->string( 'model_class' );
            $table->text( 'description' )->nullable();
            $table->boolean( 'hierarchical' )->default( false );
            $table->boolean( 'has_archive' )->default( true );
            $table->string( 'archive_slug' )->nullable();
            $table->json( 'supports' )->nullable();
            $table->json( 'metadata' )->nullable();
            $table->boolean( 'public' )->default( true );
            $table->boolean( 'show_in_admin' )->default( true );
            $table->string( 'icon' )->nullable();
            $table->integer( 'menu_position' )->nullable();
            $table->timestamps();

            $table->index( 'slug' );
            $table->index( 'public' );
            $table->index( 'show_in_admin' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'content_types');
    }
};

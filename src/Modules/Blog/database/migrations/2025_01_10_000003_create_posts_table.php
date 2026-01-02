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
        Schema::create( 'posts', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'title' );
            $table->string( 'slug' )->unique();
            $table->longText( 'content' )->nullable();
            $table->text( 'excerpt' )->nullable();
            $table->foreignId( 'author_id' )->constrained( 'users' )->onDelete( 'cascade' );
            $table->string( 'status' )->default( 'draft' );
            $table->timestamp( 'published_at' )->nullable();
            $table->json( 'metadata' )->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index( 'status' );
            $table->index( 'published_at' );
            $table->index( 'author_id' );
            $table->index( 'slug' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'posts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'content', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'slug' )->unique();
            $table->longText( 'content' )->nullable();
            $table->string( 'type' )->index();
            $table->string( 'status' )->default( 'draft' );
            $table->foreignId( 'author_id' )->constrained( 'users' )->onDelete( 'cascade' );
            $table->foreignId( 'parent_id' )->nullable()->constrained( 'content' )->onDelete( 'set null' );
            $table->json( 'meta' )->nullable();
            $table->timestamp( 'published_at' )->nullable();
            $table->timestamps();
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'contents' );
    }
};

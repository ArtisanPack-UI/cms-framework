<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'block_patterns', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'slug' )->unique();
            $table->string( 'theme' )->nullable();
            $table->string( 'title' );
            $table->text( 'description' )->nullable();
            $table->string( 'source' )->default( 'user' );
            $table->boolean( 'synced' )->default( false );
            $table->json( 'categories' )->nullable();
            $table->json( 'block_types' )->nullable();
            $table->json( 'block_content' )->nullable();
            $table->foreignId( 'author_id' )->nullable()->constrained( 'users' )->nullOnDelete();
            $table->timestamps();

            $table->index( ['source', 'synced'] );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'block_patterns' );
    }
};

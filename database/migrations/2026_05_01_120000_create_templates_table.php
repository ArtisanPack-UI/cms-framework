<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'templates', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'theme' );
            $table->string( 'slug' );
            $table->string( 'title' );
            $table->text( 'description' )->nullable();
            $table->string( 'status' )->default( 'publish' );
            $table->boolean( 'is_custom' )->default( false );
            $table->json( 'block_content' )->nullable();
            $table->foreignId( 'author_id' )->nullable()->constrained( 'users' )->nullOnDelete();
            $table->timestamps();

            $table->unique( ['theme', 'slug'] );
            $table->index( 'slug' );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'templates' );
    }
};

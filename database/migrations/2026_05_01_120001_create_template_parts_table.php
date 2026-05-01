<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'template_parts', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'theme' );
            $table->string( 'slug' );
            $table->string( 'title' );
            $table->text( 'description' )->nullable();
            $table->string( 'area' )->default( 'general' );
            $table->string( 'status' )->default( 'publish' );
            $table->boolean( 'is_custom' )->default( false );
            $table->json( 'block_content' )->nullable();
            $table->foreignId( 'author_id' )->nullable()->constrained( 'users' )->nullOnDelete();
            $table->timestamps();

            $table->unique( ['theme', 'slug'] );
            $table->index( 'slug' );
            $table->index( 'area' );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'template_parts' );
    }
};

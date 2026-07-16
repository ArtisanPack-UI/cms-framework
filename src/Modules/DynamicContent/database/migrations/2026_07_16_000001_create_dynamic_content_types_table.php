<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'dynamic_content_types', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'slug' )->unique();
            $table->string( 'name' );
            $table->string( 'cardinality' )->default( 'singleton' );
            $table->string( 'source' )->default( 'db' );
            $table->text( 'description' )->nullable();
            $table->string( 'icon' )->nullable();
            $table->timestamps();

            $table->index( 'source' );
            $table->index( 'cardinality' );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'dynamic_content_types' );
    }
};

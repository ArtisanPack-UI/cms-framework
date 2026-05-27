<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'menus', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'theme' );
            $table->string( 'slug' );
            $table->string( 'name' );
            $table->text( 'description' )->nullable();
            $table->boolean( 'auto_add_pages' )->default( false );
            $table->foreignId( 'author_id' )->nullable()->constrained( 'users' )->nullOnDelete();
            $table->timestamps();

            $table->unique( ['theme', 'slug'] );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'menus' );
    }
};

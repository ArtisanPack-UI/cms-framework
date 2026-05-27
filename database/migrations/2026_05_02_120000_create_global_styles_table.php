<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'global_styles', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'theme' )->unique();
            $table->string( 'title' )->nullable();
            $table->json( 'settings' )->nullable();
            $table->json( 'styles' )->nullable();
            $table->string( 'variation' )->nullable();
            $table->foreignId( 'author_id' )->nullable()->constrained( 'users' )->nullOnDelete();
            $table->timestamps();
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'global_styles' );
    }
};

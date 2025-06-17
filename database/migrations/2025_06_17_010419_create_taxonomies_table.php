<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'taxonomies', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'handle' )->unique();
            $table->string( 'label' );
            $table->string( 'label_plural' );
            $table->json( 'content_types' )->nullable();
            $table->boolean( 'hierarchical' )->default( false );
            $table->timestamps();
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'taxonomies' );
    }
};

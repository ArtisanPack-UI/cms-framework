<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'content_types', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'handle' )->unique();
            $table->string( 'label' );
            $table->string( 'label_plural' );
            $table->string( 'slug' )->unique();
            $table->json( 'definition' );
            $table->timestamps();
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'content_types' );
    }
};

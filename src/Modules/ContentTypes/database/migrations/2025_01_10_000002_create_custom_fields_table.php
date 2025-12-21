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
        Schema::create( 'custom_fields', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'name' );
            $table->string( 'key' )->unique();
            $table->string( 'type' );
            $table->string( 'column_type' );
            $table->text( 'description' )->nullable();
            $table->json( 'content_types' );
            $table->json( 'options' )->nullable();
            $table->integer( 'order' )->default( 0 );
            $table->boolean( 'required' )->default( false );
            $table->string( 'default_value' )->nullable();
            $table->timestamps();

            $table->index( 'key' );
            $table->index( 'type' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'custom_fields');
    }
};

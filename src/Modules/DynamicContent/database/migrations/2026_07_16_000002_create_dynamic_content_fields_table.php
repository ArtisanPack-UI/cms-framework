<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'dynamic_content_fields', function ( Blueprint $table ): void {
            $table->id();
            $table->foreignId( 'dynamic_content_type_id' )
                ->constrained( 'dynamic_content_types' )
                ->cascadeOnDelete();
            $table->string( 'slug' );
            $table->string( 'label' );
            $table->string( 'type' );
            $table->json( 'options' )->nullable();
            $table->text( 'default_value' )->nullable();
            $table->boolean( 'required' )->default( false );
            $table->unsignedInteger( 'order' )->default( 0 );
            $table->timestamps();

            $table->unique( [ 'dynamic_content_type_id', 'slug' ] );
            $table->index( 'order' );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'dynamic_content_fields' );
    }
};

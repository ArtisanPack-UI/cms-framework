<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'dynamic_content_records', function ( Blueprint $table ): void {
            $table->id();
            $table->foreignId( 'dynamic_content_type_id' )
                ->constrained( 'dynamic_content_types' )
                ->cascadeOnDelete();
            $table->string( 'label' )->nullable();
            $table->unsignedInteger( 'order' )->default( 0 );
            $table->timestamps();

            $table->index( [ 'dynamic_content_type_id', 'order' ] );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'dynamic_content_records' );
    }
};

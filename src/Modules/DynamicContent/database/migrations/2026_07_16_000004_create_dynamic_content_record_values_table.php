<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'dynamic_content_record_values', function ( Blueprint $table ): void {
            $table->id();
            $table->foreignId( 'dynamic_content_record_id' )
                ->constrained( 'dynamic_content_records' )
                ->cascadeOnDelete();
            $table->foreignId( 'dynamic_content_field_id' )
                ->constrained( 'dynamic_content_fields' )
                ->cascadeOnDelete();
            $table->json( 'value' )->nullable();
            $table->timestamps();

            $table->unique(
                [ 'dynamic_content_record_id', 'dynamic_content_field_id' ],
                'dc_record_field_unique',
            );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'dynamic_content_record_values' );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Only create if it doesn't exist (may be pre-provisioned in tests)
        if ( ! Schema::hasTable( 'settings' ) ) {
            Schema::create( 'settings', function ( Blueprint $table ): void {
                $table->string( 'key', 191 )->primary();
                $table->text( 'value' )->nullable();
                $table->string( 'type' )->default( 'string' );
                $table->timestamps();
            } );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists( 'settings');
    }
};

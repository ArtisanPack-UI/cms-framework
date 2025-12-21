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
        Schema::create( 'notification_preferences', function ( Blueprint $table ): void {
            $table->id();
            $table->foreignId( 'user_id' )->constrained()->cascadeOnDelete();
            $table->string( 'notification_type' );
            $table->boolean( 'is_enabled' )->default( true );
            $table->boolean( 'email_enabled' )->default( true );
            $table->timestamps();

            $table->unique( ['user_id', 'notification_type'] );
            $table->index( 'user_id' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'notification_preferences');
    }
};

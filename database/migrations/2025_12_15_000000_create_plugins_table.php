<?php

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Check if table already exists and modify it, otherwise create it
        if ( Schema::hasTable( 'plugins' ) ) {
            Schema::table( 'plugins', function ( Blueprint $table ): void {
                // Add new columns if they don't exist
                if ( ! Schema::hasColumn( 'plugins', 'name' ) ) {
                    $table->string( 'name' )->after( 'slug' );
                }
                if ( ! Schema::hasColumn( 'plugins', 'service_provider' ) ) {
                    $table->string( 'service_provider' )->nullable()->after( 'is_active' );
                }
                if ( ! Schema::hasColumn( 'plugins', 'meta' ) ) {
                    $table->json( 'meta' )->nullable()->after( 'service_provider' );
                }
                if ( ! Schema::hasColumn( 'plugins', 'installed_at' ) ) {
                    $table->timestamp( 'installed_at' )->nullable()->after( 'meta' );
                }
            } );
        } else {
            Schema::create( 'plugins', function ( Blueprint $table ): void {
                $table->id();
                $table->string( 'slug' )->unique();
                $table->string( 'name' );
                $table->string( 'version' );
                $table->boolean( 'is_active' )->default( false );
                $table->string( 'service_provider' )->nullable();
                $table->json( 'meta' )->nullable();
                $table->timestamp( 'installed_at' )->nullable();
                $table->timestamp( 'updated_at' )->nullable();
                $table->timestamp( 'created_at' )->nullable();

                // Indexes for performance
                $table->index( 'slug' );
                $table->index( 'is_active' );
            } );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists( 'plugins' );
    }
};

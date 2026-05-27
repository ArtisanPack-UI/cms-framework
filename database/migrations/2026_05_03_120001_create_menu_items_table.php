<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'menu_items', function ( Blueprint $table ): void {
            $table->id();
            $table->foreignId( 'menu_id' )->constrained( 'menus' )->cascadeOnDelete();
            $table->foreignId( 'parent_id' )->nullable()->constrained( 'menu_items' )->cascadeOnDelete();
            $table->unsignedInteger( 'position' )->default( 0 );
            $table->string( 'type' )->default( 'link' );
            $table->string( 'label' );
            $table->string( 'url' )->nullable();
            $table->string( 'target' )->default( '_self' );
            $table->string( 'rel' )->nullable();
            $table->string( 'classes' )->nullable();
            $table->text( 'description' )->nullable();
            $table->string( 'object_type' )->nullable();
            $table->unsignedBigInteger( 'object_id' )->nullable();
            $table->string( 'kind' )->nullable();
            $table->timestamps();

            $table->index( ['menu_id', 'parent_id', 'position'] );
            $table->index( ['object_type', 'object_id'] );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'menu_items' );
    }
};

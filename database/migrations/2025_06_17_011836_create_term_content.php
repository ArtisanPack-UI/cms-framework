<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create( 'term_content', function ( Blueprint $table ) {
            $table->foreignId( 'term_id' )->constrained( 'terms' )->onDelete( 'cascade' );
            $table->foreignId( 'content_id' )->constrained( 'content' )->onDelete( 'cascade' );
            $table->timestamps();

            $table->primary( [ 'term_id', 'content_id' ] );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'term_content' );
    }
};

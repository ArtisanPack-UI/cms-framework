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
        Schema::create( 'post_comments', function ( Blueprint $table ): void {
            $table->id();
            $table->foreignId( 'post_id' )->constrained( 'posts' )->onDelete( 'cascade' );
            $table->foreignId( 'parent_id' )->nullable()
                ->constrained( 'post_comments' )->onDelete( 'cascade' );
            $table->foreignId( 'user_id' )->nullable()
                ->constrained( 'users' )->onDelete( 'set null' );
            $table->string( 'author_name' )->nullable();
            $table->string( 'author_email' )->nullable();
            $table->string( 'author_url' )->nullable();
            $table->text( 'content' );
            $table->string( 'status' )->default( 'pending' );
            $table->timestamp( 'approved_at' )->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index( 'post_id' );
            $table->index( 'parent_id' );
            $table->index( 'status' );
            $table->index( [ 'post_id', 'status' ] );
            $table->index( 'created_at' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'post_comments' );
    }
};

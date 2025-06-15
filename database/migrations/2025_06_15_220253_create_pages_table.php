<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create( 'pages', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'user_id' ); // Add this line
			$table->string( 'title' );
			$table->string( 'slug' )->unique();
			$table->longText( 'content' )->nullable();
			$table->string( 'status' )->default( 'draft' );
			$table->unsignedBigInteger( 'parent_id' )->nullable();
			$table->integer( 'order' )->default( 0 );
			$table->timestamp( 'published_at' )->nullable();
			$table->timestamps();

			$table->foreign( 'parent_id' )
				  ->references( 'id' )
				  ->on( 'pages' )
				  ->onDelete( 'set null' );

			// Add foreign key constraint for user_id
			$table->foreign( 'user_id' )
				  ->references( 'id' )
				  ->on( 'users' )
				  ->onDelete( 'cascade' );
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'pages' );
	}
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create( 'media', function ( Blueprint $table ) {
			$table->id();
			$table->foreignId( 'user_id' )->constrained()->onDelete( 'cascade' )->comment( 'The user who uploaded this media.' );
			$table->string( 'file_name' );
			$table->string( 'mime_type' );
			$table->string( 'path' )->unique();
			$table->unsignedBigInteger( 'size' );
			$table->string( 'alt_text' )->nullable()->comment( 'Alternative text for accessibility.' );
			$table->boolean( 'is_decorative' )->default( false )->comment( 'True if the image is purely decorative and needs empty alt text.' );
			$table->json( 'metadata' )->nullable();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'media' );
	}
};

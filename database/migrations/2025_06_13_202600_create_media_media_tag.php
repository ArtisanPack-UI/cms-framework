<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create( 'media_media_tag', function ( Blueprint $table ) {
			$table->foreignId( 'media_id' )->constrained()->onDelete( 'cascade' );
			$table->foreignId( 'tag_id' )->references( 'id' )->on( 'media_tags' )->onDelete( 'cascade' );
			$table->primary( [ 'media_id', 'tag_id' ] );
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'media_media_tag' );
	}
};

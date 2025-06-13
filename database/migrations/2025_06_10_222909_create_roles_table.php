<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create( 'roles', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'name' )->unique();
			$table->string( 'slug' )->unique(); // New: Slug column
			$table->text( 'description' )->nullable(); // Adjusted: Text type for description
			$table->json( 'capabilities' )->nullable(); // Capabilities column, using json for flexibility
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'roles' );
	}
};

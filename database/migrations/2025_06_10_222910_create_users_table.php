<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create( 'users', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'username' )->unique();
			$table->string( 'email' )->unique();
			$table->timestamp( 'email_verified_at' )->nullable();
			$table->string( 'password' );
			$table->foreignId( 'role_id' )->nullable()->constrained( 'roles' )->onDelete( 'set null' );
			$table->string( 'first_name' )->nullable();
			$table->string( 'last_name' )->nullable();
			$table->string( 'website' )->nullable();
			$table->text( 'bio' )->nullable();
			$table->json( 'links' )->nullable();
			$table->json( 'settings' )->nullable();
			$table->string( 'two_factor_code' )->nullable();
			$table->timestamp( 'two_factor_expires_at' )->nullable();
			$table->timestamp( 'two_factor_enabled_at' )->nullable();
			$table->rememberToken();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'users' );
	}
};

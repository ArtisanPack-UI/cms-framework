<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	private static bool $tableCreatedByThisMigration = false;

	public function up(): void
	{
		// Only create if it doesn't exist (consuming application may already have users table)
		if ( ! Schema::hasTable( 'users' ) ) {
			Schema::create( 'users', function ( Blueprint $table ): void {
				$table->id();
				$table->string( 'name' );
				$table->string( 'email' )->unique();
				$table->timestamp( 'email_verified_at' )->nullable();
				$table->string( 'password' );
				$table->rememberToken();
				$table->timestamps();
			} );
			self::$tableCreatedByThisMigration = true;
		}
	}

	public function down(): void
	{
		if ( self::$tableCreatedByThisMigration ) {
			Schema::dropIfExists( 'users' );
		}
	}
};

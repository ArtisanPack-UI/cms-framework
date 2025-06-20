<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		// Check if the users table already exists
		if (!Schema::hasTable('users')) {
			Schema::create( 'users', function ( Blueprint $table ) {
				$table->id();
				$table->string( 'username' )->unique();
				$table->string( 'email' )->unique();
				$table->timestamp( 'email_verified_at' )->nullable();
				$table->string( 'password' );
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

			// Add role_id after the table is created to ensure roles table exists
			if (Schema::hasTable('roles')) {
				Schema::table('users', function (Blueprint $table) {
					$table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');
				});
			}
		} else {
			// If the table exists, add any missing columns
			Schema::table('users', function (Blueprint $table) {
				if (!Schema::hasColumn('users', 'username')) {
					$table->string('username')->unique();
				}
				if (!Schema::hasColumn('users', 'first_name')) {
					$table->string('first_name')->nullable();
				}
				if (!Schema::hasColumn('users', 'last_name')) {
					$table->string('last_name')->nullable();
				}
				if (!Schema::hasColumn('users', 'website')) {
					$table->string('website')->nullable();
				}
				if (!Schema::hasColumn('users', 'bio')) {
					$table->text('bio')->nullable();
				}
				if (!Schema::hasColumn('users', 'links')) {
					$table->json('links')->nullable();
				}
				if (!Schema::hasColumn('users', 'settings')) {
					$table->json('settings')->nullable();
				}
				if (!Schema::hasColumn('users', 'two_factor_code')) {
					$table->string('two_factor_code')->nullable();
				}
				if (!Schema::hasColumn('users', 'two_factor_expires_at')) {
					$table->timestamp('two_factor_expires_at')->nullable();
				}
				if (!Schema::hasColumn('users', 'two_factor_enabled_at')) {
					$table->timestamp('two_factor_enabled_at')->nullable();
				}
			});

			// Add role_id separately to ensure roles table exists
			if (!Schema::hasColumn('users', 'role_id') && Schema::hasTable('roles')) {
				Schema::table('users', function (Blueprint $table) {
					$table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');
				});
			}
		}
	}

	public function down(): void
	{
		Schema::dropIfExists( 'users' );
	}
};

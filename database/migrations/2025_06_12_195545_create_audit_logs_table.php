<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create( 'audit_logs', function ( Blueprint $table ) {
			$table->id();
			$table->foreignId( 'user_id' )->nullable()->index()->constrained()->onDelete( 'set null' );
			$table->string( 'action' )->index();
			$table->text( 'message' )->nullable();
			$table->ipAddress( 'ip_address' )->nullable();
			$table->string( 'user_agent', 512 )->nullable(); // User agent strings can be long.
			$table->string( 'status', 50 )->default( 'info' );
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'audit_logs' );
	}
};

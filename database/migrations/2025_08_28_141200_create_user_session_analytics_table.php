<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('user_session_analytics', function (Blueprint $table) {
            $table->id();
            
            // Session identification (privacy-compliant)
            $table->string('session_hash', 64)->unique()->comment('Hashed session identifier');
            
            // Privacy-compliant tracking data (all hashed for anonymization)
            $table->string('ip_address_hash', 64)->nullable()->comment('Hashed IP address for privacy');
            $table->string('user_agent_hash', 64)->nullable()->comment('Hashed user agent for privacy');
            
            // User and location data
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('User associated with session (if authenticated)');
            $table->char('country_code', 2)->nullable()->comment('Country code from IP geolocation');
            
            // Device and browser information
            $table->string('device_type', 20)->nullable()->comment('Device type (mobile, tablet, desktop)');
            $table->string('browser_family', 50)->nullable()->comment('Browser family (Chrome, Firefox, Safari, etc.)');
            $table->string('os_family', 50)->nullable()->comment('Operating system family');
            
            // Session navigation data
            $table->string('landing_page', 500)->nullable()->comment('First page visited in session');
            $table->string('exit_page', 500)->nullable()->comment('Last page visited in session');
            
            // Session metrics
            $table->integer('page_views')->unsigned()->default(0)->comment('Total page views in session');
            $table->integer('duration_seconds')->unsigned()->nullable()->comment('Session duration in seconds');
            
            // Session behavior flags
            $table->boolean('is_bounce')->default(false)->comment('Whether session was a bounce (single page view)');
            $table->boolean('is_bot')->default(false)->comment('Whether the session appears to be from a bot');
            
            // Session timestamps
            $table->timestamp('session_started_at')->comment('When the session started');
            $table->timestamp('session_ended_at')->nullable()->comment('When the session ended');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['session_hash'], 'user_session_analytics_hash_index');
            $table->index(['user_id', 'session_started_at'], 'user_session_analytics_user_time_index');
            $table->index(['country_code', 'session_started_at'], 'user_session_analytics_country_time_index');
            $table->index(['device_type', 'session_started_at'], 'user_session_analytics_device_time_index');
            $table->index(['is_bot', 'session_started_at'], 'user_session_analytics_bot_time_index');
            $table->index(['is_bounce', 'session_started_at'], 'user_session_analytics_bounce_time_index');
            $table->index(['duration_seconds', 'session_started_at'], 'user_session_analytics_duration_time_index');
            $table->index('session_started_at', 'user_session_analytics_started_time_index');
            $table->index('session_ended_at', 'user_session_analytics_ended_time_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('user_session_analytics');
    }
};
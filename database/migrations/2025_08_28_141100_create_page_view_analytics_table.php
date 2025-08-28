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
        Schema::create('page_view_analytics', function (Blueprint $table) {
            $table->id();
            
            // URL and path information
            $table->text('url')->comment('The requested URL');
            $table->string('path', 500)->comment('The URL path without domain');
            
            // Privacy-compliant tracking data (all hashed for anonymization)
            $table->string('referrer_hash', 64)->nullable()->comment('Hashed referrer URL for privacy');
            $table->string('session_hash', 64)->nullable()->comment('Hashed session identifier');
            $table->string('ip_address_hash', 64)->nullable()->comment('Hashed IP address for privacy');
            $table->string('user_agent_hash', 64)->nullable()->comment('Hashed user agent for privacy');
            
            // User and location data
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('User who viewed the page (if authenticated)');
            $table->char('country_code', 2)->nullable()->comment('Country code from IP geolocation');
            
            // Device and browser information
            $table->string('device_type', 20)->nullable()->comment('Device type (mobile, tablet, desktop)');
            $table->string('browser_family', 50)->nullable()->comment('Browser family (Chrome, Firefox, Safari, etc.)');
            $table->string('os_family', 50)->nullable()->comment('Operating system family');
            
            // Performance metrics
            $table->integer('response_time_ms')->unsigned()->nullable()->comment('Page response time in milliseconds');
            $table->integer('page_load_time_ms')->unsigned()->nullable()->comment('Client-side page load time');
            
            // Bot detection
            $table->boolean('is_bot')->default(false)->comment('Whether the request appears to be from a bot');
            
            // Timestamps
            $table->timestamp('viewed_at')->comment('When the page was viewed');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['path', 'viewed_at'], 'page_view_analytics_path_time_index');
            $table->index(['user_id', 'viewed_at'], 'page_view_analytics_user_time_index');
            $table->index(['session_hash', 'viewed_at'], 'page_view_analytics_session_time_index');
            $table->index(['country_code', 'viewed_at'], 'page_view_analytics_country_time_index');
            $table->index(['device_type', 'viewed_at'], 'page_view_analytics_device_time_index');
            $table->index(['is_bot', 'viewed_at'], 'page_view_analytics_bot_time_index');
            $table->index('viewed_at', 'page_view_analytics_time_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('page_view_analytics');
    }
};
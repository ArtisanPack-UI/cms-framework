<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The tables to rename, keyed by their legacy name and mapped to the
     * new `cms_`-prefixed name.
     *
     * @var array<string, string>
     */
    private array $tables = [
        'notifications'            => 'cms_notifications',
        'notification_user'        => 'cms_notification_user',
        'notification_preferences' => 'cms_notification_preferences',
    ];

    /**
     * Run the migrations.
     *
     * Existing installs created these tables under their legacy names before
     * the collision with Laravel's own `notifications` table was resolved.
     * Rename them in place so their data survives the upgrade. Fresh installs
     * already create the `cms_`-prefixed tables, so the guards skip every rename.
     */
    public function up(): void
    {
        foreach ( $this->tables as $from => $to ) {
            if ( Schema::hasTable( $from ) && ! Schema::hasTable( $to ) ) {
                Schema::rename( $from, $to );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ( $this->tables as $from => $to ) {
            if ( Schema::hasTable( $to ) && ! Schema::hasTable( $from ) ) {
                Schema::rename( $to, $from );
            }
        }
    }
};

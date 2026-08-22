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
     *
     * Note: on MySQL/MariaDB a table rename does NOT rename the foreign-key
     * constraint identifiers, so an upgraded install keeps the legacy
     * `notification_user_notification_id_foreign` name on the renamed pivot.
     * That is harmless today, but a future migration that drops the constraint
     * by its conventional (`cms_`-prefixed) name would fail on upgraded installs
     * only. Such a migration must resolve the constraint name from the schema
     * rather than assume the Laravel default.
     */
    public function up(): void
    {
        foreach ( $this->tables as $from => $to ) {
            if ( ! Schema::hasTable( $from ) || Schema::hasTable( $to ) ) {
                continue;
            }

            // `notifications` is also the name Laravel's database notification
            // channel uses. Only rename it when it carries the CMS shape.
            if ( 'notifications' === $from
                && ( ! Schema::hasColumn( $from, 'send_email' ) || Schema::hasColumn( $from, 'notifiable_type' ) ) ) {
                continue;
            }

            Schema::rename( $from, $to );
        }
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op: this is a one-way compatibility bridge. `up()` is
     * conditional — on a fresh install it renames nothing, because the `cms_`
     * tables already exist — so `down()` cannot know whether a rename happened
     * without state that does not survive between the separate `migrate` and
     * `migrate:rollback` invocations. Renaming the `cms_` tables back to the
     * legacy names here would strand them on a fresh-install rollback, where the
     * create migrations only drop the `cms_`-prefixed tables. Leaving the tables
     * under their `cms_` names is safe in every rollback path: the create
     * migrations' own `down()` drops them by that name.
     */
    public function down(): void
    {
        // No-op. See the method docblock for why this migration is irreversible.
    }
};

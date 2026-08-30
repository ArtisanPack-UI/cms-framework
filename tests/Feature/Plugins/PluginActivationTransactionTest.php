<?php

/**
 * Guards the fix for #333: plugin migrations must run OUTSIDE the activation
 * transaction.
 *
 * The bug it prevents is MySQL/MariaDB-only — a plugin's `CREATE TABLE`
 * implicitly commits the open transaction, so the transaction's own `commit()`
 * then throws `There is no active transaction`. The suite runs on SQLite, which
 * has transactional DDL and never trips it, so rather than reproduce the
 * failure this asserts the structural property that prevents it: migrations run
 * at the pre-activation transaction nesting level, while the genuinely
 * transactional writes (permission seeding, the `is_active` flip) run one level
 * deeper.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.10.1
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\DB;

it( 'runs plugin migrations outside the activation transaction', function (): void {
    Plugin::create( [
        'slug'      => 'txn-probe',
        'name'      => 'Txn Probe',
        'version'   => '1.0.0',
        'is_active' => false,
        // A migrations_path drives runMigrations(); no service_provider, so the
        // in-transaction container registration branch is skipped.
        'meta'      => [
            'slug'            => 'txn-probe',
            'name'            => 'Txn Probe',
            'version'         => '1.0.0',
            'migrations_path' => 'database/migrations',
        ],
    ] );

    // Probe subclass records the transaction nesting level at which migrations
    // and permission seeding actually run.
    $manager = new class extends PluginManager {
        public ?int $migrationLevel = null;

        public ?int $seedLevel = null;

        protected function runMigrations( string $slug, string $migrationsPath ): void
        {
            $this->migrationLevel = DB::transactionLevel();
        }

        protected function seedPermissions( Plugin $plugin ): void
        {
            $this->seedLevel = DB::transactionLevel();
        }
    };

    // Relative to the suite's own RefreshDatabase transaction, so the assertion
    // holds whatever level the harness starts at.
    $baseline = DB::transactionLevel();

    $result = $manager->activate( 'txn-probe' );

    expect( $result )->toBeTrue()
        // Migrations: at the baseline level — NOT inside activate()'s own
        // transaction (their DDL would auto-commit it on MySQL/MariaDB).
        ->and( $manager->migrationLevel )->toBe( $baseline )
        // Permission seed: one level deeper, inside activate()'s transaction.
        ->and( $manager->seedLevel )->toBe( $baseline + 1 )
        ->and( Plugin::where( 'slug', 'txn-probe' )->value( 'is_active' ) )->toBeTruthy();
} );

<?php

declare( strict_types=1 );

/**
 * Core Service Provider.
 *
 * Registers core services used across the CMS Framework such as the AssetManager.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Providers;

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\AssetManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\CheckForUpdateCommand;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\CheckForUpdateScheduled;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\PerformUpdateCommand;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\RollbackUpdateCommand;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\UpdateStatusCommand;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateCapability;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers core services with the application container.
 *
 * @since 1.0.0
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register the core singleton services.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton( AssetManager::class, function ( $app ) {
            return new AssetManager;
        } );

        // Bound as a singleton so the shutdown guard is registered once.
        // Every resolution of this manager that enters maintenance mode calls
        // `register_shutdown_function`, and each closure captures `$this`
        // permanently — under Octane or a long-lived queue worker, resolving
        // it per request leaked an instance and stacked N no-op handlers to
        // run at shutdown.
        $this->app->singleton( ApplicationUpdateManager::class, function ( $app ) {
            return new ApplicationUpdateManager;
        } );

        // Merge update configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../Updates/config/updates.php',
            'cms.updates',
        );
    }

    /**
     * Bootstrap the core services.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        // Publish update configuration. Also tagged `cms-framework-config` so
        // the umbrella tag the README documents publishes every module's
        // config in one command.
        $this->publishes( [
            __DIR__ . '/../Updates/config/updates.php' => config_path( 'cms/updates.php' ),
        ], [ 'cms-updates-config', 'cms-framework-config' ] );

        $this->registerUpdateCapabilities();

        // Register update commands
        if ( $this->app->runningInConsole() ) {
            $this->commands( [
                CheckForUpdateCommand::class,
                PerformUpdateCommand::class,
                RollbackUpdateCommand::class,
                UpdateStatusCommand::class,
                CheckForUpdateScheduled::class,
            ] );
        }
    }

    /**
     * Register the updates module's Gate abilities.
     *
     * The framework exposes no HTTP or Livewire trigger for updates, so
     * nothing here is consulted until a host application wires one. What it
     * buys the host is a name to authorize against — `Gate::authorize(
     * UpdateCapability::PERFORM )` — that resolves consistently whether the
     * host grants it through RBAC permissions or its own Gate definition.
     *
     * Each ability denies by default. `performUpdate()` is by design a
     * remote-code-execution channel: it overwrites PHP files and then runs
     * `composer install`, which executes `post-install-cmd` scripts from the
     * just-overwritten `composer.json`. Shipping a permissive default would
     * hand that to every authenticated user of every host that upgrades.
     *
     * Two paths grant it, neither of which this method interferes with:
     *
     * - An RBAC permission whose name or slug matches the ability. rbac's
     *   `Gate::before` hook runs ahead of every Gate definition — this one and
     *   the host's alike — and short-circuits it, so a seeded permission
     *   decides on its own. Seeding a permission *and* defining a stricter
     *   ability for the same name means the permission wins.
     * - A host `Gate::define()` for the same ability. Application providers
     *   boot after package providers, so the host's definition replaces this
     *   one. The `Gate::has()` guard covers the reverse order for hosts that
     *   register theirs early.
     *
     * @since 2.8.0
     */
    protected function registerUpdateCapabilities(): void
    {
        foreach ( UpdateCapability::all() as $ability ) {
            if ( Gate::has( $ability ) ) {
                continue;
            }

            // The unused `$user` parameter is load-bearing: Gate reflects on
            // the first parameter to decide whether an ability may be called
            // for a guest, and an untyped one denies them.
            Gate::define( $ability, fn ( $user ): bool => false );
        }
    }
}

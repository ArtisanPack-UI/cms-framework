<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates;

/**
 * Update Capability
 *
 * The Gate abilities a host application authorizes against before exposing the
 * self-updater over HTTP or Livewire.
 *
 * The framework ships no HTTP or Livewire trigger for updates — every update
 * command is console-gated — but `ApplicationUpdateManager` is written for the
 * HTTP case (`raiseExecutionLimits()` and `ignore_user_abort()` exist to
 * support it), and a host wiring the admin UI the module anticipates needs
 * something to check. These constants are that something.
 *
 * `ApplicationUpdateManager` performs no authorization of its own — it cannot,
 * because the console commands run with no authenticated user. Authorization
 * is the caller's job:
 *
 * ```php
 * Gate::authorize( UpdateCapability::PERFORM );
 *
 * app( ApplicationUpdateManager::class )->performUpdate();
 * ```
 *
 * Each ability is registered by `CoreServiceProvider` and **denies by
 * default**. `performUpdate()` overwrites PHP files and then runs
 * `composer install`, which executes `post-install-cmd` scripts from the
 * just-overwritten `composer.json` — an under-authorized trigger is total
 * compromise, so the shipped default is the safe one. Grant it either by
 * seeding an RBAC permission whose slug matches the constant (see
 * `PermissionsTableSeeder`), or by defining your own ability in the host
 * application:
 *
 * ```php
 * // AppServiceProvider::boot() — runs after the framework's registration,
 * // so this definition wins.
 * Gate::define( UpdateCapability::PERFORM, fn ( $user ) => $user->hasRole( 'owner' ) );
 * ```
 *
 * @since 2.8.0
 */
final class UpdateCapability
{
    /**
     * Run the ten-step update: download, extract, `composer install`, migrate.
     *
     * @since 2.8.0
     */
    public const PERFORM = 'cms.updates.perform';

    /**
     * Restore a pre-update snapshot. Privileged for the same reason `PERFORM`
     * is: rollback restores `composer.json` and `composer.lock` from the
     * archive and then runs `composer install` against them.
     *
     * @since 2.8.0
     */
    public const ROLLBACK = 'cms.updates.rollback';

    /**
     * Read update status — whether a release is available, and how far the
     * most recent run got. Read-only, but it discloses the installed version
     * and the update source, so it is gated too.
     *
     * @since 2.8.0
     */
    public const VIEW = 'cms.updates.view';

    /**
     * Every ability shipped by the updates module.
     *
     * @since 2.8.0
     *
     * @return array<int, string> The ability names.
     */
    public static function all(): array
    {
        return [
            self::PERFORM,
            self::ROLLBACK,
            self::VIEW,
        ];
    }
}

<?php

declare( strict_types=1 );

/**
 * SeedsPermissionsOnce trait for service providers.
 *
 * Registers a set of permission rows once and remembers that with a cache
 * flag, so a warm request costs a single cache read instead of a
 * `Schema::hasTable` probe plus one `firstOrCreate` per slug.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.15.4
 */

namespace ArtisanPackUI\CMSFramework\Support;

use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Seeds permission rows once, guarded by a cache flag.
 *
 * The flag key is versioned by the slug list and the default connection's
 * database, so a new slug is seeded automatically and sites sharing one
 * cache store don't read each other's flag. The flag is only written once
 * the rows exist, and every cache call is wrapped so an unavailable store
 * (a database cache store before its table exists) falls back to the
 * uncached seed.
 *
 * Callers should clear the flag and re-seed on `MigrationsEnded`, since
 * `migrate:fresh` / `migrate:reset` wipe the permissions table but not the
 * cache.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.15.4
 */
trait SeedsPermissionsOnce
{
    /**
     * Registers the given permissions unless the cache flag says they're
     * already seeded.
     *
     * @since 2.15.4
     *
     * @param  string                $cacheKeyPrefix  Prefix for the cache flag key.
     * @param  array<string, string> $permissions     Permission names keyed by slug.
     */
    protected function seedPermissionsOnce( string $cacheKeyPrefix, array $permissions ): void
    {
        $flagKey = $this->seededPermissionsFlagKey( $cacheKeyPrefix, $permissions );

        try {
            if ( Cache::has( $flagKey ) ) {
                return;
            }
        } catch ( Throwable ) {
            // A database cache store has no table yet during a fresh
            // install; fall through to the uncached seed.
        }

        if ( ! Schema::hasTable( 'permissions' ) ) {
            return;
        }

        /** @var PermissionManager $manager */
        $manager = $this->app->make( PermissionManager::class );

        foreach ( $permissions as $slug => $name ) {
            $manager->register( $slug, $name );
        }

        try {
            Cache::forever( $flagKey, true );
        } catch ( Throwable ) {
            // Unwritable cache: the seed simply runs again next boot.
        }
    }

    /**
     * Clears the cache flag for the given permissions.
     *
     * @since 2.15.4
     *
     * @param  string                $cacheKeyPrefix  Prefix for the cache flag key.
     * @param  array<string, string> $permissions     Permission names keyed by slug.
     */
    protected function forgetSeededPermissionsFlag( string $cacheKeyPrefix, array $permissions ): void
    {
        try {
            Cache::forget( $this->seededPermissionsFlagKey( $cacheKeyPrefix, $permissions ) );
        } catch ( Throwable ) {
            // `migrate:reset` can drop a database cache store's table.
        }
    }

    /**
     * Builds the cache flag key, versioned by the slug list and the default
     * connection's database.
     *
     * Only the slugs are hashed, so a translated permission name doesn't
     * change the key from one locale to the next.
     *
     * @since 2.15.4
     *
     * @param  string                $cacheKeyPrefix  Prefix for the cache flag key.
     * @param  array<string, string> $permissions     Permission names keyed by slug.
     *
     * @return string The cache key.
     */
    protected function seededPermissionsFlagKey( string $cacheKeyPrefix, array $permissions ): string
    {
        $connection = config( 'database.default' );

        return $cacheKeyPrefix . '.' . md5( (string) json_encode( [
            $connection,
            config( 'database.connections.' . $connection . '.database' ),
            array_keys( $permissions ),
        ] ) );
    }
}

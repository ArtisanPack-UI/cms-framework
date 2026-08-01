<?php

declare( strict_types=1 );

/**
 * Custom Field Column Cache
 *
 * Process-lifetime cache of the real column listing for content-type tables.
 *
 * @since 2.7.1
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Support;

/**
 * Shared, table-keyed cache of real DB column names.
 *
 * `HasCustomFields` needs to know whether a payload key names a real column
 * so it can refuse to let an untrusted value shadow one. Introspecting the
 * schema on every attribute read is far too expensive, so the listing is
 * cached for the life of the process.
 *
 * The cache deliberately lives here rather than in a static property on the
 * trait: a trait's statics are duplicated into every using class, so nothing
 * outside the model could flush them. `CustomFieldManager` mutates these
 * tables and must be able to invalidate by table name.
 *
 * Names are stored lower-cased. MySQL and SQLite resolve identifiers
 * case-insensitively, so a case-sensitive comparison would report `AUTHOR_ID`
 * as "not a real column" while the database happily writes it.
 *
 * @since 2.7.1
 */
final class CustomFieldColumnCache
{
    /**
     * Lower-cased column names keyed by table name.
     *
     * @var array<string,array<int,string>>
     */
    private static array $columns = [];

    /**
     * Get the cached lower-cased column listing for a table, or null when the
     * table has not been introspected yet.
     *
     * @since 2.7.1
     *
     * @param  string  $table  Table name.
     *
     * @return array<int,string>|null Lower-cased column names, or null when uncached.
     */
    public static function get( string $table ): ?array
    {
        return self::$columns[ $table ] ?? null;
    }

    /**
     * Cache the column listing for a table. Names are lower-cased on the way
     * in so callers may compare with `strtolower()`.
     *
     * @since 2.7.1
     *
     * @param  string  $table  Table name.
     * @param  array<int,string>  $columns  Column names.
     */
    public static function put( string $table, array $columns ): void
    {
        self::$columns[ $table ] = array_values( array_map( 'strtolower', $columns ) );
    }

    /**
     * Forget the cached listing for a table, or every listing when no table
     * is given.
     *
     * @since 2.7.1
     *
     * @param  string|null  $table  Table name, or null to flush everything.
     */
    public static function flush( ?string $table = null ): void
    {
        if ( null === $table ) {
            self::$columns = [];

            return;
        }

        unset( self::$columns[ $table ] );
    }
}

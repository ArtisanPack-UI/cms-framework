<?php

/**
 * Parsed Theme File Cache
 *
 * Memoises the parsed form of theme files (template/part block trees, pattern
 * headers and blocks) so the site editor does not re-parse every theme file on
 * every request (#424). Lookups go to a per-request memo first, then to the
 * cache store.
 *
 * Entries are keyed on a hash of the file's contents rather than its path and
 * `filemtime`: reading a theme file is cheap next to parsing it, and a content
 * key cannot go stale when a deploy preserves mtimes or two writes land in the
 * same second. Callers add a signature for anything else the parse depends on
 * (for theme templates, the block hydrator that produced the tree).
 *
 * The store layer can be switched off with `cms.themes.parsedFileCacheEnabled`
 * — useful while developing blocks, since a block type's attribute sources
 * shape the hydrated tree without changing the theme file. The request memo
 * always applies.
 *
 * @since      2.15.6
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * @since 2.15.6
 */
final class ParsedThemeFileCache
{
    /**
     * Cache-entry schema version. Part of every key, so bumping it on a change
     * to a parser's output shape invalidates every stored entry on deploy.
     *
     * @since 2.15.6
     */
    public const SCHEMA_VERSION = 1;

    /**
     * Default store TTL in seconds, used when `cms.themes.parsedFileCacheTtl`
     * is unset or not a positive integer.
     *
     * @since 2.15.6
     */
    public const DEFAULT_TTL = 3600;

    /**
     * Parsed values already seen during this request, keyed by cache key.
     *
     * @since 2.15.6
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * Set once the cache store has failed during this request, so later
     * lookups skip it instead of failing (or waiting out a connection
     * timeout) once per file.
     *
     * @since 2.15.6
     */
    private bool $storeUnavailable = false;

    /**
     * Return the cached parse for a theme file's contents, running the parser
     * and storing its result on a miss.
     *
     * The parser receives a `$store` flag by reference, initialised to true;
     * setting it to false keeps a degraded result (a hydrator that threw, say)
     * out of the cache store so a later request retries the full parse. The
     * result is still memoised for the rest of this request.
     *
     * @since 2.15.6
     *
     * @param  string                       $kind       Short label for what is parsed (`blocks`, `pattern`); separates keyspaces.
     * @param  string                       $contents   The raw file contents.
     * @param  string                       $signature  Anything besides the contents the parse depends on.
     * @param  callable(bool &$store): array<mixed>  $parse  Parses `$contents`.
     *
     * @return array<mixed> The parsed value.
     */
    public function remember( string $kind, string $contents, string $signature, callable $parse ): array
    {
        $key = $this->key( $kind, $contents, $signature );

        if ( array_key_exists( $key, $this->memo ) ) {
            return $this->memo[ $key ];
        }

        if ( $this->storeEnabled() ) {
            $cached = $this->readStore( $key );

            if ( is_array( $cached ) ) {
                return $this->memo[ $key ] = $cached;
            }
        }

        $store  = true;
        $parsed = $parse( $store );

        if ( $store && $this->storeEnabled() ) {
            $this->writeStore( $key, $parsed );
        }

        return $this->memo[ $key ] = $parsed;
    }

    /**
     * Read an entry from the cache store. An unavailable store (a database
     * store before its table exists, an unreachable Redis) is a miss, and is
     * skipped for the rest of the request: the parse can always be redone,
     * so it must never take the site editor down.
     *
     * @since 2.15.6
     */
    private function readStore( string $key ): mixed
    {
        try {
            return Cache::get( $key );
        } catch ( Throwable ) {
            $this->storeUnavailable = true;

            return null;
        }
    }

    /**
     * Write an entry to the cache store. A failure marks the store
     * unavailable for the rest of the request.
     *
     * @since 2.15.6
     *
     * @param  array<mixed>  $parsed  The parsed value.
     */
    private function writeStore( string $key, array $parsed ): void
    {
        try {
            Cache::put( $key, $parsed, $this->ttl() );
        } catch ( Throwable ) {
            $this->storeUnavailable = true;
        }
    }

    /**
     * Compose the cache key for a parse.
     *
     * @since 2.15.6
     */
    private function key( string $kind, string $contents, string $signature ): string
    {
        return 'cms.themes.parsed.' . self::SCHEMA_VERSION . '.' . $kind . '.' . hash( 'sha256', $signature . "\0" . $contents );
    }

    /**
     * Whether parsed results are written to and read from the cache store.
     *
     * @since 2.15.6
     */
    private function storeEnabled(): bool
    {
        return ! $this->storeUnavailable && (bool) config( 'cms.themes.parsedFileCacheEnabled', true );
    }

    /**
     * Store TTL in seconds.
     *
     * @since 2.15.6
     */
    private function ttl(): int
    {
        $ttl = (int) config( 'cms.themes.parsedFileCacheTtl', self::DEFAULT_TTL );

        return $ttl > 0 ? $ttl : self::DEFAULT_TTL;
    }
}

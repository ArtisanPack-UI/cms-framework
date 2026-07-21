<?php

declare( strict_types=1 );

/**
 * HasContentFilters Trait
 *
 * Provides shared content query filter logic for content managers.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\Concerns;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for applying common content filters to query builders.
 *
 * Provides shared status filtering and search filtering logic
 * that is used by BlogManager, PageManager, and other content managers.
 *
 * @since 1.1.0
 */
trait HasContentFilters
{
    /**
     * Apply status filter to a query.
     *
     * If a status is provided, it filters by that status. If the status is
     * 'published' or null/invalid, it uses the published scope. Otherwise,
     * it defaults to published when no status filter is provided.
     *
     * @since 1.1.0
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array  $filters  Array of filters (expects 'status' key).
     * @param  bool  $defaultToPublished  Whether to default to published scope when no status filter is provided.
     */
    protected function applyStatusFilter( Builder $query, array $filters, bool $defaultToPublished = true ): void
    {
        if ( isset( $filters['status'] ) ) {
            $status = $filters['status'] instanceof ContentStatus
                ? $filters['status']
                : ContentStatus::tryFrom( sanitizeText( $filters['status'] ) );

            if ( null === $status || ContentStatus::Published === $status ) {
                $query->published();
            } else {
                $query->where( 'status', $status );
            }
        } elseif ( $defaultToPublished ) {
            $query->published();
        }
    }

    /**
     * Apply search filter to a query.
     *
     * Searches across title, content, and excerpt fields. Runs the assembled
     * query through the `ap.cmsFramework.search.query` filter so host apps and
     * plugins can extend or narrow the default LIKE-based match — e.g. add
     * joined-column matches or restrict to a subset of records. The default
     * handler is a no-op: subscribers receive the query with the default LIKE
     * clauses already attached and mutate it in place (the builder is passed by
     * reference; the caller ignores the filter's return value, so returning a
     * fresh builder from a subscriber has no effect).
     *
     * @since 1.1.0
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array  $filters  Array of filters (expects 'search' key).
     */
    protected function applySearchFilter( Builder $query, array $filters ): void
    {
        if ( ! isset( $filters['search'] ) ) {
            return;
        }

        $term    = ( string ) $filters['search'];
        $escaped = str_replace( ['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term );
        $pattern = "%{$escaped}%";
        $query->where( function ( $q ) use ( $pattern ): void {
            $q->whereRaw( 'title LIKE ? ESCAPE ?', [$pattern, '\\'] )
                ->orWhereRaw( 'content LIKE ? ESCAPE ?', [$pattern, '\\'] )
                ->orWhereRaw( 'excerpt LIKE ? ESCAPE ?', [$pattern, '\\'] );
        } );

        $context = [
            'manager' => static::class,
            'model'   => $query->getModel()::class,
            'filters' => $filters,
        ];

        applyFilters( 'ap.cmsFramework.search.query', $query, $term, $context );
    }

    /**
     * Apply author filter to a query.
     *
     * @since 1.1.0
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array  $filters  Array of filters (expects 'author' key).
     */
    protected function applyAuthorFilter( Builder $query, array $filters ): void
    {
        if ( isset( $filters['author'] ) ) {
            $query->byAuthor( $filters['author'] );
        }
    }
}

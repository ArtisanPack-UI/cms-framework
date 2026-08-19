<?php

declare( strict_types=1 );

/**
 * QueryRuntime — resolves the V1 `core/query` attribute payload to a
 * paginated Eloquent result.
 *
 * Routes by `postType`:
 *
 *  - `post` → {@see BlogManager::getArchiveQuery()}
 *  - `page` → {@see PageManager::getPageQuery()}
 *  - any registered custom-type slug → looks up the {@see ContentType}
 *    from {@see ContentTypeManager} and queries its `model_class`.
 *
 * Used by visual-editor's `core/query` Blade/React/Vue renderers via a
 * thin REST endpoint (see G4c-2 in the visual-editor repo); a host that
 * runs the editor in-process can also call `resolve()` directly.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Services;

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;

/**
 * Resolves `core/query` block attributes to a paginated Eloquent result.
 *
 * @since 2.0.0
 */
class QueryRuntime
{
    /**
     * Default page size when the payload omits `perPage`.
     */
    protected const DEFAULT_PER_PAGE = 10;

    /**
     * Hard cap on `perPage` so a malicious or accidental
     * `?perPage=999999` cannot drag the renderer down.
     */
    protected const MAX_PER_PAGE = 100;

    /**
     * Order-by tokens this V1 implementation understands. Anything
     * else is silently coerced to the default (`date`).
     *
     * @var array<int, string>
     */
    protected const SUPPORTED_ORDER_BY = [
        'date',
        'title',
        'menu_order',
        'random',
        'relevance',
    ];

    public function __construct(
        protected BlogManager $blogManager,
        protected PageManager $pageManager,
        protected ContentTypeManager $contentTypeManager,
    ) {
    }

    /**
     * Resolve the given query attributes to a paginated result set.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attributes  Normalized `core/query` attributes.
     */
    public function resolve( array $attributes ): LengthAwarePaginator
    {
        $postType = isset( $attributes['postType'] ) && is_string( $attributes['postType'] )
            ? sanitizeText( $attributes['postType'] )
            : 'post';

        $query = $this->baseQuery( $postType, $attributes );

        $this->applyIdFilters( $query, $attributes );
        $this->applyParentFilter( $query, $postType, $attributes );
        $this->applyTaxQuery( $query, $postType, $attributes );
        $this->applyOrdering( $query, $postType, $attributes );

        return $this->paginate( $query, $attributes );
    }

    /**
     * Build the base query for the given post type.
     *
     * Status / author / search / category / tag are delegated to the
     * existing manager so the QueryRuntime stays a thin orchestration
     * layer rather than a parallel filter implementation.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function baseQuery( string $postType, array $attributes ): Builder
    {
        $managerFilters = $this->mapToManagerFilters( $attributes );

        return match ( $postType ) {
            'post'  => $this->blogManager->getArchiveQuery( $managerFilters ),
            'page'  => $this->pageManager->getPageQuery( $managerFilters ),
            default => $this->customTypeQuery( $postType, $managerFilters ),
        };
    }

    /**
     * Translate the V1 `core/query` attributes the existing managers
     * already understand into their `getXQuery( $filters )` shape.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attributes
     *
     * @return array<string, mixed>
     */
    protected function mapToManagerFilters( array $attributes ): array
    {
        $filters = [];

        if ( isset( $attributes['author'] ) ) {
            $filters['author'] = (int) $attributes['author'];
        }

        if ( isset( $attributes['search'] ) && is_string( $attributes['search'] ) ) {
            $filters['search'] = $attributes['search'];
        }

        // Default the manager filter status pass-through to a forward
        // filter; the QueryRuntime caller can override per-payload.
        if ( isset( $attributes['status'] ) ) {
            $filters['status'] = $attributes['status'];
        }

        return $filters;
    }

    /**
     * Resolve a custom content type to a base query.
     *
     * Throws when the slug does not resolve to a registered type or the
     * type's `model_class` is unloadable — visual-editor's caller
     * catches and surfaces an empty fallback state.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $managerFilters
     */
    protected function customTypeQuery( string $postType, array $managerFilters ): Builder
    {
        $contentType = $this->contentTypeManager->getContentType( $postType );

        if ( null === $contentType ) {
            throw new InvalidArgumentException( sprintf(
                'QueryRuntime: unknown post type "%s". Register the content type before querying it.',
                $postType,
            ) );
        }

        $instance = $contentType->getModelInstance();

        if ( ! $instance instanceof Model ) {
            throw new InvalidArgumentException( sprintf(
                'QueryRuntime: content type "%s" has no resolvable model class.',
                $postType,
            ) );
        }

        $query = $instance::query();

        // Best-effort filter mapping for custom types: pass the keys the
        // model is likely to understand. Models that do not expose these
        // scopes simply ignore the corresponding filter (the underlying
        // builder still works since each branch is gated on isset()).
        if ( isset( $managerFilters['author'] ) && method_exists( $instance, 'scopeByAuthor' ) ) {
            $query->byAuthor( (int) $managerFilters['author'] );
        }

        if ( isset( $managerFilters['status'] ) && in_array( 'status', $instance->getFillable(), true ) ) {
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $managerFilters['status'] is applied as an Eloquent parameter-bound where(), gated on the column being fillable.
            $query->where( 'status', $managerFilters['status'] );
        }

        if ( isset( $managerFilters['search'] ) && is_string( $managerFilters['search'] ) ) {
            $escaped = str_replace( ['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $managerFilters['search'] );
            $pattern = "%{$escaped}%";
            $query->where( function ( Builder $q ) use ( $pattern, $instance ): void {
                $columns = array_intersect( ['title', 'content', 'excerpt'], $instance->getFillable() );
                foreach ( $columns as $column ) {
                    $q->orWhereRaw( "{$column} LIKE ? ESCAPE ?", [$pattern, '\\'] );
                }
            } );
        }

        return $query;
    }

    /**
     * Apply `postIn` / `postNotIn` / `exclude` id filters.
     *
     * `exclude` is documented as an alias for `postNotIn`; both are
     * merged, deduplicated, and applied as a single `whereNotIn` so a
     * caller passing both does not double-up the constraint.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyIdFilters( Builder $query, array $attributes ): void
    {
        $postIn = $this->normalizeIdList( $attributes['postIn'] ?? null );
        if ( [] !== $postIn ) {
            $query->whereIn( 'id', $postIn );
        }

        $excluded = array_values( array_unique( array_merge(
            $this->normalizeIdList( $attributes['postNotIn'] ?? null ),
            $this->normalizeIdList( $attributes['exclude'] ?? null ),
        ) ) );

        if ( [] !== $excluded ) {
            $query->whereNotIn( 'id', $excluded );
        }
    }

    /**
     * Apply the `parents` filter — pages only. Posts have no
     * `parent_id` column so the attribute is silently ignored.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyParentFilter( Builder $query, string $postType, array $attributes ): void
    {
        if ( 'page' !== $postType ) {
            return;
        }

        $parents = $this->normalizeIdList( $attributes['parents'] ?? null );

        if ( [] !== $parents ) {
            $query->whereIn( 'parent_id', $parents );
        }
    }

    /**
     * Apply the V1 `taxQuery` subset: `{ taxonomy, terms, operator }`.
     *
     * Only the `IN` operator is implemented — `NOT IN` and `AND` are
     * deferred per the issue's "Out of scope". `taxonomy` accepts
     * `category` / `post_tag` for both posts and pages (Page exposes
     * the same `categories()` and `tags()` relations as Post).
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyTaxQuery( Builder $query, string $postType, array $attributes ): void
    {
        if ( ! isset( $attributes['taxQuery'] ) || ! is_array( $attributes['taxQuery'] ) ) {
            return;
        }

        $taxQuery = $attributes['taxQuery'];
        $taxonomy = isset( $taxQuery['taxonomy'] ) && is_string( $taxQuery['taxonomy'] )
            ? $taxQuery['taxonomy']
            : null;
        $operator = isset( $taxQuery['operator'] ) && is_string( $taxQuery['operator'] )
            ? strtoupper( $taxQuery['operator'] )
            : 'IN';
        $terms    = $this->normalizeIdList( $taxQuery['terms'] ?? null );

        if ( null === $taxonomy || [] === $terms || 'IN' !== $operator ) {
            return;
        }

        $relation = match ( $taxonomy ) {
            'category', 'post_category'  => 'categories',
            'post_tag', 'tag'            => 'tags',
            default                      => null,
        };

        if ( null === $relation ) {
            return;
        }

        // Custom content types may not declare `categories()`/`tags()`
        // relations — calling `whereHas` against a missing relation
        // throws `BadMethodCallException`. Drop the constraint silently
        // when the model can't satisfy it; the visual-editor caller
        // sees the unfiltered result and can decide how to surface it.
        if ( ! method_exists( $query->getModel(), $relation ) ) {
            return;
        }

        $query->whereHas( $relation, function ( Builder $q ) use ( $terms ): void {
            $q->whereIn( 'id', $terms );
        } );
    }

    /**
     * Apply `orderBy` / `order`. The orders are post-type aware:
     *
     *  - `date` → `published_at`
     *  - `title` → `title`
     *  - `menu_order` → `order` for pages; ignored for posts
     *  - `random` → `RANDOM()` / `RAND()` depending on driver
     *  - `relevance` → preserves the manager's default ordering when
     *    `search` is set; otherwise falls back to `date`.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyOrdering( Builder $query, string $postType, array $attributes ): void
    {
        $orderBy = isset( $attributes['orderBy'] ) && is_string( $attributes['orderBy'] )
            ? strtolower( $attributes['orderBy'] )
            : 'date';
        $order   = isset( $attributes['order'] ) && is_string( $attributes['order'] )
            && 'asc' === strtolower( $attributes['order'] )
            ? 'asc'
            : 'desc';

        if ( ! in_array( $orderBy, self::SUPPORTED_ORDER_BY, true ) ) {
            $orderBy = 'date';
        }

        $hasSearch = isset( $attributes['search'] )
            && is_string( $attributes['search'] )
            && '' !== trim( $attributes['search'] );

        // The base query already applies a default order via the
        // existing managers — strip those orders before re-applying so
        // the V1 `orderBy` payload is authoritative when present. The
        // `relevance` case with an active search term is the exception:
        // the manager has already wired its relevance ordering and
        // calling `reorder()` would discard it.
        if ( ! ( 'relevance' === $orderBy && $hasSearch ) ) {
            $query->reorder();
        }

        switch ( $orderBy ) {
            case 'title':
                $query->orderBy( 'title', $order );
                break;
            case 'menu_order':
                if ( 'page' === $postType ) {
                    $query->orderBy( 'order', $order );
                } else {
                    // Posts have no menu_order column — fall back to date.
                    $query->orderBy( 'published_at', $order );
                }
                break;
            case 'random':
                $driver     = $query->getModel()->getConnection()->getDriverName();
                $expression = 'mysql' === $driver ? 'RAND()' : 'RANDOM()';
                $query->orderByRaw( $expression );
                break;
            case 'relevance':
                if ( ! $hasSearch ) {
                    // Without a search term `relevance` has no signal — fall
                    // through to a date sort so the result is stable.
                    $query->orderBy( 'published_at', $order );
                }
                break;
            case 'date':
            default:
                $query->orderBy( 'published_at', $order );
                break;
        }
    }

    /**
     * Paginate the query, honouring `perPage`, `offset`, and `pages`.
     *
     * Without `offset` or `pages`, defer to Laravel's standard
     * `Builder::paginate()` (which runs an unbounded `count()` for
     * `total()`). With either set, fall back to a hand-rolled paginator:
     *
     *  - Run a single `count()` to get the unbounded match total.
     *  - Compute the effective total — `rawTotal - offset`, clamped to
     *    `cap` when set. This matches the WordPress semantic for
     *    `offset` ("skip these, treat the rest as the result set") and
     *    the issue's intent for `pages` ("latest N").
     *  - Fetch only the rows needed for the current page via
     *    `skip()`/`take()`. The SQL `LIMIT`/`OFFSET` keeps memory bounded
     *    regardless of how large the matching result set is.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function paginate( Builder $query, array $attributes ): LengthAwarePaginator
    {
        $perPage = isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : self::DEFAULT_PER_PAGE;
        $perPage = max( 1, min( self::MAX_PER_PAGE, $perPage ) );

        $offset = isset( $attributes['offset'] ) ? max( 0, (int) $attributes['offset'] ) : 0;
        $cap    = isset( $attributes['pages'] ) ? max( 0, (int) $attributes['pages'] ) : 0;

        if ( 0 === $offset && 0 === $cap ) {
            return $query->paginate( $perPage );
        }

        if ( $cap > 0 ) {
            $perPage = min( $perPage, $cap );
        }

        // `count()` clones the query so the subsequent skip/take below
        // start from a clean builder.
        $rawTotal       = ( clone $query )->toBase()->getCountForPagination();
        $effectiveTotal = max( 0, $rawTotal - $offset );

        if ( $cap > 0 ) {
            $effectiveTotal = min( $effectiveTotal, $cap );
        }

        $page = max( 1, Paginator::resolveCurrentPage() );
        $skip = $offset + ( $page - 1 ) * $perPage;
        $take = $perPage;

        if ( $cap > 0 ) {
            $remaining = $cap - ( $page - 1 ) * $perPage;
            $take      = max( 0, min( $perPage, $remaining ) );
        }

        $items = $take > 0
            ? $query->skip( $skip )->take( $take )->get()
            : $query->getModel()->newCollection();

        return new ConcretePaginator(
            $items,
            $effectiveTotal,
            $perPage,
            $page,
            [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * Coerce the given value to a list of unique positive integers.
     *
     * @since 2.0.0
     */
    protected function normalizeIdList( mixed $value ): array
    {
        if ( ! is_array( $value ) ) {
            return [];
        }

        $ids = array_filter(
            array_map( static fn ( mixed $id ): int => (int) $id, $value ),
            static fn ( int $id ): bool => $id > 0,
        );

        return array_values( array_unique( $ids));
    }
}

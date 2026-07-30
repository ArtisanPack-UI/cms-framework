<?php

declare( strict_types=1 );

/**
 * Page Manager
 *
 * Manages page operations including hierarchy management and page retrieval.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Managers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\Concerns\HasContentFilters;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Manages page operations.
 *
 * @since 1.0.0
 */
class PageManager
{
    use HasContentFilters;

    /**
     * Get a hierarchical page tree structure.
     *
     * @since 1.0.0
     *
     * @param  array  $filters  Optional filters (status, author, template).
     */
    public function getPageTree( array $filters = [] ): Collection
    {
        $query = Page::query()->topLevel()->with( ['children' => function ( $query ): void {
            $query->orderBy( 'order' );
        }] );

        $this->applyFilters( $query, $filters );

        $topLevel = $query->orderBy( 'order' )->get();

        // Recursively load all children
        $topLevel->each( function ( $page ): void {
            $this->loadChildrenRecursively( $page );
        } );

        return $topLevel;
    }

    /**
     * Get top-level pages (pages without a parent).
     *
     * @since 1.0.0
     *
     * @param  array  $filters  Optional filters (status, author, template).
     */
    public function getTopLevelPages( array $filters = [] ): Collection
    {
        $query = Page::query()->topLevel();

        $this->applyFilters( $query, $filters );

        return $query->orderBy( 'order' )->get();
    }

    /**
     * Get pages by template.
     *
     * @since 1.0.0
     *
     * @param  string  $template  Template name to filter by.
     * @param  array  $filters  Optional filters (status, author).
     */
    public function getPagesByTemplate( string $template, array $filters = [] ): Collection
    {
        $query = Page::query()->byTemplate( $template );

        $this->applyFilters( $query, $filters );

        return $query->orderBy( 'order' )->get();
    }

    /**
     * Reorder pages.
     *
     * @since 1.0.0
     *
     * @param  array  $order  Array of page IDs with their new order values.
     *                        Format: ['page_id' => order_value, ...]
     */
    public function reorderPages( array $order ): void
    {
        foreach ( $order as $pageId => $orderValue ) {
            Page::where( 'id', sanitizeInt( $pageId ) )->update( ['order' => $orderValue] );
        }
    }

    /**
     * Move a page to a new parent in the hierarchy.
     *
     * @since 1.0.0
     *
     * @param  int  $pageId  The ID of the page to move.
     * @param  int|null  $newParentId  The ID of the new parent page (null for top-level).
     */
    public function movePage( int $pageId, ?int $newParentId = null ): void
    {
        $page = Page::findOrFail( $pageId );

        // Prevent moving a page to itself or to its own descendant
        if ( null !== $newParentId ) {
            if ( $newParentId === $pageId ) {
                throw new InvalidArgumentException( 'Cannot move a page to itself.' );
            }

            $descendantIds = $page->descendants()->pluck( 'id' )->toArray();
            if ( in_array( $newParentId, $descendantIds ) ) {
                throw new InvalidArgumentException( 'Cannot move a page to its own descendant.' );
            }
        }

        $page->update( ['parent_id' => $newParentId] );
    }

    /**
     * Get all pages query with filters.
     *
     * @since 1.0.0
     *
     * @param  array  $filters  Array of filters to apply (status, author, template, search).
     */
    public function getPageQuery( array $filters = [] ): Builder
    {
        $query = Page::query()->with( ['author', 'categories', 'tags'] );

        $this->applyFilters( $query, $filters );

        // Order by order column and title
        $query->orderBy( 'order' )->orderBy( 'title' );

        return $query;
    }

    /**
     * Get pages by author.
     *
     * @since 1.0.0
     *
     * @param  int  $authorId  Author ID to filter by.
     */
    public function getPagesByAuthor( int $authorId ): Collection
    {
        return $this->getPageQuery( ['author' => $authorId] )->get();
    }

    /**
     * Get pages by category.
     *
     * @since 1.0.0
     *
     * @param  int|string  $category  Category ID or slug.
     */
    public function getPagesByCategory( $category ): Collection
    {
        // If category is a string (slug), find the category ID
        if ( is_string( $category ) ) {
            $categoryModel = \ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageCategory::where( 'slug', sanitizeText( $category ) )->first();
            if ( $categoryModel ) {
                $category = $categoryModel->id;
            } else {
                return collect();
            }
        }

        $query = $this->getPageQuery();
        $query->whereHas( 'categories', function ( $q ) use ( $category ): void {
            $q->where( 'page_categories.id', sanitizeInt( $category ) );
        } );

        return $query->get();
    }

    /**
     * Get pages by tag.
     *
     * @since 1.0.0
     *
     * @param  int|string  $tag  Tag ID or slug.
     */
    public function getPagesByTag( $tag ): Collection
    {
        // If tag is a string (slug), find the tag ID
        if ( is_string( $tag ) ) {
            $tagModel = \ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageTag::where( 'slug', sanitizeText( $tag ) )->first();
            if ( $tagModel ) {
                $tag = $tagModel->id;
            } else {
                return collect();
            }
        }

        $query = $this->getPageQuery();
        $query->whereHas( 'tags', function ( $q ) use ( $tag ): void {
            $q->where( 'page_tags.id', sanitizeInt( $tag ) );
        } );

        return $query->get();
    }

    /**
     * Get recent pages.
     *
     * @since 1.0.0
     *
     * @param  int  $limit  Number of pages to retrieve.
     */
    public function getRecentPages( int $limit = 10 ): Collection
    {
        return $this->getPageQuery( ['status' => ContentStatus::Published] )
            ->limit( $limit )
            ->get();
    }

    /**
     * Create a blank draft page with an allocated unique slug. Powers
     * the "New Page" auto-draft admin flow — an editor lands on the
     * edit screen against a real record instead of a synthesized
     * in-memory one, so autosave, custom-field wiring, and media
     * associations all have an id to hang off.
     *
     * @since 2.7.0
     *
     * @param  int|null  $authorId  Author id to stamp on the record.
     * @param  string  $baseSlug  Seed used for the unique-slug allocator.
     */
    public function autoDraft( ?int $authorId = null, string $baseSlug = 'untitled-page' ): Page
    {
        return Page::create( [
            'title'     => 'Untitled page',
            'slug'      => $this->uniqueSlug( $baseSlug ),
            'status'    => ContentStatus::Draft,
            'author_id' => $authorId,
            'order'     => 0,
        ] );
    }

    /**
     * Create a page from a validated attribute array. Same four
     * responsibilities as {@see \ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager::create()}
     * — missing-slug allocation, custom-field application timing,
     * `published_at` stamp on Published, and transactional write —
     * plus support for the hierarchical `parent_id`, `order`, and
     * `template` columns unique to Page.
     *
     * @since 2.7.0
     *
     * @param  array<string,mixed>  $attributes
     * @param  array<string,mixed>|null  $customFields
     */
    public function create( array $attributes, ?array $customFields = null, ?int $authorId = null ): Page
    {
        return DB::transaction( function () use ( $attributes, $customFields, $authorId ): Page {
            $attributes = $this->normalizeAttributes( $attributes, $authorId );

            $page = new Page( $this->fillableSubset( $attributes ) );
            $this->applyCustomFields( $page, $customFields );
            $page->save();

            return $page;
        } );
    }

    /**
     * Update an existing page from a validated attribute array. Same
     * transitions as create, plus the "first draft -> published stamps
     * `published_at`" rule.
     *
     * @since 2.7.0
     *
     * @param  array<string,mixed>  $attributes
     * @param  array<string,mixed>|null  $customFields
     */
    public function update( Page $page, array $attributes, ?array $customFields = null ): Page
    {
        return DB::transaction( function () use ( $page, $attributes, $customFields ): Page {
            $wasPublished = ContentStatus::Published === $page->status;

            $page->fill( $this->fillableSubset( $attributes ) );

            $nowPublished = ContentStatus::Published === $page->status;

            if ( $nowPublished && ! $wasPublished && null === $page->published_at ) {
                $page->published_at = now();
            }

            $this->applyCustomFields( $page, $customFields );
            $page->save();

            return $page;
        } );
    }

    /**
     * Soft-delete a page. Wrapper so consumers don't have to import
     * Page to route through the domain service.
     *
     * @since 2.7.0
     */
    public function delete( Page $page ): void
    {
        DB::transaction( fn () => $page->delete() );
    }

    /**
     * Duplicate an existing page. Copies fillable attributes, allocates
     * a fresh unique slug, appends "(Copy)" to the title, resets status
     * to {@see ContentStatus::Draft}. Does NOT copy `parent_id` blindly
     * — the duplicate is created at the same hierarchical level as the
     * source. `published_at` is intentionally not copied.
     *
     * @since 2.7.0
     */
    public function duplicate( Page $page, ?int $authorId = null ): Page
    {
        return DB::transaction( function () use ( $page, $authorId ): Page {
            $copy            = $page->replicate( ['published_at'] );
            $copy->title     = $page->title . ' (Copy)';
            $copy->slug      = $this->uniqueSlug( $page->slug . '-copy' );
            $copy->status    = ContentStatus::Draft;
            $copy->author_id = $authorId ?? $page->author_id;
            $copy->save();

            return $copy;
        } );
    }

    /**
     * Allocate a slug that does not collide with any existing page's
     * slug ( including soft-deleted rows ). Appends `-2`, `-3`, … to
     * the source until a free slot is found.
     *
     * @since 2.7.0
     *
     * @param  string  $source  Text to slugify. Non-slug input is
     *                          normalized via {@see Str::slug()}.
     */
    public function uniqueSlug( string $source ): string
    {
        $base = Str::slug( $source );

        if ( '' === $base ) {
            $base = 'page';
        }

        $slug = $base;
        $n    = 2;

        while ( Page::withTrashed()->where( 'slug', $slug )->exists() ) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }

    /**
     * Load children recursively for a page.
     *
     * @since 1.0.0
     *
     * @param  Page  $page  The page to load children for.
     */
    protected function loadChildrenRecursively( Page $page ): void
    {
        if ( $page->children->isNotEmpty() ) {
            $page->children->each( function ( $child ): void {
                $child->load( ['children' => function ( $query ): void {
                    $query->orderBy( 'order' );
                }] );
                $this->loadChildrenRecursively( $child );
            } );
        }
    }

    /**
     * Apply filters to a query.
     *
     * @since 1.0.0
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array  $filters  Array of filters to apply.
     */
    protected function applyFilters( Builder $query, array $filters ): void
    {
        // Apply shared content filters
        $this->applyStatusFilter( $query, $filters, false );
        $this->applyAuthorFilter( $query, $filters );
        $this->applySearchFilter( $query, $filters );

        // Apply template filter (page-specific)
        if ( isset( $filters['template'] ) ) {
            $query->byTemplate( $filters['template'] );
        }
    }

    /**
     * Apply the shared normalization every write path needs.
     *
     * @param  array<string,mixed>  $attributes
     *
     * @return array<string,mixed>
     */
    protected function normalizeAttributes( array $attributes, ?int $authorId ): array
    {
        if ( ! isset( $attributes['slug'] ) || '' === $attributes['slug'] ) {
            $attributes['slug'] = $this->uniqueSlug( ( string ) ( $attributes['title'] ?? '' ) );
        }

        if ( ! isset( $attributes['author_id'] ) && null !== $authorId ) {
            $attributes['author_id'] = $authorId;
        }

        if ( isset( $attributes['status'] ) && is_string( $attributes['status'] ) ) {
            $attributes['status'] = ContentStatus::from( $attributes['status'] );
        }

        if ( isset( $attributes['status'] )
            && ContentStatus::Published === $attributes['status']
            && ! array_key_exists( 'published_at', $attributes ) ) {
            $attributes['published_at'] = now();
        }

        return $attributes;
    }

    /**
     * Intersect a caller-supplied attribute array with the model's
     * fillable set so a manager caller can hand over a raw validated
     * payload and not worry about mass-assignment guardrails.
     *
     * @param  array<string,mixed>  $attributes
     *
     * @return array<string,mixed>
     */
    protected function fillableSubset( array $attributes ): array
    {
        return array_intersect_key( $attributes, array_flip( ( new Page )->getFillable() ) );
    }

    /**
     * Route filter-registered custom-field values through the
     * {@see \ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasCustomFields}
     * magic setter before save so a single write captures both the
     * hardcoded columns and the metadata JSON.
     *
     * @param  array<string,mixed>|null  $customFields
     */
    protected function applyCustomFields( Page $page, ?array $customFields ): void
    {
        if ( null === $customFields || [] === $customFields ) {
            return;
        }

        foreach ( $customFields as $key => $value ) {
            $page->{$key} = $value;
        }
    }
}

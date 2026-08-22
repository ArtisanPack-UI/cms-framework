<?php

declare( strict_types=1 );

/**
 * Blog Manager
 *
 * Manages blog operations including archive queries and post retrieval.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Managers;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\Concerns\HasContentFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages blog operations.
 *
 * @since 1.0.0
 */
class BlogManager
{
    use HasContentFilters;

    /**
     * Build an archive query with filters.
     *
     * @since 1.0.0
     *
     * @param  array  $filters  Array of filters to apply (category, tag, author, year, month, day, status).
     */
    public function getArchiveQuery( array $filters = [] ): Builder
    {
        $query = Post::query()->with( ['author', 'categories', 'tags'] );

        // Apply shared filters
        $this->applyStatusFilter( $query, $filters );
        $this->applyAuthorFilter( $query, $filters );
        $this->applySearchFilter( $query, $filters );

        // Apply category filter
        if ( isset( $filters['category'] ) ) {
            $query->byCategory( $filters['category'] );
        }

        // Apply tag filter
        if ( isset( $filters['tag'] ) ) {
            $query->byTag( $filters['tag'] );
        }

        // Apply date filters
        if ( isset( $filters['year'] ) ) {
            if ( isset( $filters['month'] ) ) {
                $query->byMonth( $filters['year'], $filters['month'] );
                if ( isset( $filters['day'] ) ) {
                    $query->whereDay( 'published_at', $filters['day'] );
                }
            } else {
                $query->byYear( $filters['year'] );
            }
        }

        // Order by published date descending
        $query->orderBy( 'published_at', 'desc' );

        return $query;
    }

    /**
     * Get posts by date.
     *
     * @since 1.0.0
     *
     * @param  int  $year  Year to filter by.
     * @param  int|null  $month  Month to filter by (optional).
     * @param  int|null  $day  Day to filter by (optional).
     */
    public function getPostsByDate( int $year, ?int $month = null, ?int $day = null ): Collection
    {
        $filters = ['year' => $year];

        if ( null !== $month ) {
            $filters['month'] = $month;
        }

        if ( null !== $day ) {
            $filters['day'] = $day;
        }

        return $this->getArchiveQuery( $filters )->get();
    }

    /**
     * Get posts by author.
     *
     * @since 1.0.0
     *
     * @param  int  $authorId  Author ID to filter by.
     */
    public function getPostsByAuthor( int $authorId ): Collection
    {
        return $this->getArchiveQuery( ['author' => $authorId] )->get();
    }

    /**
     * Get posts by category.
     *
     * @since 1.0.0
     *
     * @param  int|string  $category  Category ID or slug.
     */
    public function getPostsByCategory( $category ): Collection
    {
        // If category is a string (slug), find the category ID
        if ( is_string( $category ) ) {
            $categoryModel = \ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory::where( 'slug', sanitizeText( $category ) )->first();
            if ( $categoryModel ) {
                $category = $categoryModel->id;
            } else {
                return collect();
            }
        }

        return $this->getArchiveQuery( ['category' => $category] )->get();
    }

    /**
     * Get posts by tag.
     *
     * @since 1.0.0
     *
     * @param  int|string  $tag  Tag ID or slug.
     */
    public function getPostsByTag( $tag ): Collection
    {
        // If tag is a string (slug), find the tag ID
        if ( is_string( $tag ) ) {
            $tagModel = \ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag::where( 'slug', sanitizeText( $tag ) )->first();
            if ( $tagModel ) {
                $tag = $tagModel->id;
            } else {
                return collect();
            }
        }

        return $this->getArchiveQuery( ['tag' => $tag] )->get();
    }

    /**
     * Get recent posts.
     *
     * @since 1.0.0
     *
     * @param  int  $limit  Number of posts to retrieve.
     * @param  array|string  $with  Optional relationships to eager load.
     */
    public function getRecentPosts( int $limit = 10, array|string $with = [] ): Collection
    {
        $query = $this->getArchiveQuery();

        if ( ! empty( $with ) ) {
            $query->with( (array) $with );
        }

        return $query->limit( $limit )->get();
    }

    /**
     * Get popular posts based on view count in metadata.
     *
     * @since 1.0.0
     *
     * @param  int  $limit  Number of posts to retrieve.
     */
    public function getPopularPosts( int $limit = 10 ): Collection
    {
        return $this->getArchiveQuery()
            ->orderByRaw( 'CAST(JSON_EXTRACT(metadata, "$.view_count") AS UNSIGNED) DESC' )
            ->limit( $limit )
            ->get();
    }

    /**
     * Create a blank draft post with an allocated unique slug. Powers the
     * "New Post" auto-draft admin flow — an editor lands on the edit
     * screen against a real record instead of a synthesized in-memory
     * one, so autosave, custom-field wiring, and media associations all
     * have an id to hang off.
     *
     * @since 2.7.0
     *
     * @param  int|null  $authorId  Author id to stamp on the record. Null when
     *                              the caller has no authenticated user.
     * @param  string  $baseSlug  Seed used for the unique-slug allocator.
     */
    public function autoDraft( ?int $authorId = null, string $baseSlug = 'untitled-post' ): Post
    {
        $post = new Post( [
            'title'     => 'Untitled post',
            'slug'      => $this->uniqueSlug( $baseSlug ),
            'status'    => ContentStatus::Draft,
            'author_id' => $authorId,
        ] );

        $this->saveWithSlugRetry( $post );

        return $post;
    }

    /**
     * Create a post from a validated attribute array. Handles the four
     * responsibilities every consumer currently re-implements:
     *
     * 1. Missing-slug allocation via {@see uniqueSlug()} from the title.
     * 2. Custom-field application through the {@see \ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasCustomFields}
     *    magic setter before the initial insert, so a single INSERT
     *    captures both hardcoded columns and metadata JSON — filter
     *    observers fire exactly once.
     * 3. Setting `published_at = now()` when `status` is
     *    {@see ContentStatus::Published} on creation.
     * 4. Wrapping the write in a DB transaction so a mid-save filter
     *     throw leaves neither a half-populated row nor stale category
     *     pivots behind.
     *
     * Callers are responsible for validation ( slug uniqueness,
     * status enum, author_id integrity ) upstream — the manager trusts
     * the array it receives.
     *
     * @since 2.7.0
     *
     * @param  array<string,mixed>  $attributes  Fillable attributes for the
     *                                           new post ( title, slug,
     *                                           status, excerpt,
     *                                           featured_image_id, etc. ).
     * @param  array<string,mixed>|null  $customFields  Filter-registered
     *                                                  custom-field values,
     *                                                  keyed by field slug.
     * @param  int|null  $authorId  Author id to stamp when not supplied
     *                              in `$attributes`.
     */
    public function create( array $attributes, ?array $customFields = null, ?int $authorId = null ): Post
    {
        return DB::transaction( function () use ( $attributes, $customFields, $authorId ): Post {
            $attributes = $this->normalizeAttributes( $attributes, $authorId );

            $post = new Post( $this->fillableSubset( $attributes ) );
            $this->applyCustomFields( $post, $customFields );
            $this->saveWithSlugRetry( $post );

            return $post;
        } );
    }

    /**
     * Update an existing post from a validated attribute array. Same
     * four responsibilities as {@see create()}, plus the "first
     * transition to Published stamps `published_at`" rule that every
     * consumer today rediscovers independently.
     *
     * @since 2.7.0
     *
     * @param  array<string,mixed>  $attributes  Fillable attributes to fill
     *                                           onto the record.
     * @param  array<string,mixed>|null  $customFields  Filter-registered
     *                                                  custom-field values.
     */
    public function update( Post $post, array $attributes, ?array $customFields = null ): Post
    {
        return DB::transaction( function () use ( $post, $attributes, $customFields ): Post {
            // A caller passing `slug => ''` on update would otherwise
            // overwrite an existing unique slug with an empty string,
            // bypassing the allocator. Drop the key when blank so the
            // fill() below leaves the record's current slug intact.
            if ( array_key_exists( 'slug', $attributes ) && '' === trim( ( string ) $attributes['slug'] ) ) {
                unset( $attributes['slug'] );
            }

            $wasPublished = ContentStatus::Published === $post->status;

            $post->fill( $this->fillableSubset( $attributes ) );

            $nowPublished = ContentStatus::Published === $post->status;

            if ( $nowPublished && ! $wasPublished && null === $post->published_at ) {
                $post->published_at = now();
            }

            $this->applyCustomFields( $post, $customFields );
            $this->saveWithSlugRetry( $post );

            return $post;
        } );
    }

    /**
     * Soft-delete a post. Thin seal over `Post::delete()` so consumers
     * don't have to import the model to route through the domain
     * service; also gives us one place to hang cross-cutting concerns
     * later ( audit log, revisions history, cache invalidation ).
     *
     * @since 2.7.0
     */
    public function delete( Post $post ): void
    {
        DB::transaction( fn () => $post->delete() );
    }

    /**
     * Duplicate an existing post. Copies fillable attributes, allocates
     * a fresh unique slug, appends "(Copy)" to the title, resets status
     * to {@see ContentStatus::Draft}, and mirrors the source's category
     * and tag associations. `published_at` is intentionally not copied
     * — a duplicated draft has never been published.
     *
     * @since 2.7.0
     *
     * @param  int|null  $authorId  Author id to stamp on the copy. Falls
     *                              back to the source's author when null.
     */
    public function duplicate( Post $post, ?int $authorId = null ): Post
    {
        return DB::transaction( function () use ( $post, $authorId ): Post {
            $copy            = $post->replicate( ['published_at'] );
            $copy->title     = $post->title . ' (Copy)';
            $copy->slug      = $this->uniqueSlug( $post->slug . '-copy' );
            $copy->status    = ContentStatus::Draft;
            $copy->author_id = $authorId ?? $post->author_id;
            $this->saveWithSlugRetry( $copy );

            $copy->categories()->sync( $post->categories->pluck( 'id' )->all() );
            $copy->tags()->sync( $post->tags->pluck( 'id' )->all() );

            return $copy;
        } );
    }

    /**
     * Sync a post's category associations. Wrapper so consumers don't
     * have to know the pivot's shape.
     *
     * @since 2.7.0
     *
     * @param  array<int,int>  $categoryIds
     */
    public function syncCategories( Post $post, array $categoryIds ): void
    {
        // Wrapped so a mid-sync constraint violation on the attach
        // half doesn't leave the pivot with the detach half applied.
        DB::transaction( fn () => $post->categories()->sync( $categoryIds ) );
    }

    /**
     * Sync a post's tag associations. Wrapper so consumers don't have
     * to know the pivot's shape.
     *
     * @since 2.7.0
     *
     * @param  array<int,int>  $tagIds
     */
    public function syncTags( Post $post, array $tagIds ): void
    {
        DB::transaction( fn () => $post->tags()->sync( $tagIds ) );
    }

    /**
     * Allocate a slug that does not collide with any existing post's
     * slug ( including soft-deleted rows, so a restore can't
     * resurrect a duplicate ). Appends `-2`, `-3`, … to the source
     * until a free slot is found.
     *
     * @since 2.7.0
     *
     * @param  string  $source  Text to slugify. Non-slug input is normalized
     *                          via {@see Str::slug()}.
     */
    public function uniqueSlug( string $source ): string
    {
        $base = Str::slug( $source );

        if ( '' === $base ) {
            $base = 'post';
        }

        $slug = $base;
        $n    = 2;

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $slug is generated internally via Str::slug() and used in a parameter-bound where() uniqueness check.
        while ( Post::withTrashed()->where( 'slug', $slug )->exists() ) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }

    /**
     * Apply the shared normalization every write path needs — missing
     * slug allocation, author stamp fallback, status coercion from raw
     * string.
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
        $fillable = ( new Post )->getFillable();

        // `published_at` and `author_id` are guarded by default but the
        // manager legitimately writes them; keep them in the subset.
        $allowed = array_merge( $fillable, ['published_at', 'author_id', 'status'] );

        return array_intersect_key( $attributes, array_flip( $allowed ) );
    }

    /**
     * Route filter-registered custom-field values through the
     * {@see \ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasCustomFields}
     * magic setter before save so a single INSERT/UPDATE captures both
     * the hardcoded columns and the metadata JSON.
     *
     * Delegates to `applyCustomFieldValues()` rather than assigning each
     * key directly: the payload is untrusted, and that method drops keys
     * that shadow a real column before they can reach the column ( #253 ).
     *
     * @param  array<string,mixed>|null  $customFields
     */
    protected function applyCustomFields( Post $post, ?array $customFields ): void
    {
        if ( null === $customFields || [] === $customFields ) {
            return;
        }

        $post->applyCustomFieldValues( $customFields );
    }

    /**
     * Save a post, catching the tiny race where two writers each pass
     * {@see uniqueSlug()}'s pre-check with the same candidate ( two
     * simultaneous "New Post" clicks both probing `untitled-post` )
     * and then collide on the DB-level unique index. Reallocates the
     * slug once from its own value as a fresh base and retries. If the
     * retry also collides — genuine sustained contention, not a race —
     * the second exception surfaces to the caller.
     *
     * @throws QueryException on non-slug-unique errors, or on a second
     *                        slug-unique collision after the retry.
     */
    protected function saveWithSlugRetry( Post $post ): void
    {
        try {
            $post->save();
        } catch ( QueryException $e ) {
            if ( ! $this->isUniqueSlugViolation( $e ) ) {
                throw $e;
            }

            $post->slug = $this->uniqueSlug( $post->slug );
            $post->save();
        }
    }

    /**
     * Whether a QueryException reports an integrity-constraint violation
     * on the slug column. Uses the SQLSTATE class ( `23`, integrity
     * constraint violation ) rather than a driver-specific errno so the
     * check works uniformly across MySQL, SQLite, and PostgreSQL.
     */
    protected function isUniqueSlugViolation( QueryException $e ): bool
    {
        $sqlState = ( string ) $e->getCode();

        if ( ! str_starts_with( $sqlState, '23' ) ) {
            return false;
        }

        return str_contains( strtolower( $e->getMessage() ), 'slug' );
    }
}

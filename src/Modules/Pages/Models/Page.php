<?php

declare( strict_types = 1 );

/**
 * Page Model
 *
 * Represents a page in the system with hierarchical structure.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Models;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasCustomFields;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasFeaturedImage;
use ArtisanPackUI\MediaLibrary\Models\Media;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Page Model
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property string|null $excerpt
 * @property int $author_id
 * @property int|null $parent_id
 * @property int $order
 * @property string|null $template
 * @property string $status
 * @property Carbon|null $published_at
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @since 1.0.0
 */
class Page extends Model
{
    use HasCustomFields;
    use HasFactory;
    use HasFeaturedImage;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image_id',
        'author_id',
        'parent_id',
        'order',
        'template',
        'status',
        'published_at',
        'metadata',
    ];

    /**
     * Get the author of the page.
     *
     * @since 1.0.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo( config( 'auth.providers.users.model' ), 'author_id' );
    }

    /**
     * Get the featured image for the page.
     *
     * @since 1.0.0
     */
    public function featuredImageMedia(): BelongsTo
    {
        return $this->belongsTo( Media::class, 'featured_image_id' );
    }

    /**
     * Get the parent page.
     *
     * @since 1.0.0
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo( Page::class, 'parent_id' );
    }

    /**
     * Get the child pages.
     *
     * @since 1.0.0
     */
    public function children(): HasMany
    {
        return $this->hasMany( Page::class, 'parent_id' )->orderBy( 'order' );
    }

    /**
     * Get the categories for the page.
     *
     * @since 1.0.0
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany( PageCategory::class, 'page_category_pivots', 'page_id', 'page_category_id' );
    }

    /**
     * Get the tags for the page.
     *
     * @since 1.0.0
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany( PageTag::class, 'page_tag_pivots', 'page_id', 'page_tag_id' );
    }

    /**
     * Get sibling pages (pages with the same parent).
     *
     * @since 1.0.0
     */
    public function siblings(): HasMany
    {
        // phpcs:ignore ArtisanPackUIStandard.Security.ValidatedSanitizedInput.MissingUnslash -- Model ID is type-safe
        return $this->hasMany( Page::class, 'parent_id', 'parent_id' )
            ->where( 'id', '!=', $this->id )
            ->orderBy( 'order' );
    }

    /**
     * Get all ancestor pages.
     *
     * @since 1.0.0
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $parent    = $this->parent;

        while ( $parent ) {
            $ancestors->prepend( $parent );
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get all descendant pages recursively.
     *
     * @since 1.0.0
     */
    public function descendants(): Collection
    {
        $descendants = collect();

        foreach ( $this->children as $child ) {
            $descendants->push( $child );
            $descendants = $descendants->merge( $child->descendants() );
        }

        return $descendants;
    }

    /**
     * Scope a query to only include published pages.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished( Builder $query )
    {
        return $query->where( 'status', 'published' )
            ->where( function ( $q ): void {
                $q->whereNull( 'published_at' )
                    ->orWhere( 'published_at', '<=', now() );
            } );
    }

    /**
     * Scope a query to only include draft pages.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDraft( Builder $query )
    {
        return $query->where( 'status', 'draft' );
    }

    /**
     * Scope a query to pages by a specific author.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAuthor( Builder $query, int $authorId )
    {
        return $query->where( 'author_id', sanitizeInt( $authorId ) );
    }

    /**
     * Scope a query to top-level pages (no parent).
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTopLevel( Builder $query )
    {
        return $query->whereNull( 'parent_id' );
    }

    /**
     * Scope a query to pages with a specific template.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTemplate( Builder $query, string $template )
    {
        return $query->where( 'template', sanitizeText( $template ) );
    }

    /**
     * Check if the page is published.
     *
     * @since 1.0.0
     */
    public function isPublished(): bool
    {
        return 'published' === $this->status &&
            ( null === $this->published_at || $this->published_at->isPast() );
    }

    /**
     * Get the breadcrumb trail for the page.
     *
     * @since 1.0.0
     */
    public function getBreadcrumbAttribute(): array
    {
        $breadcrumb = [];

        foreach ( $this->ancestors() as $ancestor ) {
            $breadcrumb[] = [
                'title' => $ancestor->title,
                'url'   => $ancestor->permalink,
            ];
        }

        $breadcrumb[] = [
            'title' => $this->title,
            'url'   => $this->permalink,
        ];

        return $breadcrumb;
    }

    /**
     * Get the depth level of the page in the hierarchy.
     *
     * @since 1.0.0
     */
    public function getDepthAttribute(): int
    {
        return $this->ancestors()->count();
    }

    /**
     * Get the permalink for the page.
     *
     * @since 1.0.0
     */
    public function getPermalinkAttribute(): string
    {
        $ancestors = $this->ancestors();

        if ( $ancestors->isEmpty() ) {
            return url( "/{$this->slug}" );
        }

        $path = $ancestors->pluck( 'slug' )->implode( '/' ) . '/' . $this->slug;

        return url( "/{$path}" );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'metadata'     => 'array',
            'order'        => 'integer',
        ];
    }
}

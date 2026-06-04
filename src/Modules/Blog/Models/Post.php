<?php

declare( strict_types=1 );

/**
 * Post Model
 *
 * Represents a blog post in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Models;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasContentStatus;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasCustomFields;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasFeaturedImage;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasRenderedBlockContent;
use ArtisanPackUI\MediaLibrary\Models\Media;
use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Post Model
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property array<int, array<string, mixed>>|null $block_content
 * @property-read string $rendered_content
 * @property string|null $excerpt
 * @property int $author_id
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @since 1.0.0
 */
class Post extends Model
{
    use HasBlockContent;
    use HasContentStatus;
    use HasCustomFields;
    use HasFactory;
    use HasFeaturedImage;
    use HasRenderedBlockContent;
    use SoftDeletes;

    /**
     * The column that stores the visual editor block tree JSON.
     *
     * @since 2.0.0
     */
    protected string $blockContentColumn = 'block_content';

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
        'status',
        'published_at',
        'metadata',
    ];

    /**
     * Get the author of the post.
     *
     * @since 1.0.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo( config( 'auth.providers.users.model' ), 'author_id' );
    }

    /**
     * Get the featured image for the post.
     *
     * @since 1.0.0
     */
    public function featuredImageMedia(): BelongsTo
    {
        return $this->belongsTo( Media::class, 'featured_image_id' );
    }

    /**
     * Get the categories for the post.
     *
     * @since 1.0.0
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany( PostCategory::class, 'post_category_pivots', 'post_id', 'post_category_id' );
    }

    /**
     * Get the tags for the post.
     *
     * @since 1.0.0
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany( PostTag::class, 'post_tag_pivots', 'post_id', 'post_tag_id' );
    }

    /**
     * Get the comments for the post. Returns approved comments by
     * default so consumers (e.g. the visual-editor `CommentResolver`)
     * iterate over the public set out of the box. Use
     * `$post->commentsIncludingUnapproved()` (or query
     * `$post->hasMany(Comment::class)` directly with another status
     * scope) for moderation surfaces.
     *
     * @since 2.1.0
     */
    public function comments(): HasMany
    {
        return $this->hasMany( Comment::class )->approved()->latest();
    }

    /**
     * Get every comment on the post (regardless of moderation
     * status). Used by admin / moderation surfaces; public consumers
     * should use `comments()` above.
     *
     * @since 2.1.0
     */
    public function commentsIncludingUnapproved(): HasMany
    {
        return $this->hasMany( Comment::class );
    }

    /**
     * Convenience integer count of approved comments. Read by the
     * visual-editor's `PostResolver` when stamping
     * `_resolvedCommentCount` onto post-comments-* display blocks.
     *
     * @since 2.1.0
     */
    public function getCommentsCountAttribute(): int
    {
        if ( $this->relationLoaded( 'comments' ) ) {
            return $this->comments->count();
        }

        return $this->comments()->count();
    }

    /**
     * Public URL to the comments section on the post permalink. The
     * visual-editor's `PostResolver` reads this when stamping
     * `_resolvedCommentsUrl` onto the `post-comments-link` block.
     *
     * @since 2.1.0
     */
    public function getCommentsUrlAttribute(): string
    {
        return $this->permalink . '#comments';
    }

    /**
     * Scope a query to posts by a specific author.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    public function scopeByAuthor( Builder $query, int $authorId )
    {
        return $query->where( 'author_id', sanitizeInt( $authorId ) );
    }

    /**
     * Scope a query to posts in a specific category.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    public function scopeByCategory( Builder $query, int $categoryId )
    {
        return $query->whereHas( 'categories', function ( $q ) use ( $categoryId ): void {
            $q->where( 'post_categories.id', sanitizeInt( $categoryId ) );
        } );
    }

    /**
     * Scope a query to posts with a specific tag.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    public function scopeByTag( Builder $query, int $tagId )
    {
        return $query->whereHas( 'tags', function ( $q ) use ( $tagId ): void {
            $q->where( 'post_tags.id', sanitizeInt( $tagId ) );
        } );
    }

    /**
     * Scope a query to posts by year.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    public function scopeByYear( Builder $query, int $year )
    {
        return $query->whereYear( 'published_at', $year );
    }

    /**
     * Scope a query to posts by month and year.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    public function scopeByMonth( Builder $query, int $year, int $month )
    {
        return $query->whereYear( 'published_at', $year )
            ->whereMonth( 'published_at', $month );
    }

    /**
     * Scope a query to posts by specific date.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    public function scopeByDate( Builder $query, Carbon $date )
    {
        return $query->whereDate( 'published_at', $date );
    }

    /**
     * Get the permalink for the post.
     *
     * @since 1.0.0
     */
    public function getPermalinkAttribute(): string
    {
        return url( "/blog/{$this->slug}" );
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
            'status'       => ContentStatus::class,
        ];
    }
}

<?php

declare(strict_types=1);

/**
 * Post Model
 *
 * Represents a blog post in the system.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Models;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasCustomFields;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasFeaturedImage;
use ArtisanPackUI\MediaLibrary\Models\Media;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Post Model
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property string|null $excerpt
 * @property int $author_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @since 2.0.0
 */
class Post extends Model
{
    use HasCustomFields;
    use HasFactory;
    use HasFeaturedImage;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @since 2.0.0
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
     * @since 2.0.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'author_id');
    }

    /**
     * Get the featured image for the post.
     *
     * @since 2.0.0
     */
    public function featuredImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    /**
     * Get the categories for the post.
     *
     * @since 2.0.0
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PostCategory::class, 'post_category_pivots', 'post_id', 'post_category_id');
    }

    /**
     * Get the tags for the post.
     *
     * @since 2.0.0
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PostTag::class, 'post_tag_pivots', 'post_id', 'post_tag_id');
    }

    /**
     * Scope a query to only include published posts.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope a query to only include draft posts.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to posts by a specific author.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAuthor($query, int $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    /**
     * Scope a query to posts in a specific category.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId): void {
            $q->where('post_categories.id', $categoryId);
        });
    }

    /**
     * Scope a query to posts with a specific tag.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTag($query, int $tagId)
    {
        return $query->whereHas('tags', function ($q) use ($tagId): void {
            $q->where('post_tags.id', $tagId);
        });
    }

    /**
     * Scope a query to posts by year.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByYear($query, int $year)
    {
        return $query->whereYear('published_at', $year);
    }

    /**
     * Scope a query to posts by month and year.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMonth($query, int $year, int $month)
    {
        return $query->whereYear('published_at', $year)
            ->whereMonth('published_at', $month);
    }

    /**
     * Scope a query to posts by specific date.
     *
     * @since 2.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDate($query, Carbon $date)
    {
        return $query->whereDate('published_at', $date);
    }

    /**
     * Check if the post is published.
     *
     * @since 2.0.0
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' &&
            ($this->published_at === null || $this->published_at->isPast());
    }

    /**
     * Get the permalink for the post.
     *
     * @since 2.0.0
     */
    public function getPermalinkAttribute(): string
    {
        return url("/blog/{$this->slug}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @since 2.0.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}

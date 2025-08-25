<?php

/**
 * Content Model
 *
 * Represents a generic content item in the CMS Framework, capable of representing
 * various content types like posts, pages, videos, and custom user-defined content types
 * via a 'type' column and 'meta' JSON field.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Services\CacheService;
use ArtisanPackUI\Database\factories\ContentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Content Model
 *
 * Represents a generic content item in the CMS Framework,
 * capable of representing various content types like posts, pages, videos,
 * and custom user-defined content types via a 'type' column and 'meta' JSON field.
 *
 * @since      1.0.0
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property string $type
 * @property string $status
 * @property int $author_id
 * @property int|null $parent_id
 * @property array|null $meta
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder|static ofType( string $type )
 */
class Content extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @since 1.1.0
     *
     * @var string
     */
    protected $table = 'content';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'type',
        'status',
        'author_id',
        'parent_id',
        'meta',
        'published_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.1.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @since 1.0.0
     */
    protected static function newFactory(): Factory
    {
        return ContentFactory::new();
    }

    /**
     * Get the definition for this content item's type.
     *
     * Retrieves the comprehensive definition array for the content type
     * of the current model instance, as registered with the ContentTypeManager.
     *
     * @since 1.1.0
     *
     * @return array|null The content type definition array, or null if the type is not registered.
     */
    public function contentTypeDefinition(): ?array
    {
        return ContentTypeManager::instance()->getContentType($this->type);
    }

    /**
     * Get cache service instance.
     */
    private function getCacheService(): CacheService
    {
        return app(CacheService::class);
    }

    /**
     * Get a specific meta value from the 'meta' JSON column.
     *
     * This helper method provides convenient access to fields stored
     * within the flexible JSON `meta` column.
     *
     * @since 1.1.0
     *
     * @param  string  $key  The dot-notation key for the meta value to retrieve (e.g., 'embed_url').
     * @param  mixed  $default  Optional. The default value to return if the key does not exist. Default null.
     * @return mixed The retrieved meta value, or the default if not found.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'content_meta';
        $cacheParams = ['id' => $this->id, 'key' => $key];

        return $this->getCacheService()->remember(
            'content',
            $cacheKey,
            function () use ($key, $default) {
                return Arr::get($this->meta ?? [], $key, $default);
            },
            $cacheParams
        );
    }

    /**
     * Set a specific meta value in the 'meta' JSON column.
     *
     * This method updates a value within the flexible JSON `meta` column.
     * Remember to save the model after calling this method to persist changes.
     *
     * @since 1.1.0
     *
     * @param  string  $key  The dot-notation key for the meta value to set.
     * @param  mixed  $value  The value to store.
     */
    public function setMeta(string $key, mixed $value): void
    {
        $meta = $this->meta ?? [];
        Arr::set($meta, $key, $value);
        $this->meta = $meta;

        // Invalidate cached meta for this specific key
        $cacheParams = ['id' => $this->id, 'key' => $key];
        $this->getCacheService()->forget('content', 'content_meta', $cacheParams);
    }

    /**
     * Get the author that owns the Content.
     *
     * @since 1.1.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the parent content item for hierarchical content types.
     *
     * @since 1.1.0
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'parent_id');
    }

    /**
     * Get the child content items for hierarchical content types.
     *
     * @since 1.1.0
     */
    public function children(): HasMany
    {
        return $this->hasMany(Content::class, 'parent_id');
    }

    /**
     * The terms that are assigned to the content.
     *
     * Establishes a many-to-many relationship with taxonomy terms.
     *
     * @since 1.1.0
     */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'term_content', 'content_id', 'term_id')
            ->withTimestamps(); // If you added timestamps to the pivot table
    }

    /**
     * Scope a query to only include content of a given type.
     *
     * @since 1.1.0
     *
     * @param  Builder  $query  The Eloquent query builder.
     * @param  string  $type  The content type handle (e.g., 'post', 'video').
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include published content.
     *
     * @since 1.1.0
     *
     * @param  Builder  $query  The Eloquent query builder.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }
}

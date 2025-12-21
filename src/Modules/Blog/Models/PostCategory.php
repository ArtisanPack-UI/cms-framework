<?php

declare( strict_types = 1 );

/**
 * PostCategory Model
 *
 * Represents a post category in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PostCategory Model
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $parent_id
 * @property int $order
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 1.0.0
 */
class PostCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'order',
        'metadata',
    ];

    /**
     * Get the posts in this category.
     *
     * @since 1.0.0
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany( Post::class, 'post_category_pivots', 'post_category_id', 'post_id' );
    }

    /**
     * Get the parent category.
     *
     * @since 1.0.0
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo( PostCategory::class, 'parent_id' );
    }

    /**
     * Get the child categories.
     *
     * @since 1.0.0
     */
    public function children(): HasMany
    {
        return $this->hasMany( PostCategory::class, 'parent_id' );
    }

    /**
     * Get the permalink for the category archive.
     *
     * @since 1.0.0
     */
    public function getPermalinkAttribute(): string
    {
        return url( "/blog/category/{$this->slug}" );
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
            'metadata' => 'array',
        ];
    }
}

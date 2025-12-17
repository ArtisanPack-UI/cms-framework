<?php

/**
 * PageCategory Model
 *
 * Represents a page category in the system.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PageCategory Model
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
 * @since 2.0.0
 */
class PageCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @since 2.0.0
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
     * Get the attributes that should be cast.
     *
     * @since 2.0.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Get the pages in this category.
     *
     * @since 2.0.0
     */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_category_pivots', 'page_category_id', 'page_id');
    }

    /**
     * Get the parent category.
     *
     * @since 2.0.0
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'parent_id');
    }

    /**
     * Get the child categories.
     *
     * @since 2.0.0
     */
    public function children(): HasMany
    {
        return $this->hasMany(PageCategory::class, 'parent_id');
    }

    /**
     * Get the permalink for the category archive.
     *
     * @since 2.0.0
     */
    public function getPermalinkAttribute(): string
    {
        return url("/pages/category/{$this->slug}");
    }
}

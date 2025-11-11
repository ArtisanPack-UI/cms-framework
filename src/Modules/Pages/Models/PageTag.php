<?php

/**
 * PageTag Model
 *
 * Represents a page tag in the system.
 *
 * @since 2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Pages\Models
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * PageTag Model
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $order
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 2.0.0
 */
class PageTag extends Model
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
     * Get the pages with this tag.
     *
     * @since 2.0.0
     */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_tag_pivots', 'page_tag_id', 'page_id');
    }

    /**
     * Get the permalink for the tag archive.
     *
     * @since 2.0.0
     */
    public function getPermalinkAttribute(): string
    {
        return url("/pages/tag/{$this->slug}");
    }
}

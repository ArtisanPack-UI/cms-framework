<?php

declare( strict_types = 1 );

/**
 * PostTag Model
 *
 * Represents a post tag in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * PostTag Model
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
 * @since 1.0.0
 */
class PostTag extends Model
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
        'order',
        'metadata',
    ];

    /**
     * Get the posts with this tag.
     *
     * @since 1.0.0
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany( Post::class, 'post_tag_pivots', 'post_tag_id', 'post_id' );
    }

    /**
     * Get the permalink for the tag archive.
     *
     * @since 1.0.0
     */
    public function getPermalinkAttribute(): string
    {
        return url( "/blog/tag/{$this->slug}" );
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

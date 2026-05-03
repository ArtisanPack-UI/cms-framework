<?php

/**
 * Menu Item Model
 *
 * Represents a single navigation entry within a {@see Menu}. Items support
 * three link types — `link`, `submenu`, `page-list` — matching the upstream
 * `core/navigation-link` / `core/navigation-submenu` / `core/page-list`
 * blocks, so {@see \ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\MenuResolver}
 * projects them into the navigation block shape without translation.
 *
 * The `(object_type, object_id)` pair is a soft polymorphic reference: items
 * survive the deletion of the linked resource and continue rendering with the
 * stored `label` + `url`. Pruning dead links is deferred to V1.1.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property int $position
 * @property string $type
 * @property string $label
 * @property string|null $url
 * @property string $target
 * @property string|null $rel
 * @property string|null $classes
 * @property string|null $description
 * @property string|null $object_type
 * @property int|null $object_id
 * @property string|null $kind
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MenuItem extends Model
{
    use HasFactory;

    /**
     * Menu-item link types matching the upstream navigation block family.
     *
     * @since 1.2.0
     */
    public const TYPE_LINK = 'link';

    public const TYPE_SUBMENU = 'submenu';

    public const TYPE_PAGE_LIST = 'page-list';

    /**
     * @since 1.2.0
     *
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_LINK,
        self::TYPE_SUBMENU,
        self::TYPE_PAGE_LIST,
    ];

    /**
     * @since 1.2.0
     */
    protected $table = 'menu_items';

    /**
     * @since 1.2.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'menu_id',
        'parent_id',
        'position',
        'type',
        'label',
        'url',
        'target',
        'rel',
        'classes',
        'description',
        'object_type',
        'object_id',
        'kind',
    ];

    /**
     * @since 1.2.0
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo( Menu::class );
    }

    /**
     * @since 1.2.0
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo( self::class, 'parent_id' );
    }

    /**
     * Direct children of this item, ordered by position.
     *
     * @since 1.2.0
     */
    public function children(): HasMany
    {
        return $this->hasMany( self::class, 'parent_id' )->orderBy( 'position' );
    }

    /**
     * @since 1.2.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position'  => 'integer',
            'object_id' => 'integer',
        ];
    }

    /**
     * Eloquent-level cascade for child items so deletion behavior is
     * consistent regardless of whether the underlying driver enforces the
     * `parent_id` foreign key constraint declared in the migration.
     *
     * @since 1.2.0
     */
    protected static function booted(): void
    {
        static::deleting( static function ( MenuItem $item ): void {
            $item->children()->each( static fn ( MenuItem $child ): ?bool => $child->delete() );
        } );
    }
}

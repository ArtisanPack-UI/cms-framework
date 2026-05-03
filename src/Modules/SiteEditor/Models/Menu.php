<?php

/**
 * Menu Model
 *
 * Represents a navigation menu — a named collection of {@see MenuItem}
 * records that can be assigned to one or more theme-declared locations
 * via {@see MenuLocationAssignment}.
 *
 * Menus are DB-only (per plan 14 §4.2): themes contribute location *names*
 * via `theme.json` `menus.locations`, but never menu *content*. Switching
 * themes leaves prior menus untouched (data preservation per §5.5);
 * switching back surfaces the same menus and their assignments again.
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
 * @property string $theme
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $auto_add_pages
 * @property int|null $author_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Menu extends Model
{
    use HasFactory;

    /**
     * @since 1.2.0
     */
    protected $table = 'menus';

    /**
     * @since 1.2.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'theme',
        'slug',
        'name',
        'description',
        'auto_add_pages',
        'author_id',
    ];

    /**
     * Items in this menu, ordered by `(parent_id, position)` so consumers
     * receive a deterministic flat list ready to nest.
     *
     * @since 1.2.0
     */
    public function items(): HasMany
    {
        return $this->hasMany( MenuItem::class )->orderBy( 'parent_id' )->orderBy( 'position' );
    }

    /**
     * Locations this menu is assigned to. A single menu can satisfy
     * multiple locations within the same theme.
     *
     * @since 1.2.0
     */
    public function locationAssignments(): HasMany
    {
        return $this->hasMany( MenuLocationAssignment::class );
    }

    /**
     * The user who created this menu.
     *
     * @since 1.2.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo( config( 'artisanpack.cms-framework.user_model' ), 'author_id' );
    }

    /**
     * @since 1.2.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_add_pages' => 'boolean',
        ];
    }

    /**
     * Eloquent-level cascade for items + location assignments. Migrations
     * also declare DB-level `cascadeOnDelete()`, but FK enforcement isn't
     * universal across drivers (notably SQLite needs `PRAGMA foreign_keys`
     * enabled). Cascading at the model layer keeps the behavior consistent
     * regardless of how the connection is configured.
     *
     * @since 1.2.0
     */
    protected static function booted(): void
    {
        static::deleting( static function ( Menu $menu ): void {
            $menu->items()->each( static fn ( MenuItem $item ): ?bool => $item->delete() );
            $menu->locationAssignments()->delete();
        } );
    }
}

<?php

/**
 * Menu Location Assignment Model
 *
 * Joins a {@see Menu} to a theme-declared location key (e.g. `primary`,
 * `footer`). Lives in its own table rather than as a column on `Menu`
 * because (a) one menu can satisfy multiple locations, and (b) location
 * names are theme-scoped — switching themes can leave a menu unassigned
 * without orphaning the menu itself.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $theme
 * @property string $location
 * @property int $menu_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MenuLocationAssignment extends Model
{
    use HasFactory;

    /**
     * @since 2.0.0
     */
    protected $table = 'menu_location_assignments';

    /**
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'theme',
        'location',
        'menu_id',
    ];

    /**
     * @since 2.0.0
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo( Menu::class );
    }
}

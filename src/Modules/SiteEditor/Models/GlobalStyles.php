<?php

/**
 * Global Styles Model
 *
 * Represents the user's customization deltas over a theme's `theme.json`
 * `settings` + `styles` defaults. Singleton per theme — one row per theme,
 * created lazily on the first user write. Switching themes leaves prior
 * rows untouched (data preservation per plan 14 §5.5).
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $theme
 * @property string|null $title
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $styles
 * @property string|null $variation
 * @property int|null $author_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GlobalStyles extends Model
{
    use HasFactory;

    /**
     * @since 1.2.0
     */
    protected $table = 'global_styles';

    /**
     * @since 1.2.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'theme',
        'title',
        'settings',
        'styles',
        'variation',
        'author_id',
    ];

    /**
     * @since 1.2.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(config('artisanpack.cms-framework.user_model'), 'author_id');
    }

    /**
     * @since 1.2.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'styles'   => 'array',
        ];
    }
}

<?php

/**
 * TemplatePart Model
 *
 * Represents a site-editor template part — a reusable chunk of a template
 * (header, footer, sidebar, or general). Either a DB-stored override of a
 * theme file, or a fully custom part authored in the admin.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $theme
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string $area
 * @property string $status
 * @property bool $is_custom
 * @property array<int, array<string, mixed>>|null $block_content
 * @property int|null $author_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TemplatePart extends Model
{
    use HasBlockContent;
    use HasFactory;

    /**
     * Closed list of template-part areas allowed in V1.
     *
     * Matches WordPress's default template-part areas. Open-ended user-defined
     * areas are deferred to V1.1 per plan 14 §8.
     *
     * @since 1.2.0
     */
    public const AREAS = [
        'header',
        'footer',
        'sidebar',
        'general',
    ];

    /**
     * @since 1.2.0
     */
    protected $table = 'template_parts';

    /**
     * The column that stores the visual editor block tree JSON.
     *
     * @since 1.2.0
     */
    protected string $blockContentColumn = 'block_content';

    /**
     * @since 1.2.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'theme',
        'slug',
        'title',
        'description',
        'area',
        'status',
        'is_custom',
        'block_content',
        'author_id',
    ];

    /**
     * The user who last authored or edited this template part.
     *
     * @since 1.2.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(config('artisanpack.cms-framework.user_model'), 'author_id');
    }

    /**
     * @since 1.2.0
     */
    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
        ];
    }
}

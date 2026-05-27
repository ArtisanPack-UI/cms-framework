<?php

/**
 * Template Model
 *
 * Represents a site-editor template — either a DB-stored override of a theme
 * file, or a fully custom template authored in the admin.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

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
 * @property string $status
 * @property bool $is_custom
 * @property array<int, array<string, mixed>>|null $block_content
 * @property int|null $author_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Template extends Model
{
    use HasBlockContent;
    use HasFactory;

    /**
     * @since 2.0.0
     */
    protected $table = 'templates';

    /**
     * The column that stores the visual editor block tree JSON.
     *
     * @since 2.0.0
     */
    protected string $blockContentColumn = 'block_content';

    /**
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'theme',
        'slug',
        'title',
        'description',
        'status',
        'is_custom',
        'block_content',
        'author_id',
    ];

    /**
     * The user who last authored or edited this template.
     *
     * @since 2.0.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo( config( 'artisanpack.cms-framework.user_model' ), 'author_id' );
    }

    /**
     * @since 2.0.0
     */
    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
        ];
    }
}

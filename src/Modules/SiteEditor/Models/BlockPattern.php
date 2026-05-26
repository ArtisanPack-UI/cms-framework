<?php

/**
 * BlockPattern Model
 *
 * Represents a site-editor block pattern. The DB stores user-authored patterns
 * only — theme-shipped patterns live in the active theme's `patterns/` directory
 * and are read at resolve time. Both sources surface together through the
 * `ap.visual-editor.patterns` filter at the visual-editor consumer side.
 *
 * Per plan 14 §5.6, the `slug` column carries a `user/` prefix at storage time
 * so user `single` and theme `single` cannot collide in the merged result map.
 * The unprefixed user-facing slug is exposed via {@see self::userFacingSlug()}
 * and used by the REST surface.
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
 * @property string $slug
 * @property string|null $theme
 * @property string $title
 * @property string|null $description
 * @property string $source
 * @property bool $synced
 * @property array<int, string>|null $categories
 * @property array<int, string>|null $block_types
 * @property array<int, array<string, mixed>>|null $block_content
 * @property int|null $author_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BlockPattern extends Model
{
    use HasBlockContent;
    use HasFactory;

    /**
     * Allowed pattern sources. The DB stores `user` rows only — `theme` is
     * recorded here for symmetry and to keep the door open to future cached
     * theme-pattern rows without a schema change.
     *
     * @since 2.0.0
     */
    public const SOURCE_THEME = 'theme';

    public const SOURCE_USER = 'user';

    public const SOURCES = [
        self::SOURCE_THEME,
        self::SOURCE_USER,
    ];

    /**
     * Storage prefix applied to user-source slugs per plan 14 §5.6. Keeps
     * theme/user namespaces distinct in the merged `ap.visual-editor.patterns`
     * map at the visual-editor consumer side.
     *
     * @since 2.0.0
     */
    public const USER_SLUG_PREFIX = 'user/';

    /**
     * @since 2.0.0
     */
    protected $table = 'block_patterns';

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
        'slug',
        'theme',
        'title',
        'description',
        'source',
        'synced',
        'categories',
        'block_types',
        'block_content',
        'author_id',
    ];

    /**
     * The user who last authored or edited this pattern.
     *
     * @since 2.0.0
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo( config( 'artisanpack.cms-framework.user_model' ), 'author_id' );
    }

    /**
     * The user-facing slug (storage slug minus the `user/` prefix).
     *
     * REST resources and inspector UIs consume this. The raw stored slug
     * remains accessible via `$pattern->slug` for queries that need the
     * namespaced form.
     *
     * @since 2.0.0
     */
    public function userFacingSlug(): string
    {
        return self::stripUserPrefix( $this->slug );
    }

    /**
     * Adds the `user/` prefix to a slug if missing. Idempotent — safe to call
     * on already-prefixed values.
     *
     * @since 2.0.0
     */
    public static function withUserPrefix( string $slug ): string
    {
        return str_starts_with( $slug, self::USER_SLUG_PREFIX )
            ? $slug
            : self::USER_SLUG_PREFIX . $slug;
    }

    /**
     * Removes the `user/` prefix if present. Returns the slug unchanged when
     * no prefix is present.
     *
     * @since 2.0.0
     */
    public static function stripUserPrefix( string $slug ): string
    {
        return str_starts_with( $slug, self::USER_SLUG_PREFIX )
            ? substr( $slug, strlen( self::USER_SLUG_PREFIX ) )
            : $slug;
    }

    /**
     * Mutator that auto-prefixes user-source slugs at write time. Theme-source
     * rows (rare — the table primarily holds user patterns) keep raw slugs.
     *
     * Implemented as a setter mutator rather than an `Attribute::make` cast
     * because the prefix decision depends on the sibling `source` field, and
     * the cast layer cannot read peer attributes during resolution.
     *
     * @since 2.0.0
     */
    public function setSlugAttribute( string $value ): void
    {
        $source = $this->attributes['source'] ?? self::SOURCE_USER;

        $this->attributes['slug'] = self::SOURCE_USER === $source
            ? self::withUserPrefix( $value )
            : $value;
    }

    /**
     * @since 2.0.0
     */
    protected function casts(): array
    {
        return [
            'synced'      => 'boolean',
            'categories'  => 'array',
            'block_types' => 'array',
        ];
    }
}

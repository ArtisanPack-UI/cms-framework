<?php

declare( strict_types = 1 );

/**
 * ContentType Model
 *
 * Represents a content type in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * ContentType Model
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $table_name
 * @property string $model_class
 * @property string|null $description
 * @property bool $hierarchical
 * @property bool $has_archive
 * @property string|null $archive_slug
 * @property array|null $supports
 * @property array|null $metadata
 * @property bool $public
 * @property bool $show_in_admin
 * @property string|null $icon
 * @property int|null $menu_position
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 1.0.0
 */
class ContentType extends Model
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
        'table_name',
        'model_class',
        'description',
        'hierarchical',
        'has_archive',
        'archive_slug',
        'supports',
        'metadata',
        'public',
        'show_in_admin',
        'icon',
        'menu_position',
    ];

    /**
     * Get an instance of the model class.
     *
     * @since 1.0.0
     */
    public function getModelInstance(): ?Model
    {
        if ( ! class_exists( $this->model_class ) ) {
            return null;
        }

        return new $this->model_class;
    }

    /**
     * Check if the content type supports a specific feature.
     *
     * @since 1.0.0
     */
    public function supportsFeature( string $feature ): bool
    {
        if ( null === $this->supports ) {
            return false;
        }

        return in_array( $feature, $this->supports, true );
    }

    /**
     * Get the custom fields for this content type.
     *
     * @since 1.0.0
     */
    public function getCustomFields(): Collection
    {
        return CustomField::whereJsonContains( 'content_types', $this->slug )->get();
    }

    /**
     * Scope a query to include custom fields count.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCustomFieldsCount( Builder $query )
    {
        return $query->selectSub(
            CustomField::selectRaw( 'count(*)' )
                ->whereRaw( "JSON_CONTAINS(content_types, CONCAT('\"', content_types.slug, '\"'))" ),
            'custom_fields_count',
        );
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
            'hierarchical'  => 'boolean',
            'has_archive'   => 'boolean',
            'public'        => 'boolean',
            'show_in_admin' => 'boolean',
            'supports'      => 'array',
            'metadata'      => 'array',
        ];
    }
}

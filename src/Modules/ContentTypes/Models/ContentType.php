<?php

declare( strict_types=1 );

/**
 * ContentType Model
 *
 * Represents a content type in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasSupports;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * ContentType Model
 *
 * @property int $id
 * @property string $name
 * @property string|null $singular_name
 * @property string|null $plural_name
 * @property array<string, string|null>|null $labels
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
    use HasSupports;

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'singular_name',
        'plural_name',
        'labels',
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
     * @param  Builder  $query
     *
     * @return Builder
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
     * Get the singular label ( e.g. "Package" ), falling back to `name`.
     *
     * @since 2.13.0
     */
    public function getSingularLabel(): string
    {
        return $this->filledString( $this->singular_name ) ?? (string) $this->name;
    }

    /**
     * Get the plural label ( e.g. "Packages" ), falling back to the
     * pluralized singular label.
     *
     * @since 2.13.0
     */
    public function getPluralLabel(): string
    {
        return $this->filledString( $this->plural_name ) ?? Str::plural( $this->getSingularLabel() );
    }

    /**
     * Get the full label set, modelled on WordPress's post type `labels`.
     *
     * Defaults are derived from the singular / plural labels; any non-empty
     * string in the `labels` column overrides the default for its key, and
     * extra keys are passed through for consumers that define their own.
     *
     * @since 2.13.0
     *
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        $singular = $this->getSingularLabel();
        $plural   = $this->getPluralLabel();

        $defaults = [
            'singular_name'      => $singular,
            'plural_name'        => $plural,
            'menu_name'          => $plural,
            'add_new'            => __( 'Add New' ),
            'add_new_item'       => __( 'Add New :singular', [ 'singular' => $singular ] ),
            'new_item'           => __( 'New :singular', [ 'singular' => $singular ] ),
            'edit_item'          => __( 'Edit :singular', [ 'singular' => $singular ] ),
            'view_item'          => __( 'View :singular', [ 'singular' => $singular ] ),
            'view_items'         => __( 'View :plural', [ 'plural' => $plural ] ),
            'all_items'          => __( 'All :plural', [ 'plural' => $plural ] ),
            'search_items'       => __( 'Search :plural', [ 'plural' => $plural ] ),
            'not_found'          => __( 'No :plural found.', [ 'plural' => $plural ] ),
            'not_found_in_trash' => __( 'No :plural found in Trash.', [ 'plural' => $plural ] ),
            'parent_item_colon'  => __( 'Parent :singular:', [ 'singular' => $singular ] ),
            'archives'           => __( ':singular Archives', [ 'singular' => $singular ] ),
        ];

        $overrides = array_filter(
            is_array( $this->labels ) ? $this->labels : [],
            fn ( $value, $key ): bool => is_string( $key ) && null !== $this->filledString( $value ),
            ARRAY_FILTER_USE_BOTH,
        );

        return array_merge( $defaults, $overrides );
    }

    /**
     * Get a single label by key, or null when the key is unknown.
     *
     * @since 2.13.0
     *
     * @param  string  $key  Label key, e.g. `add_new_item`.
     */
    public function getLabel( string $key ): ?string
    {
        return $this->getLabels()[ $key ] ?? null;
    }

    /**
     * Hand the DB-persisted `supports` array to {@see HasSupports}. Falling
     * back to `null` when the column is empty lets the trait's default
     * resolution ( `[title, editor]` minimum ) kick in for legacy rows that
     * predate the column being populated.
     *
     * @since 2.6.0
     *
     * @return list<string>|null
     */
    protected function explicitSupports(): ?array
    {
        return is_array( $this->supports ) ? $this->supports : null;
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
            'labels'        => 'array',
        ];
    }

    /**
     * Return the value when it is a non-blank string, otherwise null.
     *
     * @since 2.13.0
     */
    private function filledString( mixed $value ): ?string
    {
        return is_string( $value ) && '' !== trim( $value ) ? $value : null;
    }
}

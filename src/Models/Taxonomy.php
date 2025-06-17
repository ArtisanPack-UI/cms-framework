<?php

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\Database\factories\TaxonomyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

// Assuming Content model for relationship

/**
 * Taxonomy Model.
 *
 * Represents a custom taxonomy (e.g., categories, tags) within the ArtisanPack UI CMS Framework.
 * This model defines the structure and properties of dynamically created taxonomies.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.1.0
 *
 * @property int                        $id
 * @property string                     $handle         Unique identifier for the taxonomy (machine name).
 * @property string                     $label          Singular human-readable name.
 * @property string                     $label_plural   Plural human-readable name.
 * @property array|null                 $content_types  JSON array of content type handles this taxonomy applies to.
 * @property bool                       $hierarchical   Whether terms in this taxonomy can have parents.
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Taxonomy extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @since 1.1.0
     * @var string
     */
    protected $table = 'taxonomies';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.1.0
     * @var array<int, string>
     */
    protected $fillable = [
        'handle',
        'label',
        'label_plural',
        'content_types',
        'hierarchical',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.1.0
     * @var array<string, string>
     */
    protected $casts = [
        'content_types' => 'array',
        'hierarchical'  => 'boolean',
    ];

    /**
     * Get the terms associated with this taxonomy.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function terms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany( Term::class );
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return TaxonomyFactory::new();
    }
}

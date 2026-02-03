<?php

declare( strict_types = 1 );

/**
 * Taxonomy Model
 *
 * Represents a taxonomy (category system, tag system, etc.) in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taxonomy Model
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $content_type_slug
 * @property string|null $description
 * @property bool $hierarchical
 * @property bool $show_in_admin
 * @property string|null $rest_base
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 1.0.0
 */
class Taxonomy extends Model
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
        'content_type_slug',
        'description',
        'hierarchical',
        'show_in_admin',
        'rest_base',
        'metadata',
    ];

    /**
     * Get the content type that this taxonomy belongs to.
     *
     * @since 1.0.0
     */
    public function contentType(): BelongsTo
    {
        return $this->belongsTo( ContentType::class, 'content_type_slug', 'slug' );
    }

    /**
     * Get the table name for terms of this taxonomy.
     *
     * This is typically the slug of the taxonomy (e.g., "post_categories", "page_tags").
     *
     * @since 1.0.0
     */
    public function getTermsTable(): string
    {
        return $this->slug;
    }

    /**
     * Get the model class for terms of this taxonomy.
     *
     * This attempts to derive the model class from the slug.
     * Example: "post_categories" -> "ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory"
     *
     * @since 1.0.0
     */
    public function getTermModel(): ?string
    {
        // Check if metadata has a custom model class
        if ( isset( $this->metadata['model_class'] ) ) {
            return $this->metadata['model_class'];
        }

        // Try to derive from content type and taxonomy slug
        $contentType = $this->contentType;
        if ( ! $contentType ) {
            return null;
        }

        // Get the model namespace from content type model class
        $modelClass = $contentType->model_class;
        $namespace  = substr( $modelClass, 0, strrpos( $modelClass, '\\' ) );

        // Convert taxonomy slug to model class name
        // Example: "post_categories" -> "PostCategory"
        $modelName = str_replace( '_', '', ucwords( $this->slug, '_' ) );
        $modelName = rtrim( $modelName, 's' ); // Remove trailing 's' if present

        return $namespace . '\\' . $modelName;
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
            'show_in_admin' => 'boolean',
            'metadata'      => 'array',
        ];
    }
}

<?php

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\Database\factories\ContentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * ContentType Model.
 *
 * Represents a user-defined content type within the CMS Framework.
 * This model stores the schema and properties of dynamically created content types.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.1.0
 *
 * @property int                        $id
 * @property string                     $handle        Unique identifier for the content type (machine name).
 * @property string                     $label         Singular human-readable name.
 * @property string                     $label_plural  Plural human-readable name.
 * @property string                     $slug          Base slug for URLs related to this content type.
 * @property array                      $definition    JSON array containing the content type's full schema and
 *           properties.
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ContentType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @since 1.1.0
     * @var string
     */
    protected $table = 'content_types';

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
        'slug',
        'definition',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.1.0
     * @var array<string, string>
     */
    protected $casts = [
        'definition' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return ContentTypeFactory::new();
    }
}

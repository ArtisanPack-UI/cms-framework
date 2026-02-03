<?php

declare( strict_types = 1 );

/**
 * CustomField Model
 *
 * Represents a custom field in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * CustomField Model
 *
 * @property int $id
 * @property string $name
 * @property string $key
 * @property string $type
 * @property string $column_type
 * @property string|null $description
 * @property array $content_types
 * @property array|null $options
 * @property int $order
 * @property bool $required
 * @property string|null $default_value
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 1.0.0
 */
class CustomField extends Model
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
        'key',
        'type',
        'column_type',
        'description',
        'content_types',
        'options',
        'order',
        'required',
        'default_value',
    ];

    /**
     * Get the migration column definition for this field.
     *
     * @since 1.0.0
     */
    public function getMigrationColumnDefinition(): string
    {
        $definition = "\$table->{$this->column_type}('{$this->key}')";

        // Add nullable if not required
        if ( ! $this->required ) {
            $definition .= '->nullable()';
        }

        // Add default value if specified
        if ( null !== $this->default_value ) {
            $default = is_numeric( $this->default_value ) ? $this->default_value : "'{$this->default_value}'";
            $definition .= "->default({$default})";
        }

        $definition .= ';';

        return $definition;
    }

    /**
     * Get the content types this field belongs to.
     *
     * @since 1.0.0
     */
    public function getContentTypes(): Collection
    {
        return ContentType::whereIn( 'slug', $this->content_types )->get();
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
            'content_types' => 'array',
            'options'       => 'array',
            'required'      => 'boolean',
        ];
    }
}

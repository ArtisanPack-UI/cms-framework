<?php

declare( strict_types=1 );

/**
 * CustomField Model
 *
 * Represents a custom field in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ColumnType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\FieldType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Registries\CustomFieldTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Support\FieldTypeDefinition;
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
 * @property ColumnType $column_type
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
        $definition = "\$table->{$this->column_type->value}('{$this->key}')";

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
     * Resolve the `FieldType` enum case for this field's type, if the type
     * corresponds to one of the framework's built-in cases. Returns null for
     * plugin-registered types that live outside the enum.
     *
     * @since 2.4.0
     */
    public function fieldTypeEnum(): ?FieldType
    {
        return FieldType::tryFrom( $this->type );
    }

    /**
     * Resolve the `FieldTypeDefinition` for this field's type from the
     * `CustomFieldTypeRegistry`. Covers both built-in and plugin-registered types.
     * Returns null when the type is unregistered.
     *
     * @since 2.4.0
     */
    public function fieldTypeDefinition(): ?FieldTypeDefinition
    {
        return app( CustomFieldTypeRegistry::class )->get( $this->type );
    }

    /**
     * Where this field's values are stored on a content record.
     *
     * - `column`  → a physical column materialized on the content type's table
     *   ( the DB-registered path used by `CustomFieldManager::createField()` ).
     * - `metadata` → the value lives in the model's `metadata` JSON column;
     *   used by filter-registered fields ( plugins ) that never get a physical
     *   column.
     *
     * @since 2.4.0
     *
     * @return 'column'|'metadata'
     */
    public function storageMode(): string
    {
        $explicit = $this->getAttribute( 'storage' );

        if ( 'metadata' === $explicit || 'column' === $explicit ) {
            return $explicit;
        }

        // Persisted DB rows always materialize a physical column.
        return $this->exists ? 'column' : 'metadata';
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
            'type'          => 'string',
            'column_type'   => ColumnType::class,
            'content_types' => 'array',
            'options'       => 'array',
            'required'      => 'boolean',
        ];
    }
}

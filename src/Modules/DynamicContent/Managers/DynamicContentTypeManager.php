<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Source;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentField;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Support\Facades\DB;

/**
 * Writes / reads DB-backed dynamic content types.
 *
 * @since 2.4.0
 */
class DynamicContentTypeManager
{
    public function __construct( protected DynamicContentTypeRegistry $registry )
    {
    }

    /**
     * Create a type from validated input.
     *
     * @param  array<string, mixed>  $data
     */
    public function create( array $data ): DynamicContentType
    {
        return DB::transaction( function () use ( $data ): DynamicContentType {
            $type = DynamicContentType::create( [
                'slug'        => $data['slug'],
                'name'        => $data['name'],
                'cardinality' => $data['cardinality'],
                'source'      => Source::Db->value,
                'description' => $data['description'] ?? null,
                'icon'        => $data['icon'] ?? null,
            ] );

            $this->syncFields( $type, $data['fields'] ?? [] );

            $this->registry->forget();

            return $type->fresh( 'fields' );
        } );
    }

    /**
     * Update an existing type.
     *
     * @param  array<string, mixed>  $data
     */
    public function update( DynamicContentType $type, array $data ): DynamicContentType
    {
        return DB::transaction( function () use ( $type, $data ): DynamicContentType {
            // Slug is intentionally not updated: mutating it would silently
            // break every persisted `{{oldslug.field}}` token across the site.
            $type->update( [
                'name'        => $data['name'] ?? $type->name,
                'cardinality' => $data['cardinality'] ?? $type->cardinality->value,
                'description' => $data['description'] ?? $type->description,
                'icon'        => $data['icon'] ?? $type->icon,
            ] );

            if ( array_key_exists( 'fields', $data ) ) {
                $this->syncFields( $type, $data['fields'] );
            }

            $this->registry->forget();

            return $type->fresh( 'fields' );
        } );
    }

    public function delete( DynamicContentType $type ): void
    {
        DB::transaction( function () use ( $type ): void {
            $type->delete();
            $this->registry->forget();
        } );
    }

    /**
     * Replace the type's fields with the given definition.
     *
     * @param  array<int, array<string, mixed>>  $fields
     */
    protected function syncFields( DynamicContentType $type, array $fields ): void
    {
        $seen = [];

        foreach ( $fields as $order => $field ) {
            $model = $type->fields()->updateOrCreate(
                [ 'slug' => $field['slug'] ],
                [
                    'label'         => $field['label'],
                    'type'          => $field['type'],
                    'options'       => $field['options'] ?? null,
                    'default_value' => $field['default'] ?? null,
                    'required'      => (bool) ( $field['required'] ?? false ),
                    'order'         => (int) ( $field['order'] ?? $order ),
                ],
            );

            $seen[] = $model->id;
        }

        DynamicContentField::query()
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $type->id is a model primary key passed as an Eloquent where() binding.
            ->where( 'dynamic_content_type_id', $type->id )
            ->whereNotIn( 'id', $seen )
            ->delete();
    }
}

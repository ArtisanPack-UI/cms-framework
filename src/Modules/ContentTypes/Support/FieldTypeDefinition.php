<?php

declare( strict_types=1 );

/**
 * FieldTypeDefinition Value Object.
 *
 * Describes a custom-field type registered with the framework's CustomFieldTypeRegistry.
 * Both built-in types (mirrored from the `FieldType` enum) and plugin-registered
 * types hydrate into this shape so consumers can treat them uniformly.
 *
 * @since 2.4.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Support;

/**
 * Immutable descriptor for a registered field type.
 *
 * @since 2.4.0
 */
final class FieldTypeDefinition
{
    /**
     * @param  string  $slug  Machine key ( e.g. `text`, `map_picker` ).
     * @param  string  $label  Human-readable label ( already translated when possible ).
     * @param  string  $columnType  Laravel Schema column-method slug for DB-materialized fields ( e.g. `string`, `text`, `integer` ). Plugin types that store via `metadata` may still declare a column type for documentation purposes; it is only used by `CustomFieldManager::createField()` on the DB-materialized path.
     * @param  array<int,object|string>  $validationRules  Laravel validation rules applied to values of this type.
     * @param  string|null  $editorComponent  Frontend admin-editor component identifier ( host-resolved ).
     * @param  string|null  $rendererComponent  Frontend public-renderer component identifier ( host-resolved ).
     * @param  array<string,mixed>  $meta  Freeform metadata a host or plugin can attach ( icon slug, category, etc. ).
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $columnType = 'string',
        public readonly array $validationRules = [],
        public readonly ?string $editorComponent = null,
        public readonly ?string $rendererComponent = null,
        public readonly array $meta = [],
    ) {
    }

    /**
     * Convenience factory for array-shaped registrations coming from plugin
     * manifests or the `ap.contentTypes.registeredFieldTypes` filter.
     *
     * @since 2.4.0
     *
     * @param  array{slug:string,label?:string,column_type?:string,columnType?:string,validation_rules?:array,validationRules?:array,editor_component?:string|null,editorComponent?:string|null,renderer_component?:string|null,rendererComponent?:string|null,meta?:array<string,mixed>}  $args
     */
    public static function fromArray( array $args ): self
    {
        return new self(
            slug              : ( string ) $args['slug'],
            label             : ( string ) ( $args['label'] ?? ucfirst( $args['slug'] ) ),
            columnType        : ( string ) ( $args['column_type'] ?? $args['columnType'] ?? 'string' ),
            validationRules   : ( array ) ( $args['validation_rules'] ?? $args['validationRules'] ?? [] ),
            editorComponent   : $args['editor_component'] ?? $args['editorComponent'] ?? null,
            rendererComponent : $args['renderer_component'] ?? $args['rendererComponent'] ?? null,
            meta              : ( array ) ( $args['meta'] ?? [] ),
        );
    }

    /**
     * Array-shape representation for JSON APIs and Blade templates.
     *
     * @since 2.4.0
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'slug'               => $this->slug,
            'label'              => $this->label,
            'column_type'        => $this->columnType,
            'validation_rules'   => $this->validationRules,
            'editor_component'   => $this->editorComponent,
            'renderer_component' => $this->rendererComponent,
            'meta'               => $this->meta,
        ];
    }
}

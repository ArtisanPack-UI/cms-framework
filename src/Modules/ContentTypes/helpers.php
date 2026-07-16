<?php

declare( strict_types=1 );

/**
 * ContentTypes Module Helper Functions
 *
 * Provides convenient helper functions for working with content types.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Registries\CustomFieldTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Support\FieldTypeDefinition;

if ( ! function_exists( 'getContentType' ) ) {
    /**
     * Get a content type by slug.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The content type slug.
     *
     * @return ContentType|null The content type instance or null if not found.
     */
    function getContentType( string $slug ): ?ContentType
    {
        return app( ContentTypeManager::class )->getContentType( $slug );
    }
}

if ( ! function_exists( 'contentTypeExists' ) ) {
    /**
     * Check if a content type exists by slug.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The content type slug.
     *
     * @return bool True if the content type exists, false otherwise.
     */
    function contentTypeExists( string $slug ): bool
    {
        return app( ContentTypeManager::class )->contentTypeExists( $slug );
    }
}

if ( ! function_exists( 'apRegisterFieldType' ) ) {
    /**
     * Register a custom-field type with the framework's CustomFieldTypeRegistry.
     *
     * Signature matches sibling `apRegister*` helpers ( slug first, definition
     * second ). Accepts either an array of arguments understood by
     * `FieldTypeDefinition::fromArray()` or a fully-constructed
     * `FieldTypeDefinition`. The `$slug` always wins over any `slug` key
     * inside the definition payload.
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>|FieldTypeDefinition  $definition
     */
    function apRegisterFieldType( string $slug, FieldTypeDefinition|array $definition ): FieldTypeDefinition
    {
        if ( is_array( $definition ) ) {
            $definition['slug'] = $slug;
            $definition         = FieldTypeDefinition::fromArray( $definition );
        } elseif ( $definition->slug !== $slug ) {
            // A pre-built definition with a mismatched slug — re-hydrate so the
            // registry stays keyed by the caller's slug.
            $definition = FieldTypeDefinition::fromArray( array_merge(
                $definition->toArray(),
                ['slug' => $slug],
            ) );
        }

        app( CustomFieldTypeRegistry::class )->register( $definition );

        return $definition;
    }
}

if ( ! function_exists( 'apGetFieldType' ) ) {
    /**
     * Fetch a registered field-type definition by slug, or null if unknown.
     *
     * @since 2.4.0
     */
    function apGetFieldType( string $slug ): ?FieldTypeDefinition
    {
        return app( CustomFieldTypeRegistry::class )->get( $slug );
    }
}

if ( ! function_exists( 'apRegisteredFieldTypes' ) ) {
    /**
     * Return every registered field-type definition keyed by slug.
     *
     * @since 2.4.0
     *
     * @return array<string,FieldTypeDefinition>
     */
    function apRegisteredFieldTypes(): array
    {
        return app( CustomFieldTypeRegistry::class )->all();
    }
}

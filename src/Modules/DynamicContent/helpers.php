<?php

declare( strict_types=1 );

/**
 * DynamicContent Module Helper Functions.
 *
 * SECURITY: dynamic content is a public content pipeline. `apRenderContent()`
 * and the `@dynamicContent` Blade directive resolve tokens against every
 * registered type without a per-request authorization check — the intent is
 * that any content author who can write a token can substitute any dynamic
 * content field. Do NOT store secrets in dynamic content records. See
 * {@see ArtisanPackUI\CMSFramework\Modules\DynamicContent\Casts\DynamicContentCast}
 * for the full note.
 *
 * @since 2.4.0
 */

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services\DynamicContentResolver;

if ( ! function_exists( 'apRegisterDynamicContentType' ) ) {
    /**
     * Register a dynamic content type in code.
     *
     * @since 2.4.0
     *
     * @param  string  $slug  Unique type slug.
     * @param  array<string, mixed>  $definition  Type definition (name, cardinality, fields, records).
     */
    function apRegisterDynamicContentType( string $slug, array $definition ): void
    {
        app( DynamicContentTypeRegistry::class )->registerCodeType( $slug, $definition );
    }
}

if ( ! function_exists( 'apRenderContent' ) ) {
    /**
     * Render dynamic-content tokens inside a string.
     *
     * @since 2.4.0
     *
     * @param  string  $content  Content containing `{{source.field}}` tokens.
     * @param  array<string, mixed>  $context  Optional context for logging.
     */
    function apRenderContent( string $content, array $context = [] ): string
    {
        return app( DynamicContentResolver::class )->render( $content, $context );
    }
}

if ( ! function_exists( 'apDynamicContentSignature' ) ) {
    /**
     * Signature of the resolver's state for `$content` — for cache keys.
     *
     * @since 2.4.0
     */
    function apDynamicContentSignature( string $content ): string
    {
        return app( DynamicContentResolver::class )->signatureFor( $content );
    }
}

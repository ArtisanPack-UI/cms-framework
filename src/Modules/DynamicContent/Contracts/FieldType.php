<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts;

use Illuminate\Support\HtmlString;

/**
 * Contract for a dynamic-content field type.
 *
 * @since 2.4.0
 */
interface FieldType
{
    /**
     * The type slug (e.g. "text", "email", "image").
     */
    public function slug(): string;

    /**
     * The human label used in the admin UI.
     */
    public function label(): string;

    /**
     * Cast a raw stored value into its runtime representation.
     */
    public function cast( mixed $value, array $options = [] ): mixed;

    /**
     * Render the value as HTML-safe output.
     *
     * Implementations MUST return output that is safe to echo into an HTML
     * context — either intentionally-authored HTML (rich text) or escaped
     * plain text. Callers echo the result raw via `@dynamicContent`, so
     * anything unsafe returned here is a stored-XSS sink.
     */
    public function render( mixed $value, array $options = [] ): HtmlString;
}

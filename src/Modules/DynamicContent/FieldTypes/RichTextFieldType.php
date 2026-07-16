<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes;

use Illuminate\Support\HtmlString;

/**
 * Rich-text field: content is author-supplied HTML. Not escaped on render.
 *
 * If a host application accepts untrusted authors for this field type, it
 * must sanitize the input at save time (e.g. via `artisanpack-ui/security`'s
 * `kses()` helper) before it reaches the resolver — see the security note
 * on {@see \ArtisanPackUI\CMSFramework\Modules\DynamicContent\Casts\DynamicContentCast}.
 *
 * @since 2.4.0
 */
class RichTextFieldType extends AbstractFieldType
{
    public function slug(): string
    {
        return 'rich_text';
    }

    public function label(): string
    {
        return __( 'Rich Text' );
    }

    public function render( mixed $value, array $options = [] ): HtmlString
    {
        if ( $value instanceof HtmlString ) {
            return $value;
        }

        if ( null === $value || ! is_scalar( $value ) ) {
            return new HtmlString( '' );
        }

        return new HtmlString( (string) $value );
    }
}

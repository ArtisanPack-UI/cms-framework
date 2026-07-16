<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Image field: value is a media library id.
 *
 * When rendered as a token, resolves to the media URL (default size),
 * HTML-escaped so it's safe in an attribute context.
 * Block-binding integrations consume the raw media id directly.
 *
 * @since 2.4.0
 */
class ImageFieldType extends AbstractFieldType
{
    public function slug(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return __( 'Image' );
    }

    public function render( mixed $value, array $options = [] ): HtmlString
    {
        if ( null === $value || '' === $value ) {
            return new HtmlString( '' );
        }

        $mediaId = is_numeric( $value ) ? (int) $value : null;

        if ( null === $mediaId ) {
            return new HtmlString( '' );
        }

        if ( ! function_exists( 'apGetMediaUrl' ) ) {
            Log::channel(
                config( 'logging.channels.dynamic-content' ) ? 'dynamic-content' : 'stack',
            )->warning(
                'Dynamic content: image field rendered but apGetMediaUrl() is unavailable — media-library package likely missing',
                [ 'media_id' => $mediaId ],
            );

            return new HtmlString( '' );
        }

        $url = apGetMediaUrl( $mediaId, $options['size'] ?? 'full' );

        return new HtmlString( null === $url ? '' : e( (string) $url ) );
    }
}

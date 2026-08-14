<?php

/**
 * Upload Theme Request
 *
 * Validates the multipart payload accepted by the theme upload endpoint.
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\Core\Http\Requests\ZipUploadRequest;

/**
 * Form request for uploading a theme ZIP archive.
 *
 * Authorization requires the `manage-themes` ability; see
 * {@see ZipUploadRequest} for the deny-by-default rationale.
 *
 * @since 2.8.0
 */
class UploadThemeRequest extends ZipUploadRequest
{
    /**
     * The multipart field name carrying the uploaded archive.
     *
     * @since 2.8.0
     *
     * @return string The upload field name.
     */
    protected function fieldName(): string
    {
        return 'theme_zip';
    }

    /**
     * The config key (in bytes) capping the upload size.
     *
     * @since 2.8.0
     *
     * @return string The size config key.
     */
    protected function sizeConfigKey(): string
    {
        return 'cms.themes.maxUploadSize';
    }

    /**
     * The Gate ability required to upload a theme.
     *
     * @since 2.8.0
     *
     * @return string The ability name.
     */
    protected function ability(): string
    {
        return 'manage-themes';
    }

    /**
     * The lowercase noun used in validation messages.
     *
     * @since 2.8.0
     *
     * @return string The message noun.
     */
    protected function noun(): string
    {
        return 'theme';
    }
}

<?php

/**
 * Install Plugin Request
 *
 * Validates the multipart payload accepted by the plugin install endpoint.
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\Core\Http\Requests\ZipUploadRequest;

/**
 * Form request for installing a plugin from a ZIP archive.
 *
 * Authorization requires the `manage-plugins` ability; see
 * {@see ZipUploadRequest} for the deny-by-default rationale.
 *
 * @since 2.8.0
 */
class InstallPluginRequest extends ZipUploadRequest
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
        return 'plugin_zip';
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
        return 'cms.plugins.maxUploadSize';
    }

    /**
     * The Gate ability required to install a plugin.
     *
     * @since 2.8.0
     *
     * @return string The ability name.
     */
    protected function ability(): string
    {
        return 'manage-plugins';
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
        return 'plugin';
    }
}

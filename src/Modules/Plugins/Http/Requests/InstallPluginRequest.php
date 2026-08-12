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

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for installing a plugin from a ZIP archive.
 *
 * Front-loading these rules into a form request means a malformed upload
 * fails as a `ValidationException` — a 422 carrying an `errors` bag keyed by
 * `plugin_zip` — which is the shape Inertia's error bag and pure-API consumers
 * both understand.
 *
 * @since 2.8.0
 */
class InstallPluginRequest extends FormRequest
{
    /**
     * Determines if the user is authorized to make this request.
     *
     * Authorization is handled by the route's `auth` middleware; the framework
     * grants no plugin-specific ability of its own.
     *
     * @since 2.8.0
     *
     * @return bool True if the user is authorized, false otherwise.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * @since 2.8.0
     *
     * @return array<string, mixed> The validation rules.
     */
    public function rules(): array
    {
        return [
            'plugin_zip' => [
                'required',
                'file',
                'mimes:zip',
                'max:' . $this->maxUploadKilobytes(),
            ],
        ];
    }

    /**
     * Gets custom messages for validator errors.
     *
     * @since 2.8.0
     *
     * @return array<string, string> The custom error messages.
     */
    public function messages(): array
    {
        return [
            'plugin_zip.required' => __( 'A plugin ZIP archive is required.' ),
            'plugin_zip.file'     => __( 'The plugin upload must be a file.' ),
            'plugin_zip.mimes'    => __( 'The plugin upload must be a ZIP archive.' ),
            'plugin_zip.max'      => __( 'The plugin ZIP archive may not be larger than :max kilobytes.' ),
        ];
    }

    /**
     * Gets the upload size ceiling in kilobytes.
     *
     * `cms.plugins.maxUploadSize` is expressed in bytes; Laravel's `max` rule
     * for files is expressed in kilobytes.
     *
     * @since 2.8.0
     *
     * @return int The maximum upload size in kilobytes.
     */
    private function maxUploadKilobytes(): int
    {
        return intdiv( (int) config( 'cms.plugins.maxUploadSize', 10 * 1024 * 1024 ), 1024 );
    }
}

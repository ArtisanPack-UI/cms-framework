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

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for uploading a theme ZIP archive.
 *
 * Front-loading these rules into a form request means a malformed upload
 * fails as a `ValidationException` — a 422 carrying an `errors` bag keyed by
 * `theme_zip` — which is the shape Inertia's error bag and pure-API consumers
 * both understand.
 *
 * @since 2.8.0
 */
class UploadThemeRequest extends FormRequest
{
    /**
     * Determines if the user is authorized to make this request.
     *
     * Authorization is handled by the route's `auth:sanctum` middleware; the
     * framework grants no theme-specific ability of its own.
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
            'theme_zip' => [
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
            'theme_zip.required' => __( 'A theme ZIP archive is required.' ),
            'theme_zip.file'     => __( 'The theme upload must be a file.' ),
            'theme_zip.mimes'    => __( 'The theme upload must be a ZIP archive.' ),
            'theme_zip.max'      => __( 'The theme ZIP archive may not be larger than :max kilobytes.' ),
        ];
    }

    /**
     * Gets the upload size ceiling in kilobytes.
     *
     * `cms.themes.maxUploadSize` is expressed in bytes; Laravel's `max` rule
     * for files is expressed in kilobytes.
     *
     * @since 2.8.0
     *
     * @return int The maximum upload size in kilobytes.
     */
    private function maxUploadKilobytes(): int
    {
        return intdiv( (int) config( 'cms.themes.maxUploadSize', 10 * 1024 * 1024 ), 1024 );
    }
}

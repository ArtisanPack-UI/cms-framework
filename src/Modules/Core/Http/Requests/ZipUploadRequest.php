<?php

/**
 * Zip Upload Request
 *
 * Shared base for the plugin- and theme-install form requests, which differ
 * only in their field name, size config key, authorization ability, and the
 * noun used in their validation messages.
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base form request for uploading an extension ZIP archive.
 *
 * Front-loading these rules into a form request means a malformed upload
 * fails as a `ValidationException` — a 422 carrying an `errors` bag keyed by
 * the upload field — which is the shape Inertia's error bag and pure-API
 * consumers both understand.
 *
 * Authorization is deny-by-default: the request is rejected unless the
 * authenticated user holds the module-specific ability. This mirrors the
 * self-updater's deny-by-default `UpdateCapability` gates — an extension
 * install extracts and then executes attacker-supplied PHP, so an
 * under-authorized trigger is total compromise.
 *
 * @since 2.8.0
 */
abstract class ZipUploadRequest extends FormRequest
{
    /**
     * Determines if the user is authorized to make this request.
     *
     * Deny-by-default: only a user holding the module ability may upload.
     * A guest (`null` user) is denied.
     *
     * @since 2.8.0
     *
     * @return bool True if the user is authorized, false otherwise.
     */
    public function authorize(): bool
    {
        return $this->user()?->can( $this->ability() ) ?? false;
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
            $this->fieldName() => [
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
        $field = $this->fieldName();
        $noun  = $this->noun();

        return [
            "{$field}.required" => __( 'A :noun ZIP archive is required.', [ 'noun' => $noun ] ),
            "{$field}.file"     => __( 'The :noun upload must be a file.', [ 'noun' => $noun ] ),
            "{$field}.mimes"    => __( 'The :noun upload must be a ZIP archive.', [ 'noun' => $noun ] ),
            "{$field}.max"      => __( 'The :noun ZIP archive may not be larger than :max kilobytes.', [ 'noun' => $noun ] ),
        ];
    }

    /**
     * The multipart field name carrying the uploaded archive.
     *
     * @since 2.8.0
     *
     * @return string The upload field name.
     */
    abstract protected function fieldName(): string;

    /**
     * The config key (in bytes) capping the upload size.
     *
     * @since 2.8.0
     *
     * @return string The size config key.
     */
    abstract protected function sizeConfigKey(): string;

    /**
     * The Gate ability required to perform this upload.
     *
     * @since 2.8.0
     *
     * @return string The ability name.
     */
    abstract protected function ability(): string;

    /**
     * The lowercase noun used in validation messages (e.g. `plugin`).
     *
     * @since 2.8.0
     *
     * @return string The message noun.
     */
    abstract protected function noun(): string;

    /**
     * Gets the upload size ceiling in kilobytes.
     *
     * The size config key is expressed in bytes; Laravel's `max` rule for
     * files is expressed in kilobytes.
     *
     * @since 2.8.0
     *
     * @return int The maximum upload size in kilobytes.
     */
    protected function maxUploadKilobytes(): int
    {
        return intdiv( (int) config( $this->sizeConfigKey(), 10 * 1024 * 1024 ), 1024 );
    }
}

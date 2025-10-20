<?php

/**
 * Setting Request for the CMS Framework Settings Module.
 *
 * This form request handles validation and authorization for setting-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Settings\Http\Requests
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for setting validation and authorization.
 *
 * Provides validation rules and authorization logic for setting creation
 * and update operations with proper field validation.
 *
 * @since 1.0.0
 */
class SettingRequest extends FormRequest
{
    /**
     * The setting instance.
     * @var Setting|null
     */
    protected ?Setting $setting = null;

    /**
     * Sets the setting for the request.
     *
     * This method allows the setting model to be passed in from contexts
     * like a Livewire component where route model binding isn't automatic.
     *
     * @since 1.0.0
     * @param Setting $setting The setting instance.
     * @return self
     */
    public function setSetting( Setting $setting ): self
    {
        $this->setting = $setting;

        return $this;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @since 1.0.0
     *
     * @return bool True if the user is authorized, false otherwise.
     */
    public function authorize(): bool
    {
        // Authorization is handled by policies, so we return true here
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The validation rules.
     */
    public function rules(): array
    {
        $settingId = $this->setting ? $this->setting->id : null;

        return [
            'key'   => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique( 'settings', 'key' )->ignore( $this->route( 'setting' ), 'key' ),
            ],
            'value' => [
                'required',
                'string',
            ],
            'type'  => [
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The custom error messages.
     */
    public function messages(): array
    {
        return [
            'key.required'   => __( 'The setting key is required.' ),
            'key.regex'      => __( 'The setting key must be lowercase letters, numbers, and hyphens only.' ),
            'key.unique'     => __( 'A setting with this key already exists.' ),
            'value.required' => __( 'The setting value is required.' ),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'key'   => __( 'setting key' ),
            'value' => __( 'setting value' ),
            'type'  => __( 'setting type' ),
        ];
    }
}

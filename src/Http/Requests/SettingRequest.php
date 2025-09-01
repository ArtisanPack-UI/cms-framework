<?php

/**
 * Class SettingRequest
 *
 * Form request for validating setting data.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use ArtisanPackUI\CMSFramework\Http\Utilities\InputSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class SettingRequest
 *
 * Handles validation of incoming requests for creating or updating settings.
 * Defines the validation rules and authorization logic for setting operations.
 *
 * @since 1.0.0
 */
class SettingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Defines comprehensive validation rules for setting attributes with strict security measures.
     * Settings control critical application behavior and require careful validation.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The validation rules.
     */
    public function rules(): array
    {
        $settingId = $this->route('setting') ? $this->route('setting') : null;

        $rules = [
            'key' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z][a-zA-Z0-9._\-]*$/', // Must start with letter, allow letters, numbers, dots, underscores, hyphens
                Rule::unique('settings', 'key')->ignore($settingId),
            ],
            'type' => [
                'required',
                'string',
                'in:string,integer,boolean,json,array,url,email,text,html',
            ],
            'value' => [
                'nullable',
                'string',
                'max:10000', // 10KB max for setting values
            ],
        ];

        // Add conditional validation based on setting type
        $type = $this->input('type');

        if ($type === 'integer') {
            $rules['value'] = ['nullable', 'integer', 'min:-2147483648', 'max:2147483647'];
        } elseif ($type === 'boolean') {
            $rules['value'] = ['nullable', 'boolean'];
        } elseif ($type === 'url') {
            $rules['value'] = ['nullable', 'url', 'max:255'];
        } elseif ($type === 'email') {
            $rules['value'] = ['nullable', 'email:rfc,dns', 'max:254'];
        } elseif ($type === 'json') {
            $rules['value'] = ['nullable', 'json'];
        } elseif ($type === 'array') {
            // For array type, value should be a JSON string that represents an array
            $rules['value'] = ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '') {
                    $decoded = json_decode($value, true);
                    if (! is_array($decoded)) {
                        $fail('The '.$attribute.' must be a valid JSON array.');
                    }
                }
            }];
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     *
     * Sanitizes setting input data before validation based on the setting type.
     * Settings control critical application behavior and require careful sanitization.
     *
     * @since 1.0.0
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // Sanitize key (ensure it follows naming conventions)
        if (isset($input['key'])) {
            $key = InputSanitizer::sanitizeText($input['key'], 100);
            // Ensure key follows proper naming pattern: starts with letter, only safe characters
            $key = preg_replace('/[^a-zA-Z0-9._\-]/', '', $key);
            $key = preg_replace('/^[^a-zA-Z]+/', '', $key); // Remove non-letters from start
            $input['key'] = strtolower($key);
        }

        // Sanitize type
        if (isset($input['type'])) {
            $input['type'] = InputSanitizer::sanitizeText($input['type'], 20);
            $input['type'] = strtolower($input['type']);
        }

        // Sanitize value based on type
        if (isset($input['value']) && isset($input['type'])) {
            $type = $input['type'];
            $value = $input['value'];

            switch ($type) {
                case 'html':
                    // Allow HTML but purify it
                    $input['value'] = InputSanitizer::sanitizeHtml($value);
                    break;

                case 'text':
                    // Plain text only, strip HTML
                    $input['value'] = InputSanitizer::sanitizeText($value);
                    break;

                case 'string':
                    // Basic string sanitization
                    $input['value'] = InputSanitizer::sanitizeText($value, 10000);
                    break;

                case 'url':
                    // URL sanitization
                    $input['value'] = InputSanitizer::sanitizeUrl($value);
                    break;

                case 'email':
                    // Email sanitization
                    $input['value'] = InputSanitizer::sanitizeEmail($value);
                    break;

                case 'integer':
                    // Integer sanitization
                    $input['value'] = InputSanitizer::sanitizeInteger($value);
                    break;

                case 'boolean':
                    // Boolean conversion
                    if (is_string($value)) {
                        $value = strtolower(trim($value));
                        $input['value'] = in_array($value, ['1', 'true', 'yes', 'on'], true);
                    } else {
                        $input['value'] = (bool) $value;
                    }
                    break;

                case 'json':
                case 'array':
                    // JSON/Array sanitization
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            $sanitized = InputSanitizer::sanitizeArray($decoded, 'text');
                            $input['value'] = json_encode($sanitized, JSON_UNESCAPED_UNICODE);
                        }
                    } elseif (is_array($value)) {
                        $sanitized = InputSanitizer::sanitizeArray($value, 'text');
                        $input['value'] = json_encode($sanitized, JSON_UNESCAPED_UNICODE);
                    }
                    break;

                default:
                    // Default to text sanitization for unknown types
                    $input['value'] = InputSanitizer::sanitizeText($value, 10000);
            }
        } elseif (isset($input['value'])) {
            // If no type specified, default to text sanitization
            $input['value'] = InputSanitizer::sanitizeText($input['value'], 10000);
        }

        $this->replace($input);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * This method always returns true as the actual authorization is handled
     * in the controller using policies.
     *
     * @since 1.0.0
     *
     * @return bool Always returns true.
     */
    public function authorize(): bool
    {
        return true;
    }
}

<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use ArtisanPackUI\CMSFramework\Http\Utilities\InputSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user') : null;

        $rules = [
            'email_verified_at' => ['nullable', 'date', 'before_or_equal:now'],
            'role_id' => ['nullable', 'integer', 'min:1', 'exists:roles,id'],
            'first_name' => ['nullable', 'string', 'min:1', 'max:100', 'regex:/^[a-zA-Z\s\-\'\.]+$/'],
            'last_name' => ['nullable', 'string', 'min:1', 'max:100', 'regex:/^[a-zA-Z\s\-\'\.]+$/'],
            'website' => ['nullable', 'string', 'max:255', 'url', 'regex:/^https?:\/\/.+/'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'links' => ['nullable', 'array', 'max:10'],
            'links.*.name' => ['required_with:links', 'string', 'max:100'],
            'links.*.url' => ['required_with:links', 'string', 'max:255', 'url'],
            'settings' => ['nullable', 'array', 'max:50'],
            'settings.*' => ['string', 'max:1000'],
        ];

        // Create vs Update validation
        if ($this->isMethod('POST')) {
            // Creating a new user
            $rules['username'] = [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                'unique:users,username',
            ];
            $rules['email'] = [
                'required',
                'email:rfc,dns',
                'max:254',
                'unique:users,email',
            ];
            $rules['password'] = [
                'required',
                'string',
                'min:8',
                'max:255',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'confirmed',
            ];
            $rules['password_confirmation'] = ['required', 'same:password'];
        } else {
            // Updating existing user
            $rules['username'] = [
                'sometimes',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                Rule::unique('users', 'username')->ignore($userId),
            ];
            $rules['email'] = [
                'sometimes',
                'email:rfc,dns',
                'max:254',
                Rule::unique('users', 'email')->ignore($userId),
            ];
            $rules['password'] = [
                'sometimes',
                'string',
                'min:8',
                'max:255',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'confirmed',
            ];
            $rules['password_confirmation'] = ['required_with:password', 'same:password'];
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     *
     * Sanitizes user input data before validation to prevent XSS attacks and ensure data integrity.
     * Applies appropriate sanitization methods for different field types.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // Sanitize username (alphanumeric, underscore, hyphen only)
        if (isset($input['username'])) {
            $input['username'] = InputSanitizer::sanitizeText($input['username'], 50);
            $input['username'] = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $input['username']));
        }

        // Sanitize email
        if (isset($input['email'])) {
            $input['email'] = InputSanitizer::sanitizeEmail($input['email']);
        }

        // Sanitize names (allow letters, spaces, hyphens, apostrophes, periods)
        if (isset($input['first_name'])) {
            $input['first_name'] = InputSanitizer::sanitizeText($input['first_name'], 100);
            $input['first_name'] = preg_replace('/[^a-zA-Z\s\-\'\.]/u', '', $input['first_name']);
            $input['first_name'] = trim($input['first_name']);
        }

        if (isset($input['last_name'])) {
            $input['last_name'] = InputSanitizer::sanitizeText($input['last_name'], 100);
            $input['last_name'] = preg_replace('/[^a-zA-Z\s\-\'\.]/u', '', $input['last_name']);
            $input['last_name'] = trim($input['last_name']);
        }

        // Sanitize website URL
        if (isset($input['website'])) {
            $input['website'] = InputSanitizer::sanitizeUrl($input['website']);
        }

        // Sanitize bio (allow limited HTML)
        if (isset($input['bio'])) {
            $input['bio'] = InputSanitizer::sanitizeHtmlStrict($input['bio']);
        }

        // Sanitize links array
        if (isset($input['links']) && is_array($input['links'])) {
            $sanitizedLinks = [];
            foreach ($input['links'] as $link) {
                if (is_array($link)) {
                    $sanitizedLink = [];
                    if (isset($link['name'])) {
                        $sanitizedLink['name'] = InputSanitizer::sanitizeText($link['name'], 100);
                    }
                    if (isset($link['url'])) {
                        $sanitizedLink['url'] = InputSanitizer::sanitizeUrl($link['url']);
                    }
                    if (! empty($sanitizedLink['name']) && ! empty($sanitizedLink['url'])) {
                        $sanitizedLinks[] = $sanitizedLink;
                    }
                }
            }
            $input['links'] = $sanitizedLinks;
        }

        // Sanitize settings array
        if (isset($input['settings']) && is_array($input['settings'])) {
            $input['settings'] = InputSanitizer::sanitizeArray($input['settings'], 'text');
        }

        // Sanitize role_id
        if (isset($input['role_id'])) {
            $input['role_id'] = InputSanitizer::sanitizeInteger($input['role_id'], 1);
        }

        // Note: We don't sanitize passwords as they should remain exactly as entered
        // for proper validation and hashing

        $this->replace($input);
    }

    public function authorize(): bool
    {
        return true;
    }
}

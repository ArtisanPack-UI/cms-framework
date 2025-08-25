<?php

/**
 * Media Request
 *
 * Handles validation and authorization for both storing and updating media items.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use ArtisanPackUI\CMSFramework\Http\Utilities\InputSanitizer;
use ArtisanPackUI\CMSFramework\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Class MediaRequest
 *
 * Form request for validating media data for both create and update operations.
 *
 * @since 1.0.0
 */
class MediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @since 1.0.0
     */
    public function authorize(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        // GET requests (show/index) are allowed for any authenticated user.
        if ($this->isMethod('GET')) {
            return true;
        }

        // For POST (store) operations, simply check if the user is authenticated.
        if ($this->isMethod('POST')) {
            return Auth::user()->can('upload_files'); // You might use a specific 'upload_files' permission.
        }

        // For PUT/PATCH/DELETE operations, we need to check permissions.
        if ($this->isMethod('PUT') || $this->isMethod('PATCH') || $this->isMethod('DELETE')) {
            $mediaId = $this->route('media');

            $media = Media::find($mediaId); // Find the Media instance.

            if (! $media) {
                return false; // Media not found.
            }

            // Check if admin with 'edit_files' permission.
            if (Auth::user()->can('edit_files')) {
                return true;
            }

            // Otherwise, regular users can only update/delete their own media.
            return $media->user_id === Auth::id();
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @since 1.0.0
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // For DELETE requests, no request body fields are required.
        if ($this->isMethod('DELETE')) {
            return [];
        }

        $rules = [
            'alt_text' => ['nullable', 'string', 'min:1', 'max:255'],
            'caption' => ['nullable', 'string', 'min:1', 'max:1000'],
            'is_decorative' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array', 'max:20'], // Limit metadata array size
            'metadata.*' => ['string', 'max:1000'], // Limit individual metadata values
            'media_categories' => ['nullable', 'array', 'max:10'], // Reasonable limit
            'media_categories.*' => ['integer', 'min:1', 'exists:media_categories,id'],
            'media_tags' => ['nullable', 'array', 'max:20'], // Reasonable limit
            'media_tags.*' => ['integer', 'min:1', 'exists:media_tags,id'],
        ];

        // Add rules specific to storing (uploading) new media.
        if ($this->isMethod('POST')) {
            $rules['file'] = [
                'required',
                'file',
                'mimes:jpeg,jpg,png,gif,bmp,svg,webp,mp4,mov,avi,webm,mkv,mp3,wav,ogg,m4a,pdf,doc,docx,txt',
                'max:20480', // 20MB max
                'dimensions:max_width=4000,max_height=4000', // Reasonable image size limit
            ];
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     *
     * Sanitizes media input data before validation to prevent XSS attacks and ensure data integrity.
     * Applies HTML purification to caption and sanitization to other fields.
     *
     * @since 1.0.0
     */
    protected function prepareForValidation(): void
    {
        // Skip sanitization for DELETE requests
        if ($this->isMethod('DELETE')) {
            return;
        }

        $input = $this->all();

        // Handle decorative images - clear alt_text if image is decorative
        if ($this->has('is_decorative') && (bool) $this->input('is_decorative') === true) {
            $input['alt_text'] = '';
        } elseif (isset($input['alt_text'])) {
            // Sanitize alt_text (no HTML allowed)
            $input['alt_text'] = InputSanitizer::sanitizeText($input['alt_text'], 255);
        }

        // Sanitize caption with strict HTML purification (allow basic formatting)
        if (isset($input['caption'])) {
            $input['caption'] = InputSanitizer::sanitizeHtmlStrict($input['caption']);
        }

        // Sanitize metadata array
        if (isset($input['metadata']) && is_array($input['metadata'])) {
            $input['metadata'] = InputSanitizer::sanitizeArray($input['metadata'], 'text');

            // Ensure metadata doesn't exceed reasonable limits
            if (count($input['metadata']) > 20) {
                $input['metadata'] = array_slice($input['metadata'], 0, 20, true);
            }
        }

        // Sanitize media categories array (ensure integers)
        if (isset($input['media_categories']) && is_array($input['media_categories'])) {
            $sanitizedCategories = [];
            foreach ($input['media_categories'] as $categoryId) {
                $cleanId = InputSanitizer::sanitizeInteger($categoryId, 1);
                if ($cleanId > 0) {
                    $sanitizedCategories[] = $cleanId;
                }
            }
            $input['media_categories'] = array_unique($sanitizedCategories);

            // Limit number of categories
            if (count($input['media_categories']) > 10) {
                $input['media_categories'] = array_slice($input['media_categories'], 0, 10);
            }
        }

        // Sanitize media tags array (ensure integers)
        if (isset($input['media_tags']) && is_array($input['media_tags'])) {
            $sanitizedTags = [];
            foreach ($input['media_tags'] as $tagId) {
                $cleanId = InputSanitizer::sanitizeInteger($tagId, 1);
                if ($cleanId > 0) {
                    $sanitizedTags[] = $cleanId;
                }
            }
            $input['media_tags'] = array_unique($sanitizedTags);

            // Limit number of tags
            if (count($input['media_tags']) > 20) {
                $input['media_tags'] = array_slice($input['media_tags'], 0, 20);
            }
        }

        // Handle file uploads - additional security will be handled by validation rules
        // We don't sanitize the actual file here as it needs to remain intact for proper processing

        $this->replace($input);
    }
}

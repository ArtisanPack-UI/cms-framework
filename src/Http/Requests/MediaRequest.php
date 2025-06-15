<?php
/**
 * Media Request
 *
 * Handles validation and authorization for both storing and updating media items.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Requests
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Requests;

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
     * @return bool
     */
    public function authorize(): bool
    {
        if ( ! Auth::check() ) {
            return false;
        }

        // GET requests (show/index) are allowed for any authenticated user.
        if ( $this->isMethod( 'GET' ) ) {
            return true;
        }

        // For POST (store) operations, simply check if the user is authenticated.
        if ( $this->isMethod( 'POST' ) ) {
            return Auth::user()->can( 'upload_files' ); // You might use a specific 'upload_files' permission.
        }

        // For PUT/PATCH/DELETE operations, we need to check permissions.
        if ( $this->isMethod( 'PUT' ) || $this->isMethod( 'PATCH' ) || $this->isMethod( 'DELETE' ) ) {
            $mediaId = $this->route( 'media' );

            $media = Media::find( $mediaId ); // Find the Media instance.

            if ( ! $media ) {
                return false; // Media not found.
            }

            // Check if admin with 'edit_files' permission.
            if ( Auth::user()->can( 'edit_files' ) ) {
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // For DELETE requests, no request body fields are required.
        if ( $this->isMethod( 'DELETE' ) ) {
            return [];
        }

        $rules = [
            'alt_text'           => [ 'nullable', 'string', 'max:255' ],
            'caption'            => [ 'nullable', 'string', 'max:1000' ], // <-- Added caption rule, allowing up to 1000 characters.
            'is_decorative'      => [ 'sometimes', 'boolean' ],
            'metadata'           => [ 'nullable', 'array' ],
            'media_categories'   => [ 'nullable', 'array' ],
            'media_categories.*' => [ 'integer', 'exists:media_categories,id' ],
            'media_tags'         => [ 'nullable', 'array' ],
            'media_tags.*'       => [ 'integer', 'exists:media_tags,id' ],
        ];

        // Add rules specific to storing (uploading) new media.
        if ( $this->isMethod( 'POST' ) ) {
            $rules['file'] = [ 'required', 'file', 'mimes:jpeg,png,gif,bmp,svg,webp,mp4,mov,avi,webm,mp3,wav', 'max:20480' ];
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     *
     * @since 1.0.0
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Only generate slug if it's not a DELETE request.
        if ( ! $this->isMethod( 'DELETE' ) && $this->has( 'is_decorative' ) && true === (bool)$this->input( 'is_decorative' ) ) {
            $this->merge( [ 'alt_text' => '' ] );
        }
    }
}

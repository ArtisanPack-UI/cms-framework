<?php
/**
 * Media Request
 *
 * Handles validation and authorization for both storing and updating media items.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Requests
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use ArtisanPackUI\CMSFramework\Models\Media;

// Assuming Media model is here.

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
	 * The media item instance being updated, if applicable.
	 *
	 * @since 1.0.0
	 * @var Media|null
	 */
	protected ?Media $media = null;

	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function authorize(): bool
	{
		// GET requests (show/index) are allowed for any authenticated user.
		if ( $this->isMethod( 'GET' ) ) {
			return true;
		}

		if ( $this->isMethod( 'POST' ) ) {
			return Auth::check();
		}

		// If it's an update operation, we need to check ownership.
		if ( $this->isMethod( 'PUT' ) || $this->isMethod( 'PATCH' ) || $this->isMethod( 'DELETE' ) ) {
			// Assuming your route parameter for media is 'media'.
			$mediaId = $this->route( 'media' );
			if ( $mediaId ) {
				$this->media = Media::find( $mediaId );
			}

			// Deny access if media not found or user is not the owner.
			// You might add additional checks for 'admin' roles here.
			return $this->media && ( $this->media->user_id === Auth::id() || Auth::user()->can( 'edit_files' ) );
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
		$rules = [
			'alt_text'           => [ 'nullable', 'string', 'max:255' ],
			'is_decorative'      => [ 'sometimes', 'boolean' ],
			'metadata'           => [ 'nullable', 'array' ], // Flexible JSON metadata.
			'media_categories'   => [ 'nullable', 'array' ], // Array of category IDs.
			'media_categories.*' => [ 'integer', 'exists:media_categories,id' ], // Each item must be an existing category ID.
			'media_tags'         => [ 'nullable', 'array' ], // Array of tag IDs.
			'media_tags.*'       => [ 'integer', 'exists:media_tags,id' ], // Each item must be an existing tag ID.
		];

		// Add rules specific to storing (uploading) new media.
		if ( $this->isMethod( 'POST' ) ) {
			$rules['file'] = [ 'required', 'file', 'mimes:jpeg,png,gif,bmp,svg,webp,mp4,mov,avi,webm,mp3,wav', 'max:20480' ]; // Max 20MB. Adjust as needed.
		}

		// For update operations, 'file' is not allowed.
		// We don't need explicit 'exclude' rules for update, as it's not in the base rules for `PUT`/`PATCH`.

		return $rules;
	}

	/**
	 * Prepare the data for validation.
	 *
	 * This method can be used to modify the request data before validation.
	 * For example, if 'is_decorative' is true, 'alt_text' should be empty.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function prepareForValidation(): void
	{
		// If 'is_decorative' is provided and true, ensure alt_text is not validated for content.
		// The Media model's mutator will handle setting it to empty for storage.
		if ( $this->has( 'is_decorative' ) && true === (bool) $this->input( 'is_decorative' ) ) {
			// Merge an empty alt_text to prevent validation failures if it's required otherwise,
			// though our rules already make it nullable. This is primarily for consistency with model logic.
			$this->merge( [ 'alt_text' => '' ] );
		}
	}
}
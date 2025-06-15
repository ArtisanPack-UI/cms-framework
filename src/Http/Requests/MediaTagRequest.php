<?php
/**
 * Media Tag Request
 *
 * Handles validation and authorization for both storing and updating media tags.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Requests
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use ArtisanPackUI\CMSFramework\Models\MediaTag;

/**
 * Class MediaTagRequest
 *
 * Form request for validating media tag data.
 *
 * @since 1.0.0
 */
class MediaTagRequest extends FormRequest
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

		// For POST (store) operations, generally check for appropriate permission.
		if ( $this->isMethod( 'POST' ) ) {
			return Auth::user()->can( 'manage_categories' ); // Or Auth::user()->can('create_tags')
		}

		// For PUT/PATCH/DELETE operations, we need to check permissions on the specific tag.
		if ( $this->isMethod( 'PUT' ) || $this->isMethod( 'PATCH' ) || $this->isMethod( 'DELETE' ) ) {
			$tagId = $this->route( 'media_tag' ); // Get the ID from the route parameter.

			// Find the MediaTag instance.
			$mediaTag = MediaTag::find( $tagId );

			if ( ! $mediaTag ) {
				return false; // Tag not found.
			}

			// Check if admin with 'manage_categories' permission.
			if ( Auth::user()->can( 'manage_categories' ) ) { // Or Auth::user()->can('manage_tags')
				return true;
			}

			// If tags are user-owned and regular users can update/delete their own:
			// return $mediaTag->user_id === Auth::id();
			// Otherwise, if only admin can manage tags, you can remove the above line.
		}

		return false; // Default deny for any other unhandled method or scenario.
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

		// Get the resolved model directly from the route parameters if it's already bound.
		// If $this->route('media_tag') is now returning the MediaTag object itself, use its ID.
		// Otherwise, if it's just the ID, use that.
		$routeParam = $this->route( 'media_tag' );
		$ignoreId   = null;

		if ( $routeParam instanceof MediaTag ) { // Check if it's the model object
			$ignoreId = (int) $routeParam->id; // Get ID from the object
		} else if ( is_numeric( $routeParam ) ) { // Check if it's a numeric ID
			$ignoreId = (int) $routeParam; // Cast to int
		}

		return [
			'name' => [ 'required', 'string', 'max:255' ],
			'slug' => [
				'required',
				'string',
				'max:255',
				Rule::unique( 'media_tags', 'slug' )->ignore( $ignoreId ),
			],
		];
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
		if ( ! $this->isMethod( 'DELETE' ) && ! $this->has( 'slug' ) && $this->has( 'name' ) ) {
			$this->merge( [ 'slug' => Str::slug( $this->input( 'name' ) ) ] );
		}
	}
}
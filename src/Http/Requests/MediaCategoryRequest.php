<?php
/**
 * Media Category Request
 *
 * Handles validation and authorization for both storing and updating media categories.
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
use ArtisanPackUI\CMSFramework\Models\MediaCategory;

/**
 * Class MediaCategoryRequest
 *
 * Form request for validating media category data.
 *
 * @since 1.0.0
 */
class MediaCategoryRequest extends FormRequest
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
			return Auth::user()->can( 'manage_categories' ); // Or Auth::user()->can('create_categories')
		}

		// For PUT/PATCH/DELETE operations, we need to check permissions on the specific category.
		if ( $this->isMethod( 'PUT' ) || $this->isMethod( 'PATCH' ) || $this->isMethod( 'DELETE' ) ) {
			$categoryId = $this->route( 'media_category' ); // Get the ID from the route parameter.

			// Find the MediaCategory instance.
			$mediaCategory = MediaCategory::find( $categoryId );

			if ( ! $mediaCategory ) {
				return false; // Category not found.
			}

			// Check if admin with 'manage_categories' permission.
			if ( Auth::user()->can( 'manage_categories' ) ) { // Or Auth::user()->can('manage_categories')
				return true;
			}

			// If categories are user-owned and regular users can update/delete their own:
			// return $mediaCategory->user_id === Auth::id();
			// Otherwise, if only admin can manage categories, you can remove the above line.
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
		$routeParam = $this->route( 'media_category' );
		$ignoreId   = null;

		if ( $routeParam instanceof MediaCategory ) { // Check if it's the model object
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
				Rule::unique( 'media_categories', 'slug' )->ignore( $ignoreId ),
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
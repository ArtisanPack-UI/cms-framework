<?php
/**
 * Store Page Request
 *
 * Handles validation for storing a new website page.
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

/**
 * Validates requests for creating or updating website pages.
 *
 * Ensures that incoming page data meets the required criteria
 * for title, slug, content, and status.
 *
 * @since 1.0.0
 */
class PageRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function authorize(): bool
	{
		// Implement your authorization logic here.
		// For example, check if the authenticated user has permission to create/update pages.
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @since 1.0.0
	 * @return array<string, ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		$page = $this->route( 'page' );
		$pageId = $page ? (is_object($page) ? $page->id : $page) : null;

		return [
			'title'        => [ 'required', 'string', 'max:255' ],
			'slug'         => [ 'required', 'string', 'max:255', 'unique:pages,slug,' . $pageId ],
			'content'      => [ 'nullable', 'string' ],
			'status'       => [ 'required', 'string', 'in:published,draft,pending' ],
			'parent_id'    => [ 'nullable', 'exists:pages,id' ],
			'order'        => [ 'nullable', 'integer' ],
			'published_at' => [ 'nullable', 'date' ],
			'user_id'      => [ 'required', 'exists:users,id' ],
		];
	}

	/**
	 * Get the error messages for the defined validation rules.
	 *
	 * @since 1.0.0
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'slug.unique' => 'A page with this slug already exists.',
		];
	}
}

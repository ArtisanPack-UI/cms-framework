<?php

/**
 * Menu Form Request
 *
 * Validates store/update payloads for `/api/v1/menus`.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @since 1.2.0
 */
class MenuRequest extends FormRequest
{
    /**
     * @since 1.2.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $slugPresence = $this->isMethod( 'post' ) ? 'required' : 'sometimes';

        return [
            'slug'           => [ $slugPresence, 'string', 'max:255', 'regex:' . SlugValidator::PATTERN ],
            'name'           => [ 'required', 'string', 'max:255' ],
            'description'    => [ 'nullable', 'string' ],
            'auto_add_pages' => [ 'nullable', 'boolean' ],
        ];
    }

    /**
     * @since 1.2.0
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => __( 'The menu slug is required.' ),
            'slug.regex'    => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'name.required' => __( 'The menu name is required.' ),
        ];
    }
}

<?php

/**
 * Template Form Request
 *
 * Validates store/update payloads for `/api/v1/templates`.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @since 1.2.0
 */
class TemplateRequest extends FormRequest
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
        // POST identifies the resource by payload — slug required.
        // PUT identifies the resource by route — slug optional, but if present
        // the controller enforces it equals the route slug.
        $slugPresence = $this->isMethod( 'post' ) ? 'required' : 'sometimes';

        return [
            'slug'          => [ $slugPresence, 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/' ],
            'title'         => [ 'required', 'string', 'max:255' ],
            'description'   => [ 'nullable', 'string' ],
            'status'        => [ 'nullable', 'string', 'in:auto-draft,publish,draft' ],
            'is_custom'     => [ 'nullable', 'boolean' ],
            'block_content' => [ 'nullable', 'array' ],
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
            'slug.required'  => __( 'The template slug is required.' ),
            'slug.regex'     => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'title.required' => __( 'The template title is required.' ),
        ];
    }
}

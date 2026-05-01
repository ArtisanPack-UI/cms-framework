<?php

/**
 * Template Part Form Request
 *
 * Validates store/update payloads for `/api/v1/template-parts`.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @since 1.2.0
 */
class TemplatePartRequest extends FormRequest
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
            'slug'          => [ $slugPresence, 'string', 'max:255', 'regex:' . SlugValidator::PATTERN ],
            'title'         => [ 'required', 'string', 'max:255' ],
            'description'   => [ 'nullable', 'string' ],
            'area'          => [ 'required', 'string', Rule::in( TemplatePart::AREAS ) ],
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
            'slug.required'  => __( 'The template part slug is required.' ),
            'slug.regex'     => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'title.required' => __( 'The template part title is required.' ),
            'area.required'  => __( 'The template part area is required.' ),
            'area.in'        => __( 'The area must be one of: header, footer, sidebar, general.' ),
        ];
    }
}

<?php

/**
 * Block Pattern Form Request
 *
 * Shared validation for both pattern REST mountpoints (`/api/v1/blocks` and
 * `/api/v1/block-patterns/patterns`). The `synced` field is required at the
 * controller level (each endpoint pins it to a fixed value), so it is only
 * accepted as an optional override at the request layer.
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
class BlockPatternRequest extends FormRequest
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
        // PUT identifies the resource by route — slug optional.
        $slugPresence = $this->isMethod( 'post' ) ? 'required' : 'sometimes';

        return [
            'slug'          => [ $slugPresence, 'string', 'max:255', 'regex:' . SlugValidator::PATTERN ],
            'title'         => [ 'required', 'string', 'max:255' ],
            'description'   => [ 'nullable', 'string' ],
            'synced'        => [ 'nullable', 'boolean' ],
            'categories'    => [ 'nullable', 'array' ],
            'categories.*'  => [ 'string' ],
            'block_types'   => [ 'nullable', 'array' ],
            'block_types.*' => [ 'string' ],
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
            'slug.required'  => __( 'The pattern slug is required.' ),
            'slug.regex'     => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'title.required' => __( 'The pattern title is required.' ),
        ];
    }
}

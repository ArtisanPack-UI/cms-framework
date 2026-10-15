<?php

/**
 * Block Pattern Form Request
 *
 * Shared validation for both pattern REST mountpoints (`/api/v1/blocks` and
 * `/api/v1/block-patterns/patterns`). The `synced` field is required at the
 * controller level (each endpoint pins it to a fixed value), so it is only
 * accepted as an optional override at the request layer.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\PatternFileParser;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SiteEditorAbilities;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @since 2.0.0
 */
class BlockPatternRequest extends FormRequest
{
    /**
     * Require the Site Editor ability for this request's group. The
     * route middleware checks it too; this keeps the request safe when
     * a host reuses it on its own route.
     *
     * @since 2.0.0
     * @since 2.15.0 Requires the group ability {@see SiteEditorAbilities::allows()} checks instead of allowing every user.
     */
    public function authorize(): bool
    {
        return SiteEditorAbilities::allows( $this->user(), SiteEditorAbilities::CONTENT );
    }

    /**
     * @since 2.0.0
     * @since 2.15.0 Accepts `keywords`, `inserter`, `template_types`, `post_types` and `viewport_width`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // POST identifies the resource by payload — slug required.
        // PUT identifies the resource by route — slug optional.
        $slugPresence = $this->isMethod( 'post' ) ? 'required' : 'sometimes';

        return [
            'slug'          => [$slugPresence, 'string', 'max:255', 'regex:' . SlugValidator::PATTERN],
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'synced'        => ['nullable', 'boolean'],
            'categories'    => ['nullable', 'array'],
            'categories.*'  => ['string'],
            'block_types'   => ['nullable', 'array'],
            'block_types.*' => ['string'],
            'block_content' => ['nullable', 'array'],

            'keywords'         => ['nullable', 'array'],
            'keywords.*'       => ['string', 'max:255'],
            'inserter'         => ['nullable', 'boolean'],
            'template_types'   => ['nullable', 'array'],
            'template_types.*' => ['string', 'max:255'],
            'post_types'       => ['nullable', 'array'],
            'post_types.*'     => ['string', 'max:20', 'regex:/^[a-z0-9_-]+$/'],
            'viewport_width'   => ['nullable', 'integer', 'between:' . PatternFileParser::MIN_VIEWPORT_WIDTH . ',' . PatternFileParser::MAX_VIEWPORT_WIDTH],
        ];
    }

    /**
     * @since 2.0.0
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required'      => __( 'The pattern slug is required.' ),
            'slug.regex'         => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'title.required'     => __( 'The pattern title is required.' ),
            'post_types.*.regex' => __( 'Post types must be lowercase letters, numbers, hyphens or underscores.' ),
        ];
    }
}

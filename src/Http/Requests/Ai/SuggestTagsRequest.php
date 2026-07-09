<?php

/**
 * Form request for the tag suggestion AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class SuggestTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content'          => [ 'required', 'string' ],
            'available_tags'   => [ 'required', 'array' ],
            'available_tags.*' => [ 'string' ],
            'allow_new'        => [ 'nullable', 'boolean' ],
            'max_selected'     => [ 'nullable', 'integer', 'between:1,10' ],
        ];
    }
}

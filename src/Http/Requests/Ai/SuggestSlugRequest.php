<?php

/**
 * Form request for the slug suggestion AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class SuggestSlugRequest extends FormRequest
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
            'title'     => [ 'required', 'string' ],
            'excerpt'   => [ 'nullable', 'string' ],
            'max_chars' => [ 'nullable', 'integer', 'between:20,100' ],
        ];
    }
}

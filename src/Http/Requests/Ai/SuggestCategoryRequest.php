<?php

/**
 * Form request for the category suggestion AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class SuggestCategoryRequest extends FormRequest
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
            'content'       => [ 'required', 'string' ],
            'category_tree' => [ 'required', 'array' ],
        ];
    }
}

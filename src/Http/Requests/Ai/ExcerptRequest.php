<?php

/**
 * Form request for the excerpt AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ExcerptRequest extends FormRequest
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
            'content'   => [ 'required', 'string' ],
            'max_chars' => [ 'nullable', 'integer', 'between:80,400' ],
        ];
    }
}

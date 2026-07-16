<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Cardinality;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\FieldTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DynamicContentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $existing = $this->route( 'type' );
        $isUpdate = $existing instanceof DynamicContentType;

        $fieldTypeSlugs = array_keys( app( FieldTypeRegistry::class )->all() );

        $slugRules = [ 'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/' ];

        if ( $isUpdate ) {
            // Slug is immutable post-create: changing it would silently break every
            // persisted `{{oldslug.field}}` token across the site.
            $slugRules[] = Rule::in( [ $existing->slug ] );
        } else {
            $slugRules[] = Rule::unique( 'dynamic_content_types', 'slug' );
        }

        return [
            'slug'              => $slugRules,
            'name'              => [ 'required', 'string', 'max:255' ],
            'cardinality'       => [ 'required', Rule::in( array_column( Cardinality::cases(), 'value' ) ) ],
            'description'       => [ 'nullable', 'string' ],
            'icon'              => [ 'nullable', 'string', 'max:64' ],
            'fields'            => [ 'array' ],
            'fields.*.slug'     => [ 'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/' ],
            'fields.*.label'    => [ 'required', 'string', 'max:255' ],
            'fields.*.type'     => [ 'required', 'string', Rule::in( $fieldTypeSlugs ) ],
            'fields.*.options'  => [ 'nullable', 'array' ],
            'fields.*.default'  => [ 'nullable' ],
            'fields.*.required' => [ 'nullable', 'boolean' ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.in'          => __( 'Type slug is immutable; changing it would break existing tokens.' ),
            'fields.*.type.in' => __( 'The field type is not registered.' ),
        ];
    }
}

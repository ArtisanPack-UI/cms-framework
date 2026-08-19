<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\FieldTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Type + field builder — drag-to-order fields, per-type-field option panel.
 *
 * @since 2.4.0
 */
class FieldBuilder extends Component
{
    public ?int $typeId = null;

    #[Validate( 'required|string|max:64|regex:/^[a-z][a-z0-9_]*$/' )]
    public string $slug = '';

    #[Validate( 'required|string|max:255' )]
    public string $name = '';

    #[Validate( 'required|in:singleton,collection' )]
    public string $cardinality = 'singleton';

    public ?string $description = null;

    /**
     * @var array<int, array{slug:string, label:string, type:string, required:bool, default:?string, options:array}>
     */
    public array $fields = [];

    public function mount( ?int $typeId = null ): void
    {
        $this->typeId = $typeId;

        if ( null !== $typeId ) {
            $type = DynamicContentType::with( 'fields' )->findOrFail( $typeId );

            Gate::authorize( 'update', $type );

            $this->slug        = $type->slug;
            $this->name        = $type->name;
            $this->cardinality = $type->cardinality->value;
            $this->description = $type->description;
            $this->fields      = $type->fields->map( fn ( $f ) => [
                'slug'     => $f->slug,
                'label'    => $f->label,
                'type'     => $f->type,
                'required' => $f->required,
                'default'  => $f->default_value,
                'options'  => $f->options ?? [],
            ] )->all();
        } else {
            Gate::authorize( 'create', DynamicContentType::class );
        }
    }

    public function addField(): void
    {
        $this->fields[] = [
            'slug'     => '',
            'label'    => '',
            'type'     => 'text',
            'required' => false,
            'default'  => null,
            'options'  => [],
        ];
    }

    public function removeField( int $index ): void
    {
        unset( $this->fields[ $index ] );
        $this->fields = array_values( $this->fields );
    }

    public function moveUp( int $index ): void
    {
        if ( $index <= 0 || ! isset( $this->fields[ $index ] ) ) {
            return;
        }

        [ $this->fields[ $index - 1 ], $this->fields[ $index ] ] = [ $this->fields[ $index ], $this->fields[ $index - 1 ] ];
    }

    public function moveDown( int $index ): void
    {
        if ( ! isset( $this->fields[ $index + 1 ] ) ) {
            return;
        }

        [ $this->fields[ $index ], $this->fields[ $index + 1 ] ] = [ $this->fields[ $index + 1 ], $this->fields[ $index ] ];
    }

    public function save(): void
    {
        // Mirror DynamicContentTypeRequest so the Livewire builder enforces the
        // same shape as the REST endpoint. The `#[Validate]` attributes cover the
        // scalar props; `description` and the nested `fields.*` shape (including
        // the registered-field-type allow-list) are validated explicitly here.
        $fieldTypeSlugs = array_keys( app( FieldTypeRegistry::class )->all() );

        $this->validate();
        $this->validate( [
            'description'       => [ 'nullable', 'string' ],
            'fields'            => [ 'array' ],
            'fields.*.slug'     => [ 'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/' ],
            'fields.*.label'    => [ 'required', 'string', 'max:255' ],
            'fields.*.type'     => [ 'required', 'string', Rule::in( $fieldTypeSlugs ) ],
            'fields.*.options'  => [ 'nullable', 'array' ],
            'fields.*.default'  => [ 'nullable' ],
            'fields.*.required' => [ 'nullable', 'boolean' ],
        ] );

        $data = [
            'slug'        => $this->slug,
            'name'        => $this->name,
            'cardinality' => $this->cardinality,
            'description' => $this->description,
            'fields'      => $this->fields,
        ];

        $manager = app( DynamicContentTypeManager::class );

        if ( null === $this->typeId ) {
            $type         = $manager->create( $data );
            $this->typeId = $type->id;
        } else {
            $type = DynamicContentType::findOrFail( $this->typeId );
            Gate::authorize( 'update', $type );
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $data is fully validated above (slug/name/cardinality plus description and the nested fields.* shape, mirroring DynamicContentTypeRequest) before the manager persists it via Eloquent.
            $manager->update( $type, $data );
        }

        $this->dispatch( 'dynamic-content-type-saved', typeId: $this->typeId );
    }

    public function render(): View
    {
        return view( 'ap-cms-dynamic-content::livewire.field-builder', [
            'fieldTypes' => app( FieldTypeRegistry::class )->all(),
        ] );
    }
}

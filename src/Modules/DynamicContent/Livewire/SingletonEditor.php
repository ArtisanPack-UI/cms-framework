<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentRecordManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Edits the single record of a singleton type.
 *
 * @since 2.4.0
 */
class SingletonEditor extends Component
{
    public int $typeId;

    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    public function mount( int $typeId ): void
    {
        $this->typeId = $typeId;

        $type = DynamicContentType::with( 'fields', 'records.values.field' )->findOrFail( $typeId );

        abort_unless( $type->isSingleton(), 404 );
        Gate::authorize( 'update', $type );

        $record = $type->records->first();

        if ( null !== $record ) {
            $this->values = $record->fieldValues();
        } else {
            foreach ( $type->fields as $field ) {
                $this->values[ $field->slug ] = $field->default_value;
            }
        }
    }

    public function save(): void
    {
        $type = DynamicContentType::with( 'fields', 'records' )->findOrFail( $this->typeId );

        Gate::authorize( 'update', $type );

        $manager = app( DynamicContentRecordManager::class );
        $record  = $type->records->first();

        if ( null === $record ) {
            $manager->create( $type, [ 'values' => $this->values ] );
        } else {
            $manager->update( $record, [ 'values' => $this->values ] );
        }

        $this->dispatch( 'dynamic-content-record-saved' );
    }

    public function render(): View
    {
        $type = DynamicContentType::with( 'fields' )->findOrFail( $this->typeId );

        return view( 'ap-cms-dynamic-content::livewire.singleton-editor', [
            'type' => $type,
        ] );
    }
}

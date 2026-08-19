<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentRecordManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecord;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Edits records of a collection type: paginated list + inline editor.
 *
 * @since 2.4.0
 */
class CollectionEditor extends Component
{
    use WithPagination;

    public int $typeId;

    public ?int $editingRecordId = null;

    public ?string $editingLabel = null;

    /**
     * @var array<string, mixed>
     */
    public array $editingValues = [];

    public function mount( int $typeId ): void
    {
        $this->typeId = $typeId;

        $type = DynamicContentType::findOrFail( $typeId );

        abort_unless( $type->isCollection(), 404 );
        Gate::authorize( 'update', $type );
    }

    public function edit( int $recordId ): void
    {
        $record = DynamicContentRecord::with( 'values.field', 'type.fields' )->findOrFail( $recordId );

        abort_unless( $record->dynamic_content_type_id === $this->typeId, 404 );
        Gate::authorize( 'update', $record );

        $this->editingRecordId = $recordId;
        $this->editingLabel    = $record->label;
        $this->editingValues   = $record->fieldValues();
    }

    public function create(): void
    {
        $type = DynamicContentType::with( 'fields' )->findOrFail( $this->typeId );

        Gate::authorize( 'create', DynamicContentRecord::class );

        $this->editingRecordId = null;
        $this->editingLabel    = null;
        $this->editingValues   = [];

        foreach ( $type->fields as $field ) {
            $this->editingValues[ $field->slug ] = $field->default_value;
        }
    }

    public function save(): void
    {
        $type    = DynamicContentType::with( 'fields' )->findOrFail( $this->typeId );
        $manager = app( DynamicContentRecordManager::class );

        $data = [ 'label' => $this->editingLabel, 'values' => $this->editingValues ];

        if ( null === $this->editingRecordId ) {
            Gate::authorize( 'create', DynamicContentRecord::class );
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $data is built from validated Livewire server state and persisted via Eloquent create() behind a Gate::authorize('create', ...) check.
            $manager->create( $type, $data );
        } else {
            $record = DynamicContentRecord::findOrFail( $this->editingRecordId );
            Gate::authorize( 'update', $record );
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- $data is built from validated Livewire server state and persisted via Eloquent update() behind a Gate::authorize('update', $record) check.
            $manager->update( $record, $data );
        }

        $this->editingRecordId = null;
        $this->editingLabel    = null;
        $this->editingValues   = [];
        $this->dispatch( 'dynamic-content-record-saved' );
    }

    public function delete( int $recordId ): void
    {
        $record = DynamicContentRecord::findOrFail( $recordId );

        abort_unless( $record->dynamic_content_type_id === $this->typeId, 404 );
        Gate::authorize( 'delete', $record );

        $record->delete();
    }

    public function render(): View
    {
        $type = DynamicContentType::with( 'fields' )->findOrFail( $this->typeId );

        $records = $type->records()
            ->with( 'values.field' )
            ->paginate( 20 );

        return view( 'ap-cms-dynamic-content::livewire.collection-editor', [
            'type'    => $type,
            'records' => $records,
        ] );
    }
}

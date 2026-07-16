<div>
    <h2>{{ $type->name }}</h2>

    <button type="button" wire:click="create">{{ __( 'New Record' ) }}</button>

    <table>
        <thead>
            <tr>
                <th>{{ __( 'Label' ) }}</th>
                <th>{{ __( 'Order' ) }}</th>
                <th>{{ __( 'Actions' ) }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ( $records as $record )
                <tr wire:key="record-{{ $record->id }}">
                    <td>{{ $record->label }}</td>
                    <td>{{ $record->order }}</td>
                    <td>
                        <button type="button" wire:click="edit({{ $record->id }})">{{ __( 'Edit' ) }}</button>
                        <button type="button" wire:click="delete({{ $record->id }})">{{ __( 'Delete' ) }}</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $records->links() }}

    @if ( null !== $editingRecordId || ! empty( $editingValues ) )
        <div>
            <h3>{{ null === $editingRecordId ? __( 'New Record' ) : __( 'Edit Record' ) }}</h3>
            <label>{{ __( 'Label' ) }}<input type="text" wire:model="editingLabel" /></label>

            @foreach ( $type->fields as $field )
                <div wire:key="editing-value-{{ $field->slug }}">
                    <label>
                        {{ $field->label }}
                        <input type="text" wire:model="editingValues.{{ $field->slug }}" />
                    </label>
                </div>
            @endforeach

            <button type="button" wire:click="save">{{ __( 'Save' ) }}</button>
        </div>
    @endif
</div>

<div>
    <div>
        <label>{{ __( 'Name' ) }}<input type="text" wire:model="name" /></label>
        <label>{{ __( 'Slug' ) }}<input type="text" wire:model="slug" /></label>
        <label>{{ __( 'Cardinality' ) }}
            <select wire:model="cardinality">
                <option value="singleton">{{ __( 'Singleton' ) }}</option>
                <option value="collection">{{ __( 'Collection' ) }}</option>
            </select>
        </label>
        <label>{{ __( 'Description' ) }}<textarea wire:model="description"></textarea></label>
    </div>

    <h3>{{ __( 'Fields' ) }}</h3>
    @foreach ( $fields as $index => $field )
        <div wire:key="field-{{ $index }}">
            <input type="text" wire:model="fields.{{ $index }}.slug" :placeholder="__( 'Slug' )" />
            <input type="text" wire:model="fields.{{ $index }}.label" :placeholder="__( 'Label' )" />
            <select wire:model="fields.{{ $index }}.type">
                @foreach ( $fieldTypes as $slug => $ft )
                    <option value="{{ $slug }}">{{ $ft->label() }}</option>
                @endforeach
            </select>
            <label><input type="checkbox" wire:model="fields.{{ $index }}.required" /> {{ __( 'Required' ) }}</label>
            <input type="text" wire:model="fields.{{ $index }}.default" :placeholder="__( 'Default' )" />

            <button type="button" wire:click="moveUp({{ $index }})">{{ __( 'Move up' ) }}</button>
            <button type="button" wire:click="moveDown({{ $index }})">{{ __( 'Move down' ) }}</button>
            <button type="button" wire:click="removeField({{ $index }})">{{ __( 'Remove' ) }}</button>
        </div>
    @endforeach

    <button type="button" wire:click="addField">{{ __( 'Add Field' ) }}</button>
    <button type="button" wire:click="save">{{ __( 'Save' ) }}</button>
</div>

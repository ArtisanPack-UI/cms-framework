<div>
    <h2>{{ $type->name }}</h2>

    @foreach ( $type->fields as $field )
        <div wire:key="value-{{ $field->slug }}">
            <label>
                {{ $field->label }}
                @if ( 'rich_text' === $field->type )
                    <textarea wire:model="values.{{ $field->slug }}"></textarea>
                @elseif ( 'number' === $field->type )
                    <input type="number" wire:model="values.{{ $field->slug }}" />
                @elseif ( 'email' === $field->type )
                    <input type="email" wire:model="values.{{ $field->slug }}" />
                @elseif ( 'url' === $field->type )
                    <input type="url" wire:model="values.{{ $field->slug }}" />
                @elseif ( 'date' === $field->type )
                    <input type="date" wire:model="values.{{ $field->slug }}" />
                @elseif ( 'datetime' === $field->type )
                    <input type="datetime-local" wire:model="values.{{ $field->slug }}" />
                @else
                    <input type="text" wire:model="values.{{ $field->slug }}" />
                @endif
            </label>
        </div>
    @endforeach

    <button type="button" wire:click="save">{{ __( 'Save' ) }}</button>
</div>

<div>
    <table>
        <thead>
            <tr>
                <th>{{ __( 'Name' ) }}</th>
                <th>{{ __( 'Slug' ) }}</th>
                <th>{{ __( 'Cardinality' ) }}</th>
                <th>{{ __( 'Source' ) }}</th>
                <th>{{ __( 'Actions' ) }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ( $types as $slug => $type )
                <tr wire:key="type-{{ $slug }}">
                    <td>{{ $type['name'] }}</td>
                    <td><code>{{ $slug }}</code></td>
                    <td>{{ $type['cardinality']->value }}</td>
                    <td>{{ $type['source']->value }}</td>
                    <td>
                        @if ( 'db' === $type['source']->value && isset( $type['db_id'] ) )
                            <button type="button" wire:click="delete({{ $type['db_id'] }})">{{ __( 'Delete' ) }}</button>
                        @else
                            <span title="{{ __( 'Code-registered types are read-only' ) }}">{{ __( 'Read-only' ) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

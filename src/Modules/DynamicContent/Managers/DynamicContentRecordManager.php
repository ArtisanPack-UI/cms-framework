<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecord;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Support\Facades\DB;

/**
 * Writes / reads dynamic content records.
 *
 * @since 2.4.0
 */
class DynamicContentRecordManager
{
    /**
     * @param  array{label?:?string, order?:int, values?:array<string,mixed>}  $data
     */
    public function create( DynamicContentType $type, array $data ): DynamicContentRecord
    {
        return DB::transaction( function () use ( $type, $data ): DynamicContentRecord {
            $record = $type->records()->create( [
                'label' => $data['label'] ?? null,
                'order' => $data['order'] ?? 0,
            ] );

            $this->syncValues( $record, $type, $data['values'] ?? [] );

            return $record->fresh( 'values.field' );
        } );
    }

    /**
     * @param  array{label?:?string, order?:int, values?:array<string,mixed>}  $data
     */
    public function update( DynamicContentRecord $record, array $data ): DynamicContentRecord
    {
        return DB::transaction( function () use ( $record, $data ): DynamicContentRecord {
            $record->update( [
                'label' => $data['label'] ?? $record->label,
                'order' => $data['order'] ?? $record->order,
            ] );

            if ( array_key_exists( 'values', $data ) ) {
                $this->syncValues( $record, $record->type, $data['values'] );
            }

            return $record->fresh( 'values.field' );
        } );
    }

    public function delete( DynamicContentRecord $record ): void
    {
        $record->delete();
    }

    /**
     * @param  array<string, mixed>  $values  Values keyed by field slug.
     */
    protected function syncValues( DynamicContentRecord $record, DynamicContentType $type, array $values ): void
    {
        $type->loadMissing( 'fields' );

        foreach ( $type->fields as $field ) {
            if ( ! array_key_exists( $field->slug, $values ) ) {
                continue;
            }

            $record->values()->updateOrCreate(
                [ 'dynamic_content_field_id' => $field->id ],
                [ 'value' => $values[ $field->slug ] ],
            );
        }
    }
}

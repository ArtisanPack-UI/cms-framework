<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $dynamic_content_type_id
 * @property string|null $label
 * @property int $order
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 2.4.0
 */
class DynamicContentRecord extends Model
{
    use HasFactory;

    protected $table = 'dynamic_content_records';

    protected $fillable = [
        'dynamic_content_type_id',
        'label',
        'order',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo( DynamicContentType::class, 'dynamic_content_type_id' );
    }

    public function values(): HasMany
    {
        return $this->hasMany( DynamicContentRecordValue::class, 'dynamic_content_record_id' );
    }

    /**
     * Return the record's values keyed by field slug.
     *
     * @return array<string, mixed>
     */
    public function fieldValues(): array
    {
        $this->loadMissing( 'values.field', 'type.fields' );

        $out = [];

        foreach ( $this->type->fields as $field ) {
            $out[ $field->slug ] = $field->default_value;
        }

        foreach ( $this->values as $value ) {
            if ( null !== $value->field ) {
                $out[ $value->field->slug ] = $value->value;
            }
        }

        return $out;
    }

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }
}

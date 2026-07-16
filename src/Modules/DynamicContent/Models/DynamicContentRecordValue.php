<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Casts\RecordValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dynamic_content_record_id
 * @property int $dynamic_content_field_id
 * @property mixed $value
 *
 * @since 2.4.0
 */
class DynamicContentRecordValue extends Model
{
    use HasFactory;

    protected $table = 'dynamic_content_record_values';

    protected $fillable = [
        'dynamic_content_record_id',
        'dynamic_content_field_id',
        'value',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo( DynamicContentRecord::class, 'dynamic_content_record_id' );
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo( DynamicContentField::class, 'dynamic_content_field_id' );
    }

    protected function casts(): array
    {
        return [
            'value' => RecordValueCast::class,
        ];
    }
}

<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dynamic_content_type_id
 * @property string $slug
 * @property string $label
 * @property string $type
 * @property array|null $options
 * @property string|null $default_value
 * @property bool $required
 * @property int $order
 *
 * @since 2.4.0
 */
class DynamicContentField extends Model
{
    use HasFactory;

    protected $table = 'dynamic_content_fields';

    protected $fillable = [
        'dynamic_content_type_id',
        'slug',
        'label',
        'type',
        'options',
        'default_value',
        'required',
        'order',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo( DynamicContentType::class, 'dynamic_content_type_id' );
    }

    protected function casts(): array
    {
        return [
            'options'  => 'array',
            'required' => 'boolean',
            'order'    => 'integer',
        ];
    }
}

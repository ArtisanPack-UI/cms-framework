<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Cardinality;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Source;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property Cardinality $cardinality
 * @property Source $source
 * @property string|null $description
 * @property string|null $icon
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 2.4.0
 */
class DynamicContentType extends Model
{
    use HasFactory;

    protected $table = 'dynamic_content_types';

    protected $fillable = [
        'slug',
        'name',
        'cardinality',
        'source',
        'description',
        'icon',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany( DynamicContentField::class, 'dynamic_content_type_id' )->orderBy( 'order' );
    }

    public function records(): HasMany
    {
        return $this->hasMany( DynamicContentRecord::class, 'dynamic_content_type_id' )->orderBy( 'order' );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isSingleton(): bool
    {
        return Cardinality::Singleton === $this->cardinality;
    }

    public function isCollection(): bool
    {
        return Cardinality::Collection === $this->cardinality;
    }

    protected function casts(): array
    {
        return [
            'cardinality' => Cardinality::class,
            'source'      => Source::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Models;

use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setting Model
 *
 * Represents a key-value setting stored in the database.
 * Handles automatic type casting for retrieval and storage.
 *
 * @since 1.0.0
 *
 * @property string $key The unique key for the setting.
 * @property mixed $value The value of the setting (casted).
 * @property SettingType $type The stored type identifier.
 */
class Setting extends Model
{
    use HasFactory;

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * The primary key associated with the table.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $primaryKey = 'key';

    /**
     * The data type of the auto-incrementing ID.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get and set the value attribute, handling type casting.
     *
     * @since 1.0.0
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $type = SettingType::tryFrom($this->attributes['type'] ?? 'string') ?? SettingType::String;

                return $type->cast($value);
            },
            set: function ($value) {
                $type = SettingType::fromValue($value);

                return [
                    'type'  => $type->value,
                    'value' => $type->serialize($value),
                ];
            },
        );
    }
}

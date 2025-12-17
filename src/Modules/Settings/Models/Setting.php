<?php

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setting Model
 *
 * Represents a key-value setting stored in the database.
 * Handles automatic type casting for retrieval and storage.
 *
 * @since      2.0.0
 *
 * @property string $key The unique key for the setting.
 * @property mixed $value The value of the setting (casted).
 * @property string $type The stored type identifier ('string', 'integer', 'boolean', 'json', 'float').
 */
class Setting extends Model
{
    use HasFactory;

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @since 2.0.0
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @since 2.0.0
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
     * @since 2.0.0
     *
     * @var string
     */
    protected $primaryKey = 'key';

    /**
     * The data type of the auto-incrementing ID.
     *
     * @since 2.0.0
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get and set the value attribute, handling type casting.
     *
     * @since 2.0.0
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // Getter: cast stored value using the saved type (default to string)
                $type = $this->attributes['type'] ?? 'string';

                return $this->castValue($value, $type);
            },
            set: function ($value) {
                // Determine type based on PHP value
                $type = match (true) {
                    is_bool($value) => 'boolean',
                    is_int($value) => 'integer',
                    is_float($value) => 'float',
                    is_array($value), is_object($value) => 'json',
                    default => 'string', // Includes null
                };

                // Prepare the value for storage based on determined type
                $stored = match ($type) {
                    'boolean' => $value ? '1' : '0',
                    'integer', 'float' => (string) $value, // Store numerics as strings
                    'json' => json_encode($value),
                    default => is_null($value) ? '' : (string) $value, // Store null as empty string for string type
                };

                // IMPORTANT: Return an attribute array so both fields are persisted
                return [
                    'type' => $type,
                    'value' => $stored,
                ];
            }
        );
    }

    /**
     * Casts a raw value based on the determined type.
     */
    protected function castValue(mixed $value, string $type): mixed
    {
        // Handle actual NULL from DB first
        if (is_null($value)) {
            // Return null for non-string types if DB was NULL
            return ($type === 'string') ? '' : null;
        }

        // --- Simplified Casting ---
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN), // Robust boolean check
            'integer' => (int) $value,
            'float' => (float) $value,
            'json', 'array' => json_decode($value, true), // Decode JSON/Array
            default => (string) $value,                      // Includes 'string'
        };
    }
}

<?php
/**
 * Setting Model
 *
 * Represents a setting in the application.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Models
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class for managing application settings in the database.
 *
 * Represents a configuration setting in the application.
 * Settings are used to store and retrieve configuration values that can be
 * managed through the application's interface.
 *
 * @since 1.0.0
 *
 * @property int    $id         The unique identifier for the setting.
 * @property string $key        The unique key for the setting.
 * @property string $value      The value of the setting.
 * @property string $type       The data type of the setting (string, boolean, integer, json).
 * @property string $created_at The timestamp when the setting was created.
 * @property string $updated_at The timestamp when the setting was last updated.
 */
class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable
     *
     * @since 1.0.0
     * @var array
     */
    protected $fillable = [
        'key',
        'value', // Renamed from 'option'
        'type',
    ];

    /**
     * The attributes that should be cast to specific types
     *
     * @since 1.0.0
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the value attribute with proper type casting
     *
     * Accessor for the 'value' attribute that casts it based on the 'type' column.
     *
     * @since 1.0.0
     * @param string $value The raw value from the database
     * @return mixed The value cast to its appropriate PHP type
     */
    public function getValueAttribute( string $value ): mixed
    {
        switch ( $this->attributes['type'] ) { // Access original attribute for type
            case 'boolean':
                return (bool)$value;
            case 'integer':
                return (int)$value;
            case 'json': // Changed 'array' to 'json' for consistency with common usage
                return json_decode( $value, true );
            default: // 'string' or any other type
                return $value;
        }
    }

    /**
     * Set the value attribute with proper serialization
     *
     * Mutator for the 'value' attribute to store it as a string, handling arrays/objects.
     * The 'type' attribute should ideally be explicitly set when saving complex values via the manager.
     *
     * @since 1.0.0
     * @param mixed $value The value to be stored
     * @return void
     */
    public function setValueAttribute( mixed $value ): void
    {
        if ( is_array( $value ) || is_object( $value ) ) {
            $this->attributes['value'] = json_encode( $value );
            // Optionally, you could auto-set type here if not explicitly set elsewhere:
            // if (!isset($this->attributes['type']) || $this->attributes['type'] !== 'json') $this->attributes['type'] = 'json';
        } else {
            $this->attributes['value'] = (string)$value;
        }
    }
}

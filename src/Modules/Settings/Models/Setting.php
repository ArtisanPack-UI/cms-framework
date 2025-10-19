<?php

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Setting extends Model
{
	public $incrementing = false;

	// Set the primary key to 'key' and make it non-incrementing
	protected $table = 'settings';
	protected $primaryKey = 'key';
	protected $keyType = 'string';

	protected $fillable = [
		'key',
		'value',
		'type',
	];

	/**
	 * Define the cast for the 'value' attribute based on the 'type' column.
	 * This ensures data is correctly typed when retrieved or set.
	 */
	protected function value(): Attribute
	{
		return Attribute::make(
			get: function ( $value ) {
				return $this->castValue( $value, $this->type );
			},
			set: function ( $value ) {
				// When setting, serialize the value based on its PHP type
				if ( is_bool( $value ) ) {
					$this->attributes['type'] = 'boolean';
					return $value ? '1' : '0';
				}
				if ( is_int( $value ) ) {
					$this->attributes['type'] = 'integer';
					return $value;
				}
				if ( is_float( $value ) ) {
					$this->attributes['type'] = 'float';
					return $value;
				}
				if ( is_array( $value ) || is_object( $value ) ) {
					$this->attributes['type'] = 'json';
					return json_encode( $value );
				}

				$this->attributes['type'] = 'string';
				return (string) $value;
			}
		);
	}

	/**
	 * Helper to cast the value on retrieval.
	 */
	protected function castValue( $value, $type )
	{
		if ( is_null( $value ) ) {
			return null;
		}

		return match ( $type ) {
			'boolean' => filter_var( $value, FILTER_VALIDATE_BOOLEAN ),
			'integer' => (int) $value,
			'float' => (float) $value,
			'json' => json_decode( $value, true ), // Decode as associative array
			'array' => json_decode( $value, true ),
			default => (string) $value,
		};
	}
}
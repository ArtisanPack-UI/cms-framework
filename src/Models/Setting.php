<?php
/**
 * Class Setting
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
 * Class Setting
 *
 * The Setting model represents a configuration setting in the application.
 * Settings are used to store and retrieve configuration values that can be
 * managed through the application's interface.
 *
 * @since 1.0.0
 *
 * @property int    $id         The unique identifier for the setting.
 * @property string $name       The name of the setting.
 * @property string $value      The value of the setting.
 * @property string $category   The category the setting belongs to.
 * @property string $created_at The timestamp when the setting was created.
 * @property string $updated_at The timestamp when the setting was last updated.
 */
class Setting extends Model
{
	/**
	 * The HasFactory trait allows the model to use factories for testing.
	 */
	use HasFactory;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	protected $fillable = [
		'name',
		'value',
		'category',
	];
}

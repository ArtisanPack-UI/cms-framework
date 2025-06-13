<?php

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use ArtisanPackUI\CMSFramework\Features\Plugins\Plugin as BasePlugin;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Plugin extends Model
{
	use HasFactory;

	protected $table = 'plugins';

	protected $fillable = [
		'slug',
		'composer_package_name',
		'directory_name',
		'plugin_class',
		'version',
		'is_active',
		'config',
	];

	protected $casts = [
		'is_active' => 'boolean',
		'config'    => 'array',
	];

	protected static function newFactory(): Factory
	{
		return PluginFactory::new();
	}

	/**
	 * Accessor to get an instantiated object of the plugin's main class (Plugin.php).
	 * This allows you to access plugin metadata and methods dynamically.
	 *
	 * @return BasePlugin
	 */
	public function getInstanceAttribute(): BasePlugin
	{
		// Cache the instance to avoid repeated instantiation
		static $instances = [];

		if ( ! isset( $instances[ $this->id ] ) ) {
			if ( ! class_exists( $this->plugin_class ) ) {
				throw new RuntimeException( "Plugin class '{$this->plugin_class}' not found for plugin '{$this->slug}'. Ensure Composer autoload is up-to-date." );
			}
			// Resolve from container to allow for dependency injection in plugin constructors
			$plugin = app( $this->plugin_class );
			// Inject internal plugin model data into the instantiated plugin object
			$plugin->composerPackageName = $this->composer_package_name;
			$plugin->directoryName       = $this->directory_name;
			$plugin->pluginClass         = $this->plugin_class;
			$plugin->isActive            = $this->is_active;

			$instances[ $this->id ] = $plugin;
		}

		return $instances[ $this->id ];
	}
}

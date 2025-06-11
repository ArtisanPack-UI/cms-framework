<?php
/**
 * CMSManager class
 *
 * CMSManager acts as a dynamic dispatcher for feature-specific managers,
 * providing a mechanism to handle method calls that are routed through
 * either instance or static invocation. It relies on a registry of
 * feature managers to map method calls to their respective implementations.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\CMSManager
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework;

// phpcs:disable
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;
use ArtisanPackUI\CMSFramework\Features\Users\UsersManager;
use BadMethodCallException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

// phpcs:enable

/**
 * CMSManager class
 *
 * CMSManager acts as a dynamic dispatcher for feature-specific managers,
 * providing a mechanism to handle method calls that are routed through
 * either instance or static invocation. It relies on a registry of
 * feature managers to map method calls to their respective implementations.
 *
 * @since 1.0.0
 */
class CMSManager
{
	/**
	 * Registry of feature managers.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected array $featureManagers = [
		'settings' => SettingsManager::class,
		'users'    => UsersManager::class,
	];

	/**
	 * Dynamically handles static method calls to the class.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method     The name of the method being called.
	 * @param array  $parameters The parameters passed to the method.
	 * @return mixed The result from the resolved feature manager or delegated method.
	 */
	public static function __CALLSTATIC( string $method, array $parameters ): mixed
	{
		return ( new static() )->$method( ...$parameters );
	}

	/**
	 * Dynamically handles method calls to the class. Attempts to resolve
	 * the method to a registered feature manager or delegates to a feature
	 * manager if a prefixed method is detected. Throws an exception if no
	 * matching method or feature manager is found.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method     The name of the method being called.
	 * @param array  $parameters The parameters passed to the method.
	 * @return mixed The result from the resolved feature manager or delegated method.
	 *
	 * @throws BadMethodCallException If the method does not exist.
	 */
	public function __CALL( string $method, array $parameters ): mixed
	{
		$featureName = Str::camel( $method ); // Convert snake_case or kebab-case to camelCase

		// Check if a feature manager with this name is registered
		if ( isset( $this->featureManagers[ $featureName ] ) ) {
			// Resolve the feature manager from the service container
			return App::make( $this->featureManagers[ $featureName ] );
		}

		// Check for specific methods that might prefix a feature name (e.g., postsGetLatest)
		foreach ( $this->featureManagers as $key => $managerClass ) {
			$prefix = Str::camel( $key );
			if ( Str::startsWith( $method, $prefix ) && strlen( $method ) > strlen( $prefix ) ) {
				$actualMethod    = lcfirst( substr( $method, strlen( $prefix ) ) ); // e.g., 'getLatest' from 'postsGetLatest'
				$managerInstance = App::make( $managerClass );
				if ( method_exists( $managerInstance, $actualMethod ) ) {
					return call_user_func_array( [ $managerInstance, $actualMethod ], $parameters );
				}
			}
		}

		throw new BadMethodCallException( sprintf(
			'Call to undefined method %s::%s()',
			static::class,
			$method
		) );
	}
}

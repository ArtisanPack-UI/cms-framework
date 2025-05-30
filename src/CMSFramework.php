<?php
/**
 * The CMSFramework class serves as the core framework for managing application modules.
 *
 * It categorizes modules into admin, public, and auth modules and provides
 * initialization routines for each specific type. The class also integrates
 * with the Eventy event system for extending functionality via hooks.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\CMSFramework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework;

use ArtisanPackUI\CMSFramework\Settings\Settings;
use ArtisanPackUI\CMSFramework\Util\Functions;
use ArtisanPackUI\CMSFramework\Util\Interfaces\AdminModule;
use ArtisanPackUI\CMSFramework\Util\Interfaces\AuthModule;
use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;
use ArtisanPackUI\CMSFramework\Util\Interfaces\PublicModule;
use TorMorten\Eventy\Facades\Eventy;

/**
 * The CMSFramework class functions as the central framework for managing and interacting
 * with various modules of the Content Management System (CMS).
 *
 * The class provides mechanisms to register, categorize, and initialize modules based on their
 * type, including admin, public, and auth-specific modules. It also integrates with the Eventy
 * hook system to extend functionality by allowing hooks to be added at different stages.
 *
 * The class makes use of the Functions utility for extending module functionality and ensures proper
 * initialization routines for all registered modules.
 *
 * @since 1.0.0
 */
class CMSFramework
{
	/**
	 * The array of modules registered with the CMSFramework.
	 *
	 * @since 1.0.0
	 * @var array $modules The array of registered modules.
	 */
	protected array $modules = [];

	/**
	 * The array of admin modules registered with the CMSFramework.
	 *
	 * @since 1.0.0
	 * @var array $adminModules The array of admin modules.
	 */
	protected array $adminModules = [];

	/**
	 * The array of public modules registered with the CMSFramework.
	 *
	 * @since 1.0.0
	 * @var array $publicModules The array of public modules.
	 */
	protected array $publicModules = [];

	/**
	 * The array of auth modules registered with the CMSFramework.
	 *
	 * @since 1.0.0
	 * @var array $authModules The array of auth modules.
	 */
	protected array $authModules = [];

	/**
	 * The Functions utility instance.
	 *
	 * @since 1.0.0
	 * @var Functions $functions The Functions utility instance.
	 */
	protected Functions $functions;

	/**
	 * The constructor for the CMSFramework class.
	 *
	 * Registers all modules with the CMSFramework and initializes them.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 */
	public function __construct()
	{
		$modules = $this->getModules();

		foreach ( $modules as $module ) {
			if ( $module instanceof Module ) {
				$this->modules[ $module->getSlug() ] = $module;
			}

			if ( $module instanceof AdminModule ) {
				$this->adminModules[ $module->getSlug() ] = $module;
			}

			if ( $module instanceof PublicModule ) {
				$this->publicModules[ $module->getSlug() ] = $module;
			}

			if ( $module instanceof AuthModule ) {
				$this->authModules[ $module->getSlug() ] = $module;
			}
		}

		$this->functions = new Functions( $modules );

		$this->init();
	}

	/**
	 * Returns an array of modules to register with the CMSFramework.
	 *
	 * This method is used to allow for customization of the modules to register
	 * with the CMSFramework.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @return array List of modules to register.
	 */
	public function getModules(): array
	{
		$modules = [
			new Settings(),
		];
		return Eventy::filter( 'ap.modules.list', $modules );
	}

	/**
	 * Initializes all registered modules.
	 *
	 * This method is used to initialize all registered modules. It calls the
	 * init() method on each module and triggers the ap.init event.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 */
	public function init(): void
	{
		array_walk(
			$this->modules,
			function ( Module $module ) {
				$module->init();
			}
		);

		Eventy::action( 'ap.init' );
	}

	/**
	 * Initializes all admin modules.
	 *
	 * This method is used to initialize all admin modules. It calls the
	 * adminInit() method on each module and triggers the ap.admin.init event.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 */
	public function adminInit(): void
	{
		array_walk(
			$this->adminModules,
			function ( AdminModule $module ) {
				$module->adminInit();
			}
		);

		Eventy::action( 'ap.admin.init' );
	}

	/**
	 * Initializes all public modules.
	 *
	 * This method is used to initialize all public modules. It calls the
	 * publicInit() method on each module and triggers the ap.public.init event.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 */
	public function publicInit(): void
	{
		array_walk(
			$this->publicModules,
			function ( PublicModule $module ) {
				$module->publicInit();
			}
		);

		Eventy::action( 'ap.public.init' );
	}

	/**
	 * Initializes all auth modules.
	 *
	 * This method is used to initialize all auth modules. It calls the
	 * authInit() method on each module and triggers the ap.auth.init event.
	 *
	 * @since 1.0.0
	 *
	 * @see   CMSFramework
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 */
	public function authInit(): void
	{
		array_walk(
			$this->authModules,
			function ( AuthModule $module ) {
				$module->authInit();
			}
		);

		Eventy::action( 'ap.auth.init' );
	}

	/**
	 * Returns the Functions utility instance.
	 *
	 * This method returns the Functions utility instance, which includes an array of functions that have been
	 * registered with the CMSFramework to be used throughout the application.
	 *
	 * @since 1.0.0
	 *
	 * @see   Functions
	 * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
	 *
	 * @return Functions The Functions utility instance.
	 */
	public function functions(): Functions
	{
		return $this->functions;
	}
}

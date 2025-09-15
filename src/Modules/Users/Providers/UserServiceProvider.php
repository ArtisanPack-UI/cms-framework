<?php

namespace ArtisanPackUI\CMSFramework\Modules\Users\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\RoleManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;

class UserServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		$this->app->singleton(RoleManager::class, fn() => new RoleManager());
		$this->app->singleton(PermissionManager::class, fn() => new PermissionManager());
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		$this->loadMigrationsFrom(__DIR__ . '/../../../../database/migrations');

		Route::prefix('api/v1')
			->middleware('api')
			->group(__DIR__ . '/../routes/api.php');
	}
}
<?php

namespace ArtisanPackUI\CMSFramework;

use Illuminate\Support\ServiceProvider;

class CMSFrameworkServiceProvider extends ServiceProvider
{

	public function register(): void
	{
		$this->app->singleton( 'cmsframework', function ( $app ) {
			return new CMSFramework();
		} );
	}

	public function boot(): void
	{
		new CMSFramework();
	}
}

<?php

use ArtisanPackUI\CMSFramework\CMSManager;

if ( !function_exists( 'cmsFramework' ) ) {

	function cmsFramework()
	{
		return app( CMSManager::class );
	}
}


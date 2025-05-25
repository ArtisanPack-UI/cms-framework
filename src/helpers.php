<?php

use ArtisanPackUI\CMSFramework\CMSFramework;

if ( !function_exists( 'cmsframework' ) ) {
	/**
	 * Get the Eventy instance.
	 *
	 * @return CMSFramework
	 */
	function cmsframework()
	{
		return app( 'cmsframework' );
	}
}


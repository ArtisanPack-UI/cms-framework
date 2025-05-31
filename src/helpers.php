<?php

use ArtisanPackUI\CMSFramework\CMSFramework;

if ( !function_exists( 'cmsFramework' ) ) {

	function cmsFramework()
	{
        global $cmsFramework;

        if ( is_null( $cmsFramework ) ) {
            $cmsFramework = new CMSFramework();
        }

        return $cmsFramework->functions();
	}
}


<?php

it( 'can register settings', function () {
	cmsFramework()->registerSetting( 'test-setting', 'test-value', 'sanitizeText' );

	$this->assertEquals( 'test-value', cmsFramework()->getSetting( 'test-setting' ) );
} );

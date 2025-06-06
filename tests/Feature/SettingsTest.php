<?php
it( 'can register settings', function () {
	cmsFramework()->settingsRegisterSetting( 'test-setting', 'test-value', 'sanitizeText' );

	$this->assertEquals( 'test-value', cmsFramework()->settingsGetSetting( 'test-setting' ) );
} );

it( 'can update settings', function () {
	cmsFramework()->settingsRegisterSetting( 'test-setting', 'test-value', 'sanitizeText' );
	$this->assertNotFalse( cmsFramework()->settingsUpdateSetting( 'test-setting', 'test-value-2' ) );
	$this->assertEquals( 'test-value-2', cmsFramework()->settingsGetSetting( 'test-setting' ) );
} );

it( 'can delete settings', function () {
	cmsFramework()->settingsRegisterSetting( 'test-setting', 'test-value', 'sanitizeText' );
	cmsFramework()->settingsDeleteSetting( 'test-setting' );
	$this->assertEquals( false, cmsFramework()->settingsGetSetting( 'test-setting' ) );
} );

<?php

it( 'can register settings', function () {
    cmsFramework()->registerSetting( 'test-setting', 'test-value', 'sanitizeText' );

    $this->assertEquals( 'test-value', cmsFramework()->getSetting( 'test-setting' ) );
} );

it( 'can update settings', function () {
    cmsFramework()->registerSetting( 'test-setting', 'test-value', 'sanitizeText' );
    $this->assertNotFalse( cmsFramework()->updateSetting( 'test-setting', 'test-value-2' ) );
    $this->assertEquals( 'test-value-2', cmsFramework()->getSetting( 'test-setting' ) );
} );

it( 'can delete settings', function () {
    cmsFramework()->registerSetting( 'test-setting', 'test-value', 'sanitizeText' );
    cmsFramework()->deleteSetting( 'test-setting' );
    $this->assertEquals( false, cmsFramework()->getSetting( 'test-setting' ) );
} );

<?php

test('example feature test', function () {
    expect(true)->toBe(true);
});

test('service provider is loaded', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey('ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider');
});

test('application has basic configuration', function () {
    expect(config('app.key'))->not()->toBeNull();
    expect(config('database.default'))->toBe('testing');
});

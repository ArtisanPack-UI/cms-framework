<?php

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\AssetManager;

beforeEach(function () {
    // Ensure a fresh Hooks state per test by binding new manager instances
    $this->app->instance(\ArtisanPackUI\Hooks\Filter::class, new \ArtisanPackUI\Hooks\Filter($this->app));
    $this->app->instance(\ArtisanPackUI\Hooks\Action::class, new \ArtisanPackUI\Hooks\Action($this->app));
});

/**
 * ADMIN HELPERS
 */
test('admin helpers: enqueue and retrieve assets via helpers', function () {
    apAdminEnqueueAsset('admin-js', '/admin/app.js', true);
    apAdminEnqueueAsset('admin-css', '/admin/app.css', false);

    $result = apAdminAssets();

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['admin-js', 'admin-css']);
    expect($result['admin-js'])
        ->toMatchArray([
            'path' => '/admin/app.js',
            'inFooter' => true,
        ]);
    expect($result['admin-css']['path'])->toBe('/admin/app.css');
});

/**
 * PUBLIC HELPERS
 */
test('public helpers: enqueue using helper and retrieve via manager', function () {
    apPublicEnqueueAsset('public-js', '/public/site.js', true);

    $result = app(AssetManager::class)->publicAssets();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('public-js');
    expect($result['public-js']['path'])->toBe('/public/site.js');
    expect($result['public-js']['inFooter'])->toBeTrue();
});

/**
 * AUTH HELPERS
 */
test('auth helpers: enqueue and retrieve assets via helpers', function () {
    apAuthEnqueueAsset('auth-js', '/auth/app.js', true);

    $result = apAuthAssets();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('auth-js');
    expect($result['auth-js'])
        ->toMatchArray([
            'path' => '/auth/app.js',
            'inFooter' => true,
        ]);
});

---
title: Core Assets
---

# Core Assets

The Core Asset Manager provides a simple way to register and retrieve asset definitions for different contexts: admin, public, and authenticated areas. It also exposes filter hooks that allow other packages to modify asset collections.

## Helpers

Use the helper functions to interact with the Asset Manager:

- apAdminEnqueueAsset($handle, $path, $inFooter = false)
- apAdminDequeueAsset($handle)
- apAdminAssets(): array

- apPublicEnqueueAsset($handle, $path, $inFooter = false)
- apPublicDequeueAsset($handle)
- apPublicAssets(): array

- apAuthEnqueueAsset($handle, $path, $inFooter = false)
- apAuthDequeueAsset($handle)
- apAuthAssets(): array

## Registering Assets

```php
// Admin area
apAdminEnqueueAsset('admin-app', mix('js/admin.js'), inFooter: true);

// Public area
apPublicEnqueueAsset('site', mix('js/site.js'), inFooter: true);

// Authenticated user area (e.g., account dashboard)
apAuthEnqueueAsset('account', mix('js/account.js'), inFooter: true);
```

## Retrieving Assets

```php
// In a view composer or controller
$adminAssets = apAdminAssets();
$publicAssets = apPublicAssets();
$authAssets = apAuthAssets();
```

Each array has the following structure:

```php
[
  'handle' => [
    'path' => '/build/assets/app.js',
    'inFooter' => true,
  ],
]
```

## Modifying Assets via Hooks

Third‑party code can modify the final collections via hooks:

- ap.admin.enqueuedAssets
- ap.public.enqueuedAssets
- ap.auth.enqueuedAssets

Example using addFilter:

```php
addFilter('ap.public.enqueuedAssets', function (array $assets) {
    $assets['analytics'] = [
        'path' => 'https://example.com/analytics.js',
        'inFooter' => true,
    ];
    return $assets;
});
```

## Service Registration

AssetManager is registered as a singleton by the CoreServiceProvider, so the helpers will always resolve the same instance.

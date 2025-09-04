---
title: Progressive Web App (PWA)
---

# Progressive Web App (PWA)

The ArtisanPack UI CMS Framework provides built-in support for Progressive Web Apps (PWAs), allowing you to transform your web application into an app-like experience that can work offline, receive push notifications, and be installed on users' devices.

## Overview

Progressive Web Apps combine the best of web and mobile apps. They are web applications that can be installed on a user's device, work offline, and provide a native app-like experience. The PWA feature in the CMS Framework provides the necessary infrastructure to turn your web application into a PWA with minimal configuration.

## Key Components

The PWA feature consists of several key components:

### Web App Manifest

The Web App Manifest is a JSON file that provides information about your web application to the browser. It includes details such as the app's name, icons, and display preferences. The CMS Framework automatically generates this manifest based on your configuration settings.

#### Route
```php
Route::get('/manifest.json', function (SettingsManager $settings) {
    // Returns a JSON response with the manifest configuration
})->name('pwa.manifest');
```

### Service Worker

The Service Worker is a JavaScript file that runs in the background and enables features like offline access and push notifications. The CMS Framework provides a basic service worker implementation that caches key resources for offline use.

#### Route
```php
Route::get('/service-worker.js', function (SettingsManager $settings) {
    // Returns the service worker JavaScript file
})->name('pwa.service-worker');
```

### PWA Settings

The PWA feature uses the Settings Manager to store and manage PWA-related configuration. These settings control various aspects of the PWA, such as its name, icons, and display mode.

## Configuration

### Available Settings

The following settings are available for configuring the PWA feature:

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `pwa.enabled` | boolean | false | Enable or disable PWA features |
| `pwa.name` | string | app.name | The full name of your Progressive Web App |
| `pwa.short_name` | string | app.name | A short name for your PWA, displayed on the user's home screen |
| `pwa.description` | string | null | A description of your PWA |
| `pwa.start_url` | string | '/' | The URL that loads when your PWA is launched |
| `pwa.display` | string | 'standalone' | The preferred display mode for your PWA (e.g., "standalone", "fullscreen") |
| `pwa.background_color` | string | '#ffffff' | The background color of the splash screen when your PWA is launched |
| `pwa.theme_color` | string | '#ffffff' | The theme color for your PWA, affecting the browser's UI elements |
| `pwa.icons` | array | [] | An array of icon definitions for your PWA |

### Setting Configuration

You can configure the PWA settings using the Settings Manager:

```php
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;

// Get the settings manager from the service container
$settingsManager = app(SettingsManager::class);

// Enable PWA features
$settingsManager->set('pwa.enabled', true);

// Set the PWA name
$settingsManager->set('pwa.name', 'My Awesome PWA');

// Set the PWA icons
$settingsManager->set('pwa.icons', [
    [
        'src' => '/images/icons/icon-72x72.png',
        'sizes' => '72x72',
        'type' => 'image/png'
    ],
    [
        'src' => '/images/icons/icon-96x96.png',
        'sizes' => '96x96',
        'type' => 'image/png'
    ],
    [
        'src' => '/images/icons/icon-128x128.png',
        'sizes' => '128x128',
        'type' => 'image/png'
    ],
    [
        'src' => '/images/icons/icon-144x144.png',
        'sizes' => '144x144',
        'type' => 'image/png'
    ],
    [
        'src' => '/images/icons/icon-152x152.png',
        'sizes' => '152x152',
        'type' => 'image/png'
    ],
    [
        'src' => '/images/icons/icon-192x192.png',
        'sizes' => '192x192',
        'type' => 'image/png'
    ],
    [
        'src' => '/images/icons/icon-384x384.png',
        'sizes' => '384x384',
        'type' => 'image/png'
    ],
    [
        'src' => '/images/icons/icon-512x512.png',
        'sizes' => '512x512',
        'type' => 'image/png'
    ]
]);
```

## Implementation

### Adding PWA Support to Your Application

To add PWA support to your application, you need to:

1. **Enable the PWA feature**:
   ```php
   $settingsManager->set('pwa.enabled', true);
   ```

2. **Configure the PWA settings** as shown in the previous section.

3. **Add the manifest link to your HTML**:
   ```html
   <link rel="manifest" href="/manifest.json">
   ```

4. **Add the service worker registration script to your HTML**:
   ```html
   <script>
     if ('serviceWorker' in navigator) {
       window.addEventListener('load', function() {
         navigator.serviceWorker.register('/service-worker.js')
           .then(function(registration) {
             console.log('ServiceWorker registration successful with scope: ', registration.scope);
           })
           .catch(function(error) {
             console.log('ServiceWorker registration failed: ', error);
           });
       });
     }
   </script>
   ```

5. **Add theme color meta tag to your HTML**:
   ```html
   <meta name="theme-color" content="#your-theme-color">
   ```

6. **Add Apple touch icon for iOS devices**:
   ```html
   <link rel="apple-touch-icon" href="/images/icons/icon-192x192.png">
   ```

### Customizing the Service Worker

The default service worker provided by the CMS Framework caches the homepage and index.php file. You may want to customize this to cache additional resources or implement more advanced caching strategies.

To customize the service worker, you can:

1. **Create a custom service worker view**:
   Create a new Blade view in your application that extends or replaces the default service worker.

2. **Register your custom service worker view**:
   ```php
   // In a service provider
   $this->loadViewsFrom(resource_path('views/pwa'), 'my-pwa');

   // Then update the route to use your view
   Route::get('/service-worker.js', function (SettingsManager $settings) {
       if (!$settings->get('pwa.enabled')) {
           abort(404);
       }
       return response(view('my-pwa::service-worker'))
           ->header('Content-Type', 'application/javascript');
   })->name('pwa.service-worker');
   ```

## Testing

To test your PWA implementation:

1. **Enable the PWA feature** in your application settings.
2. **Open your application in a supported browser** (Chrome, Edge, Firefox, etc.).
3. **Use the browser's developer tools** to inspect the PWA features:
   - In Chrome, open DevTools, go to the "Application" tab, and look for the "Manifest" and "Service Workers" sections.
4. **Test offline functionality** by disconnecting from the internet and reloading the page.
5. **Test installation** by looking for the "Add to Home Screen" prompt or using the installation option in the browser's menu.

## Best Practices

For optimal PWA implementation:

1. **Cache critical assets** in the service worker to ensure offline functionality.
2. **Provide appropriate icons** for different device sizes and resolutions.
3. **Set meaningful names and descriptions** for your PWA.
4. **Choose appropriate display modes** based on your application's needs.
5. **Test your PWA on various devices and browsers** to ensure compatibility.
6. **Use Lighthouse** in Chrome DevTools to audit your PWA and identify areas for improvement.

## Conclusion

The PWA feature in the ArtisanPack UI CMS Framework provides a simple way to add Progressive Web App capabilities to your application. By following the implementation steps and best practices outlined in this documentation, you can create a PWA that offers an app-like experience to your users, works offline, and can be installed on their devices.

## Implementation Guide

For a detailed guide on integrating PWA features into your ArtisanPack UI application, see the [Integrating PWA Features into Your CMS](pwa-integration-guide.md) documentation.

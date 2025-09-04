---
title: Integrating PWA Features into Your CMS
---

# Integrating PWA Features into Your CMS

This guide outlines the steps for developers to integrate Progressive Web App (PWA) features into their CMS using the ArtisanPack UI CMS Framework. The framework provides the core PWA infrastructure, and these steps focus on how you, as a developer, can enable and configure it for your specific CMS implementation.

## Prerequisites

Before you begin, ensure you have:

- An existing Laravel application built with the ArtisanPack UI CMS Framework.
- Access to modify your main Blade layout file (e.g., resources/views/layouts/app.blade.php).
- A fundamental understanding of Service Workers and Web App Manifests.
- Crucially, your application must be served over HTTPS. Service Workers only function over secure connections.

## Core CMS Framework PWA Components (Provided by ArtisanPack UI)

The ArtisanPack UI CMS Framework provides the backend and basic Service Worker capabilities for PWA. These files are already included in the framework package:

- **ArtisanPackUI/CMSFramework/Features/Settings/SettingsManager.php**: This class includes a `registerPwaDefaults()` method to set up default PWA configuration options (e.g., pwa.enabled, pwa.name, pwa.short_name, pwa.start_url, pwa.display, pwa.background_color, pwa.theme_color, pwa.icons).
- **ArtisanPackUI/CMSFramework/CMSFrameworkServiceProvider.php**: This service provider calls `registerPwaFeatures()` during its boot process, which loads the PWA-specific routes and registers the default settings and views.
- **ArtisanPackUI/CMSFramework/Features/PWA/routes.php**: This dedicated route file defines the `/manifest.json` and `/service-worker.js` endpoints. These routes dynamically serve the Web App Manifest and Service Worker file based on your CMS settings.
- **ArtisanPackUI/CMSFramework/Features/PWA/resources/views/service-worker.blade.php**: This Blade view contains the basic Service Worker JavaScript, handling install and fetch events for offline caching.

## Developer Integration Steps

Your primary tasks will involve integrating the PWA elements into your frontend layout and providing an administrative interface for configuration.

### Step 1: Ensure HTTPS is Enforced

Service Workers require HTTPS. If you haven't already, ensure your production environment forces HTTPS.

**File**: app/Providers/AppServiceProvider.php
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // Don't forget to import this

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Force HTTPS in production environment.
        if ( $this->app->environment( 'production' ) ) {
            URL::forceScheme( 'https' );
        }
    }
}
```

### Step 2: Include PWA Elements in Your Main Blade Layout

Modify your application's primary Blade layout file to include the necessary PWA links and Service Worker registration script.

**File**: resources/views/layouts/app.blade.php (or equivalent)

Locate the `<head>` section and add the following conditional block:
```blade
<head>
    {{-- Your existing head content (meta tags, CSS, etc.) --}}

    @php
        // Resolve the SettingsManager instance.
        $settings = app( \ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager::class );
    @endphp

    {{-- Conditionally include PWA manifest and Service Worker registration --}}
    @if( $settings->get('pwa.enabled') )
        <link rel="manifest" href="{{ route('pwa.manifest') }}">
        <meta name="theme-color" content="{{ $settings->get('pwa.theme_color') }}">

        <script>
            // Register the Service Worker if supported by the browser.
            if ( 'serviceWorker' in navigator ) {
                window.addEventListener( 'load', () => {
                    navigator.serviceWorker.register( '{{ route('pwa.service-worker') }}' )
                        .then( registration => {
                            console.log( 'Service Worker registered: ', registration );
                        } )
                        .catch( error => {
                            console.error( 'Service Worker registration failed: ', error );
                        } );
                } );
            }
        </script>
    @endif

    {{-- Your other scripts, typically at the end of head or before </body> --}}
</head>
```

### Step 3: Create an Administrative Interface for PWA Settings

This is where you'll build the UI for your CMS administrators to configure the PWA. Using Livewire is the recommended approach for dynamic forms in ArtisanPack UI.

#### A. Create a Livewire Component

**File**: app/Http/Livewire/Admin/PwaSettings.php
```php
<?php
/**
 * Livewire Component for PWA Settings.
 *
 * Provides an administrative interface for configuring Progressive Web App features.
 *
 * @package    YourCMSNamespace\Admin
 * @subpackage YourCMSNamespace\Admin\Livewire
 * @since      1.1.0
 */

namespace App\Http\Livewire\Admin;

use Livewire\Component;
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;
use Livewire\WithFileUploads; // If you plan to handle icon uploads

class PwaSettings extends Component
{
    use WithFileUploads; // Enable file uploads for icons

    /**
     * Determines if PWA features are enabled.
     *
     * @since 1.1.0
     * @var bool
     */
    public bool $pwaEnabled;

    /**
     * The full name of the PWA.
     *
     * @since 1.1.0
     * @var string
     */
    public string $pwaName;

    /**
     * A short name for the PWA.
     *
     * @since 1.1.0
     * @var string
     */
    public string $pwaShortName;

    /**
     * A description for the PWA.
     *
     * @since 1.1.0
     * @var string|null
     */
    public ?string $pwaDescription;

    /**
     * The start URL for the PWA.
     *
     * @since 1.1.0
     * @var string
     */
    public string $pwaStartUrl;

    /**
     * The display mode for the PWA.
     *
     * @since 1.1.0
     * @var string
     */
    public string $pwaDisplay;

    /**
     * The background color for the PWA splash screen.
     *
     * @since 1.1.0
     * @var string
     */
    public string $pwaBackgroundColor;

    /**
     * The theme color for the PWA.
     *
     * @since 1.1.0
     * @var string
     */
    public string $pwaThemeColor;

    /**
     * The array of PWA icons.
     *
     * @since 1.1.0
     * @var array
     */
    public array $pwaIcons = [];

    /**
     * Temporary property for new icon upload.
     *
     * @since 1.1.0
     * @var mixed
     */
    public $newIcon;

    /**
     * Mounts the component and loads existing PWA settings.
     *
     * @since 1.1.0
     *
     * @param SettingsManager $settings The settings manager instance.
     * @return void
     */
    public function mount( SettingsManager $settings ): void
    {
        $this->pwaEnabled       = (bool) $settings->get( 'pwa.enabled', false );
        $this->pwaName          = $settings->get( 'pwa.name', config('app.name') );
        $this->pwaShortName     = $settings->get( 'pwa.short_name', config('app.name') );
        $this->pwaDescription   = $settings->get( 'pwa.description' );
        $this->pwaStartUrl      = $settings->get( 'pwa.start_url', '/' );
        $this->pwaDisplay       = $settings->get( 'pwa.display', 'standalone' );
        $this->pwaBackgroundColor = $settings->get( 'pwa.background_color', '#ffffff' );
        $this->pwaThemeColor    = $settings->get( 'pwa.theme_color', '#ffffff' );
        $this->pwaIcons         = $settings->get( 'pwa.icons', [] );
    }

    /**
     * Renders the component view.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view( 'livewire.admin.pwa-settings' );
    }

    /**
     * Saves the PWA settings.
     *
     * @since 1.1.0
     *
     * @param SettingsManager $settings The settings manager instance.
     * @return void
     */
    public function saveSettings( SettingsManager $settings ): void
    {
        $this->validate( [
            'pwaName'          => 'required|string|max:255',
            'pwaShortName'     => 'required|string|max:255',
            'pwaDescription'   => 'nullable|string|max:500',
            'pwaStartUrl'      => 'required|string|max:255',
            'pwaDisplay'       => 'required|string|in:standalone,fullscreen,minimal-ui,browser',
            'pwaBackgroundColor' => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'pwaThemeColor'    => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'pwaEnabled'       => 'boolean',
            'pwaIcons'         => 'array', // Assuming this is an array of already processed icon data
            'newIcon'          => 'nullable|image|max:1024', // 1MB Max
        ] );

        // Handle icon upload if present.
        if ( $this->newIcon ) {
            $path = $this->newIcon->store( 'public/pwa/icons' );
            $url  = \Storage::url( $path );

            // You'll need to define sizes and type. For simplicity, we'll use a generic one.
            // In a real scenario, you'd process/resize the image to multiple sizes.
            $this->pwaIcons[] = [
                'src'   => $url,
                'sizes' => '512x512', // This needs to be dynamic based on actual icon properties
                'type'  => $this->newIcon->getMimeType(),
            ];
            $this->newIcon = null; // Clear the temporary upload property
        }

        $settings->set( 'pwa.enabled', $this->pwaEnabled );
        $settings->set( 'pwa.name', $this->pwaName );
        $settings->set( 'pwa.short_name', $this->pwaShortName );
        $settings->set( 'pwa.description', $this->pwaDescription );
        $settings->set( 'pwa.start_url', $this->pwaStartUrl );
        $settings->set( 'pwa.display', $this->pwaDisplay );
        $settings->set( 'pwa.background_color', $this->pwaBackgroundColor );
        $settings->set( 'pwa.theme_color', $this->pwaThemeColor );
        $settings->set( 'pwa.icons', $this->pwaIcons );

        session()->flash( 'message', 'PWA settings updated successfully.' );
    }

    /**
     * Removes an icon from the PWA icons array.
     *
     * @since 1.1.0
     *
     * @param int $index The index of the icon to remove.
     * @return void
     */
    public function removeIcon( int $index ): void
    {
        if ( isset( $this->pwaIcons[ $index ] ) ) {
            // Optional: Delete the actual file from storage
            // \Storage::delete( str_replace('/storage', 'public', $this->pwaIcons[$index]['src']) );
            unset( $this->pwaIcons[ $index ] );
            $this->pwaIcons = array_values( $this->pwaIcons ); // Re-index array
        }
    }
}
```

#### B. Create the Livewire Component View

**File**: resources/views/livewire/admin/pwa-settings.blade.php
```blade
{{--
/**
 * Livewire View for PWA Settings.
 *
 * Displays a form for configuring Progressive Web App features.
 *
 * @package    YourCMSNamespace\Admin
 * @subpackage YourCMSNamespace\Admin\Livewire
 * @since      1.1.0
 */
--}}
<div>
    <form wire:submit.prevent="saveSettings">
        @if ( session()->has('message') )
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('message') }}</span>
            </div>
        @endif

        <div class="mb-4">
            <label for="pwaEnabled" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Enable PWA</label>
            <input type="checkbox" id="pwaEnabled" wire:model="pwaEnabled" class="mt-1">
            <p class="text-xs text-gray-500 dark:text-gray-400">Turn on/off Progressive Web App features for your site.</p>
        </div>

        <div class="mb-4">
            <label for="pwaName" class="block text-sm font-medium text-gray-700 dark:text-gray-300">App Name</label>
            <input type="text" id="pwaName" wire:model="pwaName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            @error('pwaName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label for="pwaShortName" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Short Name</label>
            <input type="text" id="pwaShortName" wire:model="pwaShortName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            @error('pwaShortName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label for="pwaDescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
            <textarea id="pwaDescription" wire:model="pwaDescription" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"></textarea>
            <p class="text-xs text-gray-500 dark:text-gray-400">A brief description of your PWA.</p>
            @error('pwaDescription') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label for="pwaStartUrl" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start URL</label>
            <input type="text" id="pwaStartUrl" wire:model="pwaStartUrl" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <p class="text-xs text-gray-500 dark:text-gray-400">The URL the PWA should load when launched (e.g., `/` for homepage).</p>
            @error('pwaStartUrl') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label for="pwaDisplay" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Display Mode</label>
            <select id="pwaDisplay" wire:model="pwaDisplay" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                <option value="standalone">Standalone</option>
                <option value="fullscreen">Fullscreen</option>
                <option value="minimal-ui">Minimal UI</option>
                <option value="browser">Browser</option>
            </select>
            <p class="text-xs text-gray-500 dark:text-gray-400">How the PWA should be displayed (e.g., like a native app).</p>
            @error('pwaDisplay') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label for="pwaBackgroundColor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Background Color</label>
            <input type="color" id="pwaBackgroundColor" wire:model="pwaBackgroundColor" class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400">The background color of the splash screen when the PWA launches.</p>
            @error('pwaBackgroundColor') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label for="pwaThemeColor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Theme Color</label>
            <input type="color" id="pwaThemeColor" wire:model="pwaThemeColor" class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400">The theme color for your PWA, affecting browser UI.</p>
            @error('pwaThemeColor') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label for="newIcon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">PWA Icons</label>
            <input type="file" id="newIcon" wire:model="newIcon" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            @error('newIcon') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-500 dark:text-gray-400">Upload square icons (e.g., 512x512, 192x192) for your PWA.</p>

            @if ( count($pwaIcons) > 0 )
                <div class="mt-4 grid grid-cols-4 gap-4">
                    @foreach ( $pwaIcons as $index => $icon )
                        <div class="relative group">
                            <img src="{{ $icon['src'] }}" alt="PWA Icon" class="w-full h-full object-contain border border-gray-200 rounded-md p-2">
                            <button type="button" wire:click="removeIcon({{ $index }})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div class="text-xs text-center mt-1 text-gray-600 dark:text-gray-400">
                                {{ $icon['sizes'] ?? 'Unknown Size' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-6">
            <x-ds::button type="submit" variant="primary" :loading="true">Save PWA Settings</x-ds::button>
        </div>
    </form>
</div>
```

#### C. Register the Livewire Component Route

**File**: routes/web.php or routes/admin.php (for your CMS admin routes)
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Livewire\Admin\PwaSettings;

// Assuming your admin routes are grouped.
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/settings/pwa', PwaSettings::class)->name('admin.settings.pwa');
});
```

### Step 4: Customize Service Worker (Advanced - Optional)

The provided service-worker.blade.php has a basic caching strategy. For more advanced PWA features (e.g., push notifications, background sync, more sophisticated caching strategies for dynamic content), you will need to modify this file.

**File**: ArtisanPackUI/CMSFramework/Features/PWA/resources/views/service-worker.blade.php

**Considerations**:

- **Caching Strategy**: Implement Cache First, Network First, Stale-While-Revalidate for different asset types.
- **Offline Fallback Page**: Create a dedicated offline HTML page and cache it, then serve it when network requests fail.
- **Push Notifications**: Integrate with a push notification service and handle push events in the Service Worker.
- **Background Sync**: Implement background sync for sending data when connectivity is restored.

**Example for advanced caching**:
```javascript
// ... existing install event

self.addEventListener('fetch', (event) => {
    // For navigation requests, try network first, then cache, then fallback.
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match('/offline.html'); // Assuming you have an offline.html
            })
        );
        return; // Exit early
    }

    // For other requests (CSS, JS, images, etc.), use cache-first strategy.
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request).then((networkResponse) => {
                // Optionally cache new successful responses.
                return caches.open('artisanpack-ui-pwa-dynamic-cache-v1').then((cache) => {
                    cache.put(event.request, networkResponse.clone());
                    return networkResponse;
                });
            }).catch(() => {
                // Respond with an image or specific asset if network fails and not in cache.
                // Or simply let it fail, depending on your needs.
                // return new Response('Offline content here');
            });
        })
    );
});
```

## Conclusion

By following this guide, you can successfully integrate the PWA features provided by the ArtisanPack UI CMS Framework into your CMS, offering your users the benefits of modern web applications. Remember to always test thoroughly, especially Service Worker behavior across different browsers and network conditions.

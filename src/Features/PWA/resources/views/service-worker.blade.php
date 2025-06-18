{{--
/**
 * Service Worker for Progressive Web App.
 *
 * Handles caching of assets for offline use and other PWA functionalities.
 *
 * @package ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\PWA
 * @since 1.1.0
 */
--}}
self.addEventListener('install', (event) => {
event.waitUntil(
caches.open('artisanpack-ui-pwa-cache-v1').then((cache) => {
return cache.addAll([
'/',
'/index.php', // Or your actual homepage route if it's not '/'
// Add other critical assets you want to cache for offline use.
// This could be CSS, JavaScript, images.
// Example:
// '/css/app.css',
// '/js/app.js',
// '/images/logo.png',
]);
})
);
});

self.addEventListener('fetch', (event) => {
event.respondWith(
caches.match(event.request).then((response) => {
return response || fetch(event.request);
})
);
});

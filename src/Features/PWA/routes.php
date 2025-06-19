<?php
/**
 * PWA Routes
 *
 * This file registers the routes necessary for Progressive Web App features.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\PWA
 * @since      1.1.0
 */

use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;
use Illuminate\Support\Facades\Route;

/**
 * Registers the route for the Web App Manifest.
 *
 * This route returns a JSON response with the PWA manifest configuration.
 * If the PWA feature is disabled, it returns a 404 response.
 *
 * @since 1.1.0
 *
 * @param SettingsManager $settings The settings manager instance.
 * @return \Illuminate\Http\JsonResponse The JSON response with the manifest data.
 */
Route::get( '/manifest.json', function ( SettingsManager $settings ) {
    if ( ! $settings->get( 'pwa.enabled' ) ) {
        abort( 404 );
    }

    $manifest = [
        'name'             => $settings->get( 'pwa.name' ),
        'short_name'       => $settings->get( 'pwa.short_name' ),
        'description'      => $settings->get( 'pwa.description' ),
        'start_url'        => $settings->get( 'pwa.start_url' ),
        'display'          => $settings->get( 'pwa.display' ),
        'background_color' => $settings->get( 'pwa.background_color' ),
        'theme_color'      => $settings->get( 'pwa.theme_color' ),
        'icons'            => $settings->get( 'pwa.icons', [] ),
    ];

    return response()->json( $manifest );
} )->name( 'pwa.manifest' );

/**
 * Registers the route for the Service Worker.
 *
 * This route returns the service worker JavaScript file with the appropriate content type.
 * If the PWA feature is disabled, it returns a 404 response.
 *
 * @since 1.1.0
 *
 * @param SettingsManager $settings The settings manager instance.
 * @return \Illuminate\Http\Response The response with the service worker JavaScript.
 */
Route::get( '/service-worker.js', function ( SettingsManager $settings ) {
    if ( ! $settings->get( 'pwa.enabled' ) ) {
        abort( 404 );
    }
    return response( view( 'pwa::service-worker' ) )->header( 'Content-Type', 'application/javascript' );
} )->name( 'pwa.service-worker' );

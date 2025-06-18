<?php
/**
 * PWA Routes
 *
 * This file registers the routes necessary for Progressive Web App features.
 *
 * @since      1.1.0
 * @subpackage ArtisanPackUI\CMSFramework\PWA
 * @package    ArtisanPackUI\CMSFramework
 */

use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;
use Illuminate\Support\Facades\Route;

/*
 * Registers the route for the Web App Manifest.
 *
 * @since 1.1.0
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

/*
 * Registers the route for the Service Worker.
 *
 * @since 1.1.0
 */
Route::get( '/service-worker.js', function ( SettingsManager $settings ) {
    if ( ! $settings->get( 'pwa.enabled' ) ) {
        abort( 404 );
    }
    return response( view( 'pwa::service-worker' ) )->header( 'Content-Type', 'application/javascript' );
} )->name( 'pwa.service-worker' );

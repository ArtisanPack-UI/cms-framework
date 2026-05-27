<?php

/**
 * Regression test for #116 — filter callbacks must not crash when their
 * underlying SiteEditor tables are missing.
 *
 * The five `ap.visual-editor.*` filter callbacks registered by
 * {@see SiteEditorServiceProvider::registerVisualEditorSiteEditorFilters()}
 * eagerly query their primary tables. When visual-editor's H5 service
 * provider applies these filters at `booted()` — which Laravel boots
 * before host package migrations run — the un-guarded callbacks crashed
 * with "no such table" `QueryException`s. The regression here drops the
 * five tables, re-registers the filters, and asserts each callback
 * returns its input value unchanged with no exception.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Providers\SiteEditorServiceProvider;
use Illuminate\Support\Facades\Schema;

beforeEach( function (): void {
    require_once __DIR__ . '/../../Support/VisualEditorClassStub.php';

    // Drop the five SiteEditor tables to simulate boot-before-migrations.
    // Each filter callback short-circuits via Schema::hasTable when its
    // primary table is missing, so post-drop the filters must return the
    // input value unchanged.
    foreach ( ['menu_location_assignments', 'menu_items', 'menus', 'global_styles', 'block_patterns', 'template_parts', 'templates'] as $table ) {
        Schema::dropIfExists( $table );
    }

    foreach ( [
        'ap.visual-editor.templates',
        'ap.visual-editor.template-parts',
        'ap.visual-editor.patterns',
        'ap.visual-editor.global-styles',
        'ap.visual-editor.navigation',
    ] as $filter ) {
        removeAllFilters( $filter );
    }

    (new SiteEditorServiceProvider( app() ))->registerVisualEditorSiteEditorFilters();
} );

afterEach( function (): void {
    foreach ( [
        'ap.visual-editor.templates',
        'ap.visual-editor.template-parts',
        'ap.visual-editor.patterns',
        'ap.visual-editor.global-styles',
        'ap.visual-editor.navigation',
    ] as $filter ) {
        removeAllFilters( $filter );
    }
} );

it( 'short-circuits ap.visual-editor.templates when the templates table is missing', function (): void {
    $result = applyFilters( 'ap.visual-editor.templates', ['foo' => ['slug' => 'foo']] );

    expect( $result )->toBe( ['foo' => ['slug' => 'foo']] );
} );

it( 'short-circuits ap.visual-editor.template-parts when the template_parts table is missing', function (): void {
    $result = applyFilters( 'ap.visual-editor.template-parts', ['header' => ['slug' => 'header']] );

    expect( $result )->toBe( ['header' => ['slug' => 'header']] );
} );

it( 'short-circuits ap.visual-editor.patterns when the block_patterns table is missing', function (): void {
    $result = applyFilters( 'ap.visual-editor.patterns', ['hero' => ['slug' => 'hero']] );

    expect( $result )->toBe( ['hero' => ['slug' => 'hero']] );
} );

it( 'short-circuits ap.visual-editor.global-styles when the global_styles table is missing', function (): void {
    $existing = ['theme' => 'fallback', 'settings' => [], 'styles' => [], 'variations' => []];

    $result = applyFilters( 'ap.visual-editor.global-styles', $existing );

    expect( $result )->toBe( $existing );
} );

it( 'passes null through ap.visual-editor.global-styles when the table is missing and there is no prior value', function (): void {
    $result = applyFilters( 'ap.visual-editor.global-styles', null );

    expect( $result )->toBeNull();
} );

it( 'short-circuits ap.visual-editor.navigation when the menus table is missing', function (): void {
    $result = applyFilters( 'ap.visual-editor.navigation', ['primary' => ['location' => 'primary']] );

    expect( $result )->toBe( ['primary' => ['location' => 'primary']] );
} );

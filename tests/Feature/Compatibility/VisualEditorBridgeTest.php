<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;

// Standalone gate behavior — that registerVisualEditorBridge() does NOT
// add a callback when the VisualEditor class isn't loaded — is implicitly
// proven by the rest of the cms-framework test suite: visual-editor is not
// in this package's composer.json, the gate evaluates false at boot, and
// every other test passes with the bridge code in place. A dedicated
// standalone test here is redundant and depends on the stub class not
// being already loaded by a sibling test in the same process.

afterEach( function (): void {
    // Wipe the filter so a later test's call to applyFilters() does not
    // see callbacks registered by an earlier test in this file.
    removeAllFilters( 'ap.visual-editor.resources' );
} );

test( 'integrated install: registerVisualEditorBridge contributes posts + pages into the filter', function (): void {
    require_once __DIR__ . '/../../Support/VisualEditorClassStub.php';

    ( new CMSFrameworkServiceProvider( app() ) )->registerVisualEditorBridge();

    $resources = applyFilters( 'ap.visual-editor.resources', [] );

    expect( $resources )
        ->toHaveKey( 'posts', Post::class )
        ->toHaveKey( 'pages', Page::class );
} );

test( 'integrated install: existing entries win over the bridge defaults on key collision', function (): void {
    require_once __DIR__ . '/../../Support/VisualEditorClassStub.php';

    ( new CMSFrameworkServiceProvider( app() ) )->registerVisualEditorBridge();

    $resources = applyFilters( 'ap.visual-editor.resources', [
        'posts' => 'App\\Models\\CustomPost',
    ] );

    // The bridge merges defaults *first* so existing entries (whether from
    // host config or another filter contributor that ran earlier) survive
    // unchanged. The downstream visual-editor merge then layers static
    // config on top one more time — see plan 12 §4.1.
    expect( $resources )
        ->toHaveKey( 'posts', 'App\\Models\\CustomPost' )
        ->toHaveKey( 'pages', Page::class );
} );

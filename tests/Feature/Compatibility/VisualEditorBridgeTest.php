<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;

afterEach( function (): void {
    // Wipe the filter so a later test's call to applyFilters() does not
    // see callbacks registered by an earlier test in this file.
    removeAllFilters( 'ap.visual-editor.resources' );
} );

test( 'standalone install: boot does not register the resources filter when visual-editor is not loaded', function (): void {
    if ( class_exists( ArtisanPackUI\VisualEditor\VisualEditor::class, false ) ) {
        $this->markTestSkipped( 'A prior test loaded the visual-editor stub class; the standalone gate cannot be verified in this run.' );
    }

    $passthrough = [ 'baseline' => 'untouched' ];

    expect( applyFilters( 'ap.visual-editor.resources', $passthrough ) )->toBe( $passthrough );
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

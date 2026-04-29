<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestBlockContentTypeModel;
use ArtisanPackUI\CMSFramework\Tests\Support\TestPlainContentTypeModel;
use Illuminate\Support\Facades\Log;

// Standalone gate behavior — that registerVisualEditorBridge() does NOT
// add a callback when the VisualEditor class isn't loaded — is implicitly
// proven by the rest of the cms-framework test suite: visual-editor is not
// in this package's composer.json, the gate evaluates false at boot, and
// every other test passes with the bridge code in place. A dedicated
// standalone test here is redundant and depends on the stub class not
// being already loaded by a sibling test in the same process.

beforeEach( function (): void {
    // The G1c auto-registration test cases need the content_types table
    // to exist so ContentTypeManager::getRegisteredContentTypes() can read
    // it, and need a clean slate on both filters: once test #1 loads the
    // VisualEditor stub class via require_once, every subsequent
    // service-provider boot adds its own callbacks alongside the manual
    // (new SP)->registerVisualEditorBridge() call below, double-firing
    // the iteration.
    $this->artisan( 'migrate', [ '--database' => 'testing' ] );
    removeAllFilters( 'ap.visual-editor.resources' );
    removeAllFilters( 'ap.contentTypes.registeredContentTypes' );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.visual-editor.resources' );
    removeAllFilters( 'ap.contentTypes.registeredContentTypes' );
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

test( 'auto-register: a custom content type whose model uses HasBlockContent is added to the resource map', function (): void {
    require_once __DIR__ . '/../../Support/VisualEditorClassStub.php';

    app( ContentTypeManager::class )->register( [
        'name'        => 'Widgets',
        'slug'        => 'widgets',
        'table_name'  => 'widgets',
        'model_class' => TestBlockContentTypeModel::class,
        'supports'    => [ 'title', 'editor' ],
    ] );

    ( new CMSFrameworkServiceProvider( app() ) )->registerVisualEditorBridge();

    $resources = applyFilters( 'ap.visual-editor.resources', [] );

    expect( $resources )->toHaveKey( 'widgets', TestBlockContentTypeModel::class );
} );

test( 'auto-register: a content type without HasBlockContent is silently skipped', function (): void {
    require_once __DIR__ . '/../../Support/VisualEditorClassStub.php';

    Log::spy();

    app( ContentTypeManager::class )->register( [
        'name'        => 'Tags',
        'slug'        => 'tags',
        'table_name'  => 'tags',
        'model_class' => TestPlainContentTypeModel::class,
        'supports'    => [ 'title' ], // no 'editor' — silent skip path
    ] );

    ( new CMSFrameworkServiceProvider( app() ) )->registerVisualEditorBridge();

    $resources = applyFilters( 'ap.visual-editor.resources', [] );

    expect( $resources )->not->toHaveKey( 'tags' );
    Log::shouldNotHaveReceived( 'warning' );
} );

test( 'auto-register: a content type that declares editor support but lacks the trait surfaces a warning', function (): void {
    require_once __DIR__ . '/../../Support/VisualEditorClassStub.php';

    Log::spy();

    app( ContentTypeManager::class )->register( [
        'name'        => 'Broken',
        'slug'        => 'broken',
        'table_name'  => 'broken',
        'model_class' => TestPlainContentTypeModel::class,
        'supports'    => [ 'editor' ], // declared editor but trait missing
    ] );

    ( new CMSFrameworkServiceProvider( app() ) )->registerVisualEditorBridge();

    $resources = applyFilters( 'ap.visual-editor.resources', [] );

    expect( $resources )->not->toHaveKey( 'broken' );

    Log::shouldHaveReceived( 'warning' )
        ->withArgs( fn ( string $message ) =>
            str_contains( $message, '[broken]' )
            && str_contains( $message, TestPlainContentTypeModel::class )
            && str_contains( $message, 'HasBlockContent' ),
        )
        ->once();
} );

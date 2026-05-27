<?php

/**
 * Test-only stub of the visual-editor main class.
 *
 * Loaded by tests that exercise the cms-framework → visual-editor bridge
 * (`CMSFrameworkServiceProvider::registerVisualEditorBridge()`). The bridge
 * gates filter registration on `class_exists( \ArtisanPackUI\VisualEditor\VisualEditor::class )`;
 * `require_once`-loading this file in a test makes that gate evaluate true
 * without pulling visual-editor into cms-framework's composer dev deps.
 *
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor;

if ( ! class_exists( __NAMESPACE__ . '\\VisualEditor', false ) ) {
    /**
     * Stub. The real class lives in artisanpack-ui/visual-editor.
     */
    class VisualEditor
    {
    }
}

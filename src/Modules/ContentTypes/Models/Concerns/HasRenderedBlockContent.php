<?php

declare( strict_types=1 );

/**
 * HasRenderedBlockContent Trait
 *
 * Exposes the rendered HTML for a model whose body is stored as a visual-editor
 * block tree. Visual-editor's `PostResolver::resolveContent()` reads
 * `$post->content` and only stamps `_resolvedContent` when it is a string;
 * models that use `HasBlockContent` keep the body as a JSON-cast array, so
 * the `core/post-content` block renders an empty `wp-block-post-content`
 * div on the front end. This trait fills that gap.
 *
 * @since 2.2.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

use Throwable;

/**
 * Adds a `rendered_content` Eloquent accessor (and matching `renderContent()`
 * method) that walks the model's block tree through the visual-editor
 * renderer-blade package and returns the rendered HTML.
 *
 * The renderer is resolved lazily through the container so this stays a soft
 * integration — cms-framework does not require `visual-editor-renderer-blade`
 * as a composer dependency. When the renderer class is not installed, the
 * accessor falls back to the model's existing `content` column (which may
 * carry pre-rendered HTML for legacy records) and finally to an empty string.
 *
 * Intended to be combined with `ArtisanPackUI\VisualEditor\Concerns\HasBlockContent`,
 * which supplies the `getBlockContent()` reader the trait calls.
 *
 * @since 2.2.0
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasRenderedBlockContent
{
    /**
     * Fully-qualified class name of the visual-editor block renderer.
     *
     * Kept as a string so cms-framework never autoloads the class when the
     * renderer-blade package is not installed.
     *
     * @since 2.2.0
     */
    protected const BLOCK_RENDERER_CLASS = 'ArtisanPackUI\\VisualEditorRendererBlade\\BlockRenderer';

    /**
     * Eloquent accessor for `$model->rendered_content`.
     *
     * Visual-editor's `PostResolver::resolveContent()` prefers this attribute
     * over the raw `content` column when present, so a `core/post-content`
     * block stamps the actual rendered HTML rather than an empty string.
     *
     * @since 2.2.0
     */
    public function getRenderedContentAttribute(): string
    {
        return $this->renderContent();
    }

    /**
     * Render the model's block tree to an HTML string.
     *
     * Resolves the visual-editor renderer-blade `BlockRenderer` through the
     * container and walks the block tree returned by
     * `HasBlockContent::getBlockContent()`. When the renderer class is not
     * installed (or resolving / rendering throws), falls back to the
     * model's `content` column if it is a string, otherwise returns an
     * empty string.
     *
     * @since 2.2.0
     */
    public function renderContent(): string
    {
        if ( method_exists( $this, 'getBlockContent' ) && class_exists( self::BLOCK_RENDERER_CLASS ) ) {
            $blocks = $this->getBlockContent();

            if ( [] !== $blocks ) {
                try {
                    $renderer = app( self::BLOCK_RENDERER_CLASS );

                    return $renderer->render( $blocks );
                } catch ( Throwable ) {
                    // Renderer installed but failed to resolve or render —
                    // fall through to the pre-rendered string fallback below.
                }
            }
        }

        $content = $this->getAttribute( 'content' );

        return is_string( $content ) ? $content : '';
    }
}

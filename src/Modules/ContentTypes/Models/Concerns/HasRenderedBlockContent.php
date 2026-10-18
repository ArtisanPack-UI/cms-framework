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
     * Fully-qualified class name of the visual-editor binding context.
     *
     * Kept as a string for the same soft-integration reason as
     * {@see self::BLOCK_RENDERER_CLASS}.
     *
     * @since 2.14.1
     */
    protected const BINDING_CONTEXT_CLASS = 'ArtisanPackUI\\VisualEditor\\Services\\Bindings\\BindingContext';

    /**
     * Fully-qualified class name of visual-editor's block-shape normalizer.
     *
     * Kept as a string for the same soft-integration reason as
     * {@see self::BLOCK_RENDERER_CLASS}.
     *
     * @since 2.15.3
     */
    protected const BLOCK_SHAPE_CLASS = 'ArtisanPackUI\\VisualEditor\\Support\\BlockShape';

    /**
     * Fully-qualified class name of visual-editor's synced-pattern inliner.
     *
     * @since 2.15.3
     */
    protected const PATTERN_INLINER_CLASS = 'ArtisanPackUI\\VisualEditor\\Resources\\PatternInliner';

    /**
     * Fully-qualified class name of visual-editor's query-loop inliner.
     *
     * @since 2.15.3
     */
    protected const QUERY_INLINER_CLASS = 'ArtisanPackUI\\VisualEditor\\Resources\\QueryInliner';

    /**
     * Fully-qualified class name of visual-editor's `post-*` block resolver.
     *
     * @since 2.15.3
     */
    protected const POST_RESOLVER_CLASS = 'ArtisanPackUI\\VisualEditor\\Resources\\PostResolver';

    /**
     * Records whose content is being rendered right now, keyed by
     * {@see self::renderingContentKey()}.
     *
     * A `post-content` block (or a loop that lists the record itself)
     * inside a record's own content would otherwise read
     * `rendered_content` again and recurse without end.
     *
     * @since 2.15.3
     *
     * @var array<string, true>
     */
    protected static array $renderingContentKeys = [];

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
     * @since 2.14.1 Renders with a binding context for the model, so block bindings resolve.
     * @since 2.15.3 Inlines synced patterns and query loops, and stamps `post-*` blocks against the model first.
     */
    public function renderContent(): string
    {
        if ( method_exists( $this, 'getBlockContent' ) && class_exists( self::BLOCK_RENDERER_CLASS ) ) {
            $blocks = $this->getBlockContent();

            if ( [] !== $blocks ) {
                $guardKey = $this->renderingContentKey();

                // Already rendering this record further up the stack: a
                // `post-content` block inside its own content renders
                // nothing, as in WordPress.
                if ( isset( self::$renderingContentKeys[ $guardKey ] ) ) {
                    return '';
                }

                self::$renderingContentKeys[ $guardKey ] = true;

                try {
                    $renderer = app( self::BLOCK_RENDERER_CLASS );

                    return $renderer->render( $this->prepareBlocksForRender( $blocks ), $this->blockBindingContext() );
                } catch ( Throwable ) {
                    // Renderer installed but failed to resolve or render —
                    // fall through to the pre-rendered string fallback below.
                } finally {
                    unset( self::$renderingContentKeys[ $guardKey ] );
                }
            }
        }

        $content = $this->getAttribute( 'content' );

        // When `HasBlockContent` defaults to its own `content` column (i.e.
        // the host model has NOT set `$blockContentColumn`), the trait
        // registers an `array` cast on `content`. Legacy HTML records then
        // surface as `null` / an array on `getAttribute()`, not a string,
        // and the fallback below would silently drop them. Reach past the
        // cast to recover the raw value — but only when the raw bytes are
        // NOT JSON, otherwise we'd return the encoded block tree we just
        // tried to render through the renderer.
        if ( ! is_string( $content )
            && method_exists( $this, 'getBlockContentColumn' )
            && 'content' === $this->getBlockContentColumn()
        ) {
            $rawContent = $this->getRawOriginal( 'content' );

            if ( is_string( $rawContent ) ) {
                json_decode( $rawContent, true );

                if ( JSON_ERROR_NONE !== json_last_error() ) {
                    $content = $rawContent;
                }
            }
        }

        $rendered = is_string( $content ) ? $content : '';

        return $this->resolveDynamicContentTokens( $rendered );
    }

    /**
     * Run the model's block tree through visual-editor's entity passes
     * before it is rendered.
     *
     * `<x-ve-blocks :post="$post">` runs these for a template, but this
     * accessor renders the content on its own, so without them every
     * `post-title`, `post-excerpt` or `post-terms` block in a record's
     * content renders an empty wrapper. The order mirrors visual-editor's
     * `BlocksComponent`: synced patterns, then query loops (with the
     * model as host, so a related-posts loop finds its neighbours), then
     * `post-*` stamping. Template parts belong to templates and are not
     * inlined here. Each pass is skipped when its class is not installed,
     * and a pass that throws leaves the tree as it was.
     *
     * @since 2.15.3
     *
     * @param  array<int, array<string, mixed>>  $blocks
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareBlocksForRender( array $blocks ): array
    {
        $blockShape = class_exists( self::BLOCK_SHAPE_CLASS ) ? self::BLOCK_SHAPE_CLASS : null;

        // The passes read `name` / `attributes`; canonicalize legacy
        // `attrs` blocks first, as `BlocksComponent` does.
        if ( null !== $blockShape ) {
            $blocks = $blockShape::normalizeTree( $blocks );
        }

        $patternInliner = $this->contentPipelineService( self::PATTERN_INLINER_CLASS );

        if ( null !== $patternInliner ) {
            $blocks = $this->applyContentPass( $blocks, static function ( array $tree ) use ( $patternInliner, $blockShape ): array {
                $inlined = $patternInliner->inline( $tree );

                return null !== $blockShape ? $blockShape::normalizeTree( $inlined ) : $inlined;
            } );
        }

        $queryInliner = $this->contentPipelineService( self::QUERY_INLINER_CLASS );

        if ( null !== $queryInliner ) {
            $blocks = $this->applyContentPass( $blocks, fn ( array $tree ): array => $queryInliner->inline( $tree, $this ) );
        }

        $postResolver = $this->contentPipelineService( self::POST_RESOLVER_CLASS );

        if ( null !== $postResolver ) {
            $blocks = $this->applyContentPass(
                $blocks,
                fn ( array $tree ): array => $postResolver->stampTree( $this->blankNestedPostContent( $tree ), $this ),
            );
        }

        return $blocks;
    }

    /**
     * Resolve one of visual-editor's content passes from the container.
     *
     * Null when the class is not installed, so the pass is skipped.
     *
     * @since 2.15.3
     */
    protected function contentPipelineService( string $class ): ?object
    {
        return class_exists( $class ) ? app( $class ) : null;
    }

    /**
     * Apply one content pass, keeping the tree as it was if the pass throws.
     *
     * @since 2.15.3
     *
     * @param  array<int, array<string, mixed>>  $blocks
     *
     * @return array<int, array<string, mixed>>
     */
    protected function applyContentPass( array $blocks, callable $pass ): array
    {
        try {
            $result = $pass( $blocks );
        } catch ( Throwable ) {
            return $blocks;
        }

        return is_array( $result ) ? $result : $blocks;
    }

    /**
     * Stamp an empty `_resolvedContent` on every `post-content` block in
     * the model's own content that has not been resolved yet.
     *
     * The model's content can't contain itself. Pre-stamping keeps
     * `PostResolver` from printing the raw `content` column there, since
     * its own value wins on merge. Blocks a query loop already stamped
     * against another record keep their value.
     *
     * @since 2.15.3
     *
     * @param  array<int, array<string, mixed>>  $blocks
     *
     * @return array<int, array<string, mixed>>
     */
    protected function blankNestedPostContent( array $blocks ): array
    {
        foreach ( $blocks as $index => $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }

            $name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

            if ( in_array( $name, ['core/post-content', 'artisanpack/post-content'], true ) ) {
                $attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

                if ( ! array_key_exists( '_resolvedContent', $attributes ) ) {
                    $attributes['_resolvedContent'] = '';
                    $block['attributes']            = $attributes;
                }
            }

            if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                $block['innerBlocks'] = $this->blankNestedPostContent( $block['innerBlocks'] );
            }

            $blocks[ $index ] = $block;
        }

        return $blocks;
    }

    /**
     * Key identifying this record in {@see self::$renderingContentKeys}.
     *
     * Uses the table and primary key so a fresh instance of the same
     * record (a loop result, say) is caught too; unsaved models fall back
     * to the object id.
     *
     * @since 2.15.3
     */
    protected function renderingContentKey(): string
    {
        $key = $this->getKey();

        return null !== $key && '' !== $key
            ? $this->getTable() . ':' . $key
            : 'object:' . spl_object_id( $this );
    }

    /**
     * The binding context the model's own block tree resolves against.
     *
     * Block bindings (`custom_field`, `post_core`, `relation`) read from
     * the record that owns the content. This accessor runs before the
     * template's own render pass starts (visual-editor's `PostResolver`
     * reads `rendered_content` while stamping `core/post-content`), so
     * without an explicit context every binding in the body falls back
     * to its authored static value. Null when visual-editor's binding
     * layer isn't installed.
     *
     * @since 2.14.1
     */
    protected function blockBindingContext(): ?object
    {
        $contextClass = self::BINDING_CONTEXT_CLASS;

        return class_exists( $contextClass ) ? new $contextClass( $this ) : null;
    }

    /**
     * Pass rendered content through the dynamic-content resolver when the
     * DynamicContent module is loaded. Kept as a soft integration so this
     * trait doesn't hard-depend on the DynamicContent module.
     *
     * @since 2.4.0
     */
    protected function resolveDynamicContentTokens( string $rendered ): string
    {
        if ( '' === $rendered || ! function_exists( 'apRenderContent' ) ) {
            return $rendered;
        }

        try {
            return apRenderContent( $rendered, [
                'model'    => static::class,
                'model_id' => $this->getKey(),
            ] );
        } catch ( Throwable ) {
            // Never let a resolver failure hide the underlying content.
            return $rendered;
        }
    }
}

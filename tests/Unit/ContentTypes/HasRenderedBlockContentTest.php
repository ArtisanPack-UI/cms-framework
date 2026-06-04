<?php

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade {

    if ( ! class_exists( BlockRenderer::class, false ) ) {
        /**
         * Test-only stand-in for the visual-editor renderer-blade BlockRenderer.
         *
         * cms-framework does not depend on `visual-editor-renderer-blade`,
         * so the real class is not autoloadable in this suite. Declaring a
         * sibling class under the same FQCN lets the trait's `class_exists`
         * gate succeed in the "renderer installed" scenarios; tests that
         * exercise the renderer-missing path use a separate FQCN override
         * on the trait constant.
         */
        class BlockRenderer
        {
            /** @var array<int, array<string, mixed>>|null */
            public static ?array $lastTree = null;

            public static string $stubOutput = '<p>stubbed render</p>';

            public static bool $throwOnRender = false;

            /**
             * @param  array<int, array<string, mixed>>  $tree
             */
            public function render( array $tree ): string
            {
                self::$lastTree = $tree;

                if ( self::$throwOnRender ) {
                    throw new \RuntimeException( 'stub renderer failure' );
                }

                return self::$stubOutput;
            }
        }
    }
}

namespace {

    use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
    use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasRenderedBlockContent;
    use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
    use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
    use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
    use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
    use Illuminate\Database\Eloquent\Model;

    beforeEach( function (): void {
        $this->artisan( 'migrate', ['--database' => 'testing'] );

        BlockRenderer::$lastTree      = null;
        BlockRenderer::$stubOutput    = '<p>stubbed render</p>';
        BlockRenderer::$throwOnRender = false;
    } );

    test( 'rendered_content accessor walks block tree through the renderer', function (): void {
        $user = TestUser::create( [
            'name'     => 'Author',
            'email'    => 'author@example.com',
            'password' => 'password',
        ] );

        $blocks = [
            ['blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>Hi</p>'],
        ];

        $post = Post::create( [
            'title'     => 'Rendered Post',
            'slug'      => 'rendered-post',
            'author_id' => $user->id,
            'status'    => 'draft',
        ] );

        $post->setBlockContent( $blocks );
        $post->save();

        expect( $post->fresh()->rendered_content )->toBe( '<p>stubbed render</p>' );
        expect( BlockRenderer::$lastTree )->toBe( $blocks );
    } );

    test( 'renderContent() method returns the same value as the accessor', function (): void {
        $user = TestUser::create( [
            'name'     => 'Author',
            'email'    => 'author@example.com',
            'password' => 'password',
        ] );

        $page = Page::create( [
            'title'     => 'Rendered Page',
            'slug'      => 'rendered-page',
            'author_id' => $user->id,
            'status'    => 'draft',
        ] );

        $page->setBlockContent( [
            ['blockName' => 'core/heading', 'attrs' => ['level' => 2], 'innerHTML' => '<h2>Hello</h2>'],
        ] );
        $page->save();

        BlockRenderer::$stubOutput = '<h2>Hello</h2>';

        $fresh = Page::find( $page->id );

        expect( $fresh->renderContent() )->toBe( '<h2>Hello</h2>' );
        expect( $fresh->rendered_content )->toBe( '<h2>Hello</h2>' );
    } );

    test( 'rendered_content falls back to the content column when block tree is empty', function (): void {
        $user = TestUser::create( [
            'name'     => 'Author',
            'email'    => 'author@example.com',
            'password' => 'password',
        ] );

        $post = Post::create( [
            'title'     => 'Legacy Post',
            'slug'      => 'legacy-post',
            'author_id' => $user->id,
            'status'    => 'draft',
            'content'   => '<p>pre-rendered legacy HTML</p>',
        ] );

        expect( $post->rendered_content )->toBe( '<p>pre-rendered legacy HTML</p>' );
        // Renderer should NOT have been invoked — the block tree was empty,
        // so the trait short-circuits to the legacy content column.
        expect( BlockRenderer::$lastTree )->toBeNull();
    } );

    test( 'rendered_content returns an empty string when block tree and content are both empty', function (): void {
        $user = TestUser::create( [
            'name'     => 'Author',
            'email'    => 'author@example.com',
            'password' => 'password',
        ] );

        $post = Post::create( [
            'title'     => 'Empty Post',
            'slug'      => 'empty-post',
            'author_id' => $user->id,
            'status'    => 'draft',
        ] );

        expect( $post->rendered_content )->toBe( '' );
    } );

    test( 'renderContent() recovers legacy HTML when the block content column defaults to content (array cast)', function (): void {
        // Anonymous model that uses HasBlockContent WITHOUT overriding
        // $blockContentColumn — so the trait registers an `array` cast on
        // the `content` column. Legacy HTML stored there surfaces as a
        // non-string via the cast, but the raw original is the HTML bytes.
        $model = new class extends Model {
            use HasBlockContent;
            use HasRenderedBlockContent;
        };

        $model->setRawAttributes( [ 'content' => '<p>legacy HTML</p>' ], true );

        // Sanity-check: the cast layer hands back something that is NOT a
        // string (Laravel decodes invalid JSON to null), so the naive
        // `is_string` fallback would drop the legacy HTML.
        expect( is_string( $model->getAttribute( 'content' ) ) )->toBeFalse();

        expect( $model->renderContent() )->toBe( '<p>legacy HTML</p>' );
    } );

    test( 'renderContent() does NOT return raw JSON when the block content column defaults to content', function (): void {
        // Mirror of the prior test but with the raw column holding a JSON
        // block tree (i.e. the typical post-2.x record). The fallback must
        // NOT echo the encoded JSON back as "legacy HTML" — it should drop
        // to an empty string when the renderer is unavailable or returns
        // empty for an unknown reason.
        $model = new class extends Model {
            use HasBlockContent;
            use HasRenderedBlockContent;
        };

        $rawJson = '[{"name":"core/paragraph","attributes":{"content":"hi"},"innerBlocks":[]}]';
        $model->setRawAttributes( [ 'content' => $rawJson ], true );

        BlockRenderer::$throwOnRender = true;

        expect( $model->renderContent() )->toBe( '' );
    } );

    test( 'rendered_content swallows renderer failures and falls back to the content column', function (): void {
        $user = TestUser::create( [
            'name'     => 'Author',
            'email'    => 'author@example.com',
            'password' => 'password',
        ] );

        $post = Post::create( [
            'title'     => 'Failing Render',
            'slug'      => 'failing-render',
            'author_id' => $user->id,
            'status'    => 'draft',
            'content'   => '<p>fallback HTML</p>',
        ] );

        $post->setBlockContent( [
            ['blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>Hi</p>'],
        ] );
        $post->save();

        BlockRenderer::$throwOnRender = true;

        expect( $post->fresh()->rendered_content )->toBe( '<p>fallback HTML</p>' );
    } );
}

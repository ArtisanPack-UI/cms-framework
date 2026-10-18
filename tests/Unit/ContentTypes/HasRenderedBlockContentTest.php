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

            public static mixed $lastBindingContext = null;

            /**
             * @param  array<int, array<string, mixed>>  $tree
             */
            public function render( array $tree, mixed $bindingContext = null ): string
            {
                self::$lastTree           = $tree;
                self::$lastBindingContext = $bindingContext;

                if ( self::$throwOnRender ) {
                    throw new \RuntimeException( 'stub renderer failure' );
                }

                return self::$stubOutput;
            }
        }
    }
}

namespace ArtisanPackUI\VisualEditor\Services\Bindings {

    if ( ! class_exists( BindingContext::class, false ) ) {
        /**
         * Test-only stand-in for visual-editor's BindingContext, which is
         * not autoloadable in this suite for the same reason as the
         * BlockRenderer stub above.
         */
        class BindingContext
        {
            public function __construct( protected ?\Illuminate\Database\Eloquent\Model $model = null )
            {
            }

            public function model(): ?\Illuminate\Database\Eloquent\Model
            {
                return $this->model;
            }
        }
    }
}

namespace ArtisanPackUI\VisualEditor\Resources {

    if ( ! class_exists( PostResolver::class, false ) ) {
        /**
         * Test-only stand-in for visual-editor's PostResolver.
         *
         * Stamps a few `post-*` blocks the way the real resolver does,
         * including reading `rendered_content` for `post-content`, so
         * the recursion guard is exercised.
         */
        class PostResolver
        {
            public static mixed $lastPost = null;

            /**
             * @param  array<int, array<string, mixed>>  $tree
             *
             * @return array<int, array<string, mixed>>
             */
            public function stampTree( array $tree, object $post ): array
            {
                self::$lastPost = $post;

                $out = [];

                foreach ( $tree as $block ) {
                    $name       = $block['name'] ?? '';
                    $attributes = $block['attributes'] ?? [];

                    $resolved = match ( $name ) {
                        'artisanpack/post-title'   => ['_resolvedTitle' => (string) $post->title],
                        'artisanpack/post-excerpt' => ['_resolvedExcerpt' => (string) $post->excerpt],
                        'artisanpack/post-terms'   => ['_resolvedTermsByTaxonomy' => ['stub' => $post->getKey()]],
                        'artisanpack/post-content' => ['_resolvedContent' => (string) ( $post->rendered_content ?? '' )],
                        default                    => [],
                    };

                    if ( [] !== $resolved ) {
                        $block['attributes'] = array_merge( $resolved, $attributes );
                    }

                    if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                        $block['innerBlocks'] = $this->stampTree( $block['innerBlocks'], $post );
                    }

                    $out[] = $block;
                }

                return $out;
            }
        }
    }

    if ( ! class_exists( QueryInliner::class, false ) ) {
        /**
         * Test-only stand-in for visual-editor's QueryInliner. Records the
         * host post; `$expand` lets a test splice in loop results.
         */
        class QueryInliner
        {
            public static mixed $lastHostPost = null;

            public static ?\Closure $expand = null;

            /**
             * @param  array<int, array<string, mixed>>  $tree
             *
             * @return array<int, array<string, mixed>>
             */
            public function inline( array $tree, ?object $hostPost = null ): array
            {
                self::$lastHostPost = $hostPost;

                return null !== self::$expand ? ( self::$expand )( $tree, $hostPost ) : $tree;
            }
        }
    }

    if ( ! class_exists( PatternInliner::class, false ) ) {
        /**
         * Test-only stand-in for visual-editor's PatternInliner.
         */
        class PatternInliner
        {
            public static bool $called = false;

            /**
             * @param  array<int, array<string, mixed>>  $tree
             *
             * @return array<int, array<string, mixed>>
             */
            public function inline( array $tree ): array
            {
                self::$called = true;

                return $tree;
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
    use ArtisanPackUI\VisualEditor\Resources\PatternInliner;
    use ArtisanPackUI\VisualEditor\Resources\PostResolver;
    use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
    use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
    use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
    use Illuminate\Database\Eloquent\Model;

    beforeEach( function (): void {
        $this->artisan( 'migrate', ['--database' => 'testing'] );

        BlockRenderer::$lastTree      = null;
        BlockRenderer::$stubOutput    = '<p>stubbed render</p>';
        BlockRenderer::$throwOnRender = false;

        BlockRenderer::$lastBindingContext = null;

        PostResolver::$lastPost       = null;
        QueryInliner::$lastHostPost   = null;
        QueryInliner::$expand         = null;
        PatternInliner::$called       = false;
    } );

    /**
     * Create a saved post whose content is the given block tree.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    function postWithContent( array $blocks ): Post
    {
        $user = TestUser::create( [
            'name'     => 'Author',
            'email'    => 'author@example.com',
            'password' => 'password',
        ] );

        $post = Post::create( [
            'title'     => 'Core',
            'slug'      => 'core',
            'excerpt'   => 'The core package.',
            'author_id' => $user->id,
            'status'    => 'draft',
        ] );

        $post->setBlockContent( $blocks );
        $post->save();

        return $post->fresh();
    }

    test( 'post blocks in a record\'s own content are stamped with that record', function ( string $blockName, string $key, Closure $expected ): void {
        $post = postWithContent( [
            ['name' => 'artisanpack/group', 'attributes' => [], 'innerBlocks' => [
                ['name' => $blockName, 'attributes' => [], 'innerBlocks' => []],
            ]],
        ] );

        $post->rendered_content;

        expect( PostResolver::$lastPost )->toBe( $post );
        expect( BlockRenderer::$lastTree[0]['innerBlocks'][0]['attributes'][ $key ] )->toBe( $expected( $post ) );
    } )->with( [
        'post-title'   => ['artisanpack/post-title', '_resolvedTitle', fn ( Post $post ): string => 'Core'],
        'post-excerpt' => ['artisanpack/post-excerpt', '_resolvedExcerpt', fn ( Post $post ): string => 'The core package.'],
        'post-terms'   => ['artisanpack/post-terms', '_resolvedTermsByTaxonomy', fn ( Post $post ): array => ['stub' => $post->id]],
    ] );

    test( 'synced patterns and query loops in a record\'s content are inlined with the record as host', function (): void {
        $post = postWithContent( [
            ['name' => 'artisanpack/related-posts', 'attributes' => [], 'innerBlocks' => []],
        ] );

        $post->rendered_content;

        expect( PatternInliner::$called )->toBeTrue();
        expect( QueryInliner::$lastHostPost )->toBe( $post );
    } );

    test( 'a post-content block inside a record\'s own content renders empty instead of recursing', function (): void {
        $post = postWithContent( [
            ['name' => 'artisanpack/post-title', 'attributes' => [], 'innerBlocks' => []],
            ['name' => 'artisanpack/post-content', 'attributes' => [], 'innerBlocks' => []],
        ] );

        expect( $post->rendered_content )->toBe( '<p>stubbed render</p>' );
        expect( BlockRenderer::$lastTree[1]['attributes']['_resolvedContent'] )->toBe( '' );
    } );

    test( 'a loop that lists the record itself does not render its content again', function (): void {
        $post = postWithContent( [
            ['name' => 'artisanpack/query', 'attributes' => [], 'innerBlocks' => []],
        ] );

        // Mimic QueryInliner stamping each result: the loop's result is a
        // fresh instance of the same record with a post-content block.
        QueryInliner::$expand = function ( array $tree ) use ( $post ): array {
            $tree[0]['innerBlocks'] = app( PostResolver::class )->stampTree(
                [['name' => 'artisanpack/post-content', 'attributes' => [], 'innerBlocks' => []]],
                Post::find( $post->id ),
            );

            return $tree;
        };

        expect( $post->rendered_content )->toBe( '<p>stubbed render</p>' );
        expect( BlockRenderer::$lastTree[0]['innerBlocks'][0]['attributes']['_resolvedContent'] )->toBe( '' );
    } );

    test( 'a failing content pass leaves the tree for the renderer', function (): void {
        $blocks = [
            ['name' => 'artisanpack/paragraph', 'attributes' => ['content' => 'Hi'], 'innerBlocks' => []],
        ];

        $post = postWithContent( $blocks );

        QueryInliner::$expand = fn (): array => throw new RuntimeException( 'query failure' );

        expect( $post->rendered_content )->toBe( '<p>stubbed render</p>' );
        expect( BlockRenderer::$lastTree )->toBe( $blocks );
    } );

    test( 'the tree renders unchanged when visual-editor\'s content passes are not installed', function (): void {
        $post = new class extends Post {
            protected function contentPipelineService( string $class ): ?object
            {
                return null;
            }
        };

        $blocks = [
            ['name' => 'artisanpack/post-title', 'attributes' => [], 'innerBlocks' => []],
        ];

        $post->title = 'Unstamped';
        $post->setBlockContent( $blocks );

        expect( $post->renderContent() )->toBe( '<p>stubbed render</p>' );
        expect( BlockRenderer::$lastTree )->toBe( $blocks );
        expect( PostResolver::$lastPost )->toBeNull();
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

    test( 'rendered_content renders with a binding context for the model', function (): void {
        $user = TestUser::create( [
            'name'     => 'Author',
            'email'    => 'author@example.com',
            'password' => 'password',
        ] );

        $post = Post::create( [
            'title'     => 'Bound Post',
            'slug'      => 'bound-post',
            'author_id' => $user->id,
            'status'    => 'draft',
        ] );

        $post->setBlockContent( [
            ['blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>Hi</p>'],
        ] );
        $post->save();

        $fresh = $post->fresh();
        $fresh->rendered_content;

        expect( BlockRenderer::$lastBindingContext )->toBeInstanceOf( BindingContext::class );
        expect( BlockRenderer::$lastBindingContext->model() )->toBe( $fresh );
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

<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\CMSFramework\Modules\Blog\Services\QueryRuntime;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    $this->user = TestUser::create( [
        'name'     => 'Author',
        'email'    => 'author@example.com',
        'password' => 'password',
    ] );

    $this->runtime = app( QueryRuntime::class );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.query.taxQuery' );
} );

function publishedPost( int $userId, string $title, ?string $publishedAt = null, ?int $offsetMinutes = null ): Post
{
    $publishedAt = $publishedAt ?? now()->subDay()->toDateTimeString();

    if ( null !== $offsetMinutes ) {
        $publishedAt = now()->subMinutes( $offsetMinutes )->toDateTimeString();
    }

    return Post::create( [
        'title'        => $title,
        'slug'         => Str::slug( $title ) . '-' . uniqid(),
        'content'      => 'Body for ' . $title,
        'author_id'    => $userId,
        'status'       => 'published',
        'published_at' => $publishedAt,
    ] );
}

function publishedPage( int $userId, string $title, ?int $parent = null, int $order = 0 ): Page
{
    return Page::create( [
        'title'        => $title,
        'slug'         => Str::slug( $title ) . '-' . uniqid(),
        'content'      => 'Body for ' . $title,
        'author_id'    => $userId,
        'status'       => 'published',
        'published_at' => now()->subDay()->toDateTimeString(),
        'parent_id'    => $parent,
        'order'        => $order,
    ] );
}

/**
 * A custom-type model with a `status` column but no `published()` scope
 * and nothing in `$fillable`.
 */
class QueryRuntimeStatusColumnModel extends Model
{
    protected $table = 'pages';

    protected $guarded = [];
}

/**
 * A custom-type model whose `published()` scope is a `#[Scope]`-attributed
 * method (no `scope` prefix) that also hides scheduled rows.
 */
class QueryRuntimeAttributeScopeModel extends Model
{
    protected $table = 'pages';

    protected $guarded = [];

    #[Scope]
    protected function published( Builder $query ): void
    {
        $query->where( 'status', 'published' )->where( 'published_at', '<=', now() );
    }
}

/**
 * A custom-type model that lists `status` as fillable although its table
 * has no `status` column.
 */
class QueryRuntimeFillableStatusWithoutColumnModel extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'status'];
}

function draftPage( int $userId, string $title ): Page
{
    return Page::create( [
        'title'     => $title,
        'slug'      => Str::slug( $title ) . '-' . uniqid(),
        'content'   => 'Body for ' . $title,
        'author_id' => $userId,
        'status'    => 'draft',
    ] );
}

function registerQueryRuntimeType( string $slug, string $modelClass ): void
{
    ContentType::create( [
        'name'          => ucfirst( $slug ),
        'slug'          => $slug,
        'table_name'    => 'pages',
        'model_class'   => $modelClass,
        'hierarchical'  => false,
        'has_archive'   => true,
        'public'        => true,
        'show_in_admin' => true,
    ] );
}

it( 'resolves the post archive when postType is post', function (): void {
    $first  = publishedPost( $this->user->id, 'First', null, 60 );
    $second = publishedPost( $this->user->id, 'Second', null, 30 );

    $result = $this->runtime->resolve( ['postType' => 'post'] );

    expect( $result->total() )->toBe( 2 )
        ->and( $result->items()[0]->id )->toBe( $second->id )
        ->and( $result->items()[1]->id )->toBe( $first->id );
} );

it( 'resolves the page archive when postType is page', function (): void {
    $page = publishedPage( $this->user->id, 'About' );

    $result = $this->runtime->resolve( ['postType' => 'page'] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $page->id );
} );

it( 'caps perPage to the documented maximum', function (): void {
    for ( $i = 0; $i < 3; $i++ ) {
        publishedPost( $this->user->id, "Post {$i}" );
    }

    $result = $this->runtime->resolve( ['postType' => 'post', 'perPage' => 999999] );

    expect( $result->perPage() )->toBe( 100 );
} );

it( 'caps total result count when pages is set', function (): void {
    for ( $i = 0; $i < 5; $i++ ) {
        publishedPost( $this->user->id, "Post {$i}" );
    }

    $result = $this->runtime->resolve( ['postType' => 'post', 'perPage' => 100, 'pages' => 2] );

    expect( $result->total() )->toBe( 2 )
        ->and( $result->perPage() )->toBe( 2 );
} );

it( 'applies offset before paginating', function (): void {
    $first  = publishedPost( $this->user->id, 'First', null, 60 );
    $second = publishedPost( $this->user->id, 'Second', null, 30 );

    $result = $this->runtime->resolve( ['postType' => 'post', 'offset' => 1] );

    // Offset means "skip these, treat the rest as the result set". With
    // 2 published posts and offset=1, the older post is the one and
    // only result and `total()` reflects that.
    expect( $result->total() )->toBe( 1 )
        ->and( count( $result->items() ) )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $first->id );
} );

it( 'restricts results with postIn', function (): void {
    $keep = publishedPost( $this->user->id, 'Keep' );
    publishedPost( $this->user->id, 'Skip' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'postIn' => [$keep->id]] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $keep->id );
} );

it( 'treats include as an alias of postIn (#403)', function (): void {
    publishedPost( $this->user->id, 'Newest', null, 1 );
    $wanted = publishedPost( $this->user->id, 'Wanted', null, 60 );

    $result = $this->runtime->resolve( ['postType' => 'post', 'include' => [$wanted->id]] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $wanted->id );
} );

it( 'returns include rows in the requested order (#403)', function (): void {
    $newest = publishedPost( $this->user->id, 'Bravo', null, 1 );
    $oldest = publishedPost( $this->user->id, 'Alpha', null, 60 );
    $middle = publishedPost( $this->user->id, 'Charlie', null, 30 );

    $requested  = $this->runtime->resolve( ['postType' => 'post', 'include' => [$oldest->id, $newest->id, $middle->id]] );
    $byTitle    = $this->runtime->resolve( ['postType' => 'post', 'include' => [$middle->id, $newest->id], 'orderBy' => 'title', 'order' => 'asc'] );
    $postInOnly = $this->runtime->resolve( ['postType' => 'post', 'postIn' => [$oldest->id, $newest->id]] );

    expect( array_map( fn ( $post ) => $post->id, $requested->items() ) )->toBe( [$oldest->id, $newest->id, $middle->id] )
        ->and( array_map( fn ( $post ) => $post->id, $byTitle->items() ) )->toBe( [$newest->id, $middle->id] )
        ->and( array_map( fn ( $post ) => $post->id, $postInOnly->items() ) )->toBe( [$newest->id, $oldest->id] );
} );

it( 'excludes results with postNotIn', function (): void {
    $keep = publishedPost( $this->user->id, 'Keep' );
    $skip = publishedPost( $this->user->id, 'Skip' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'postNotIn' => [$skip->id]] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $keep->id );
} );

it( 'merges exclude into postNotIn', function (): void {
    $keep    = publishedPost( $this->user->id, 'Keep' );
    $skipA   = publishedPost( $this->user->id, 'Skip A' );
    $skipB   = publishedPost( $this->user->id, 'Skip B' );

    $result = $this->runtime->resolve( [
        'postType'  => 'post',
        'postNotIn' => [$skipA->id],
        'exclude'   => [$skipB->id],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $keep->id );
} );

it( 'restricts pages by parent ids', function (): void {
    $rootA  = publishedPage( $this->user->id, 'Root A' );
    $rootB  = publishedPage( $this->user->id, 'Root B' );
    $child  = publishedPage( $this->user->id, 'Child', $rootA->id );

    $result = $this->runtime->resolve( ['postType' => 'page', 'parents' => [$rootA->id]] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $child->id );
} );

it( 'orders posts by title asc', function (): void {
    $alpha = publishedPost( $this->user->id, 'Alpha' );
    $beta  = publishedPost( $this->user->id, 'Beta' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'orderBy' => 'title', 'order' => 'asc'] );

    expect( $result->items()[0]->id )->toBe( $alpha->id )
        ->and( $result->items()[1]->id )->toBe( $beta->id );
} );

it( 'orders posts by date desc by default', function (): void {
    $older = publishedPost( $this->user->id, 'Older', null, 120 );
    $newer = publishedPost( $this->user->id, 'Newer', null, 5 );

    $result = $this->runtime->resolve( ['postType' => 'post'] );

    expect( $result->items()[0]->id )->toBe( $newer->id )
        ->and( $result->items()[1]->id )->toBe( $older->id );
} );

it( 'orders pages by menu_order when requested', function (): void {
    $last  = publishedPage( $this->user->id, 'Last', null, 30 );
    $first = publishedPage( $this->user->id, 'First', null, 1 );

    $result = $this->runtime->resolve( [
        'postType' => 'page',
        'orderBy'  => 'menu_order',
        'order'    => 'asc',
    ] );

    expect( $result->items()[0]->id )->toBe( $first->id )
        ->and( $result->items()[1]->id )->toBe( $last->id );
} );

it( 'falls back to date when posts are ordered by menu_order', function (): void {
    $older = publishedPost( $this->user->id, 'Older', null, 120 );
    $newer = publishedPost( $this->user->id, 'Newer', null, 5 );

    $result = $this->runtime->resolve( ['postType' => 'post', 'orderBy' => 'menu_order', 'order' => 'desc'] );

    expect( $result->items()[0]->id )->toBe( $newer->id )
        ->and( $result->items()[1]->id )->toBe( $older->id );
} );

it( 'accepts rand as random order (#403)', function (): void {
    publishedPost( $this->user->id, 'One' );

    $runtime = new class( app( BlogManager::class ), app( PageManager::class ), app( ContentTypeManager::class ) ) extends QueryRuntime {
        public function sql( array $attributes ): string
        {
            $query = $this->baseQuery( 'post', $attributes );
            $this->applyOrdering( $query, 'post', $attributes );

            return strtolower( $query->toSql() );
        }
    };

    expect( $runtime->sql( ['orderBy' => 'rand'] ) )->toContain( 'random()' );
} );

it( 'pages on query-{queryId}-page when the loop has a queryId (#403)', function (): void {
    foreach ( range( 1, 3 ) as $minutes ) {
        publishedPost( $this->user->id, "Post {$minutes}", null, $minutes );
    }

    Paginator::currentPageResolver( fn ( string $pageName ): int => 'query-blog-page' === $pageName ? 2 : 1 );

    $named   = $this->runtime->resolve( ['postType' => 'post', 'perPage' => 2, 'queryId' => 'blog'] );
    $plain   = $this->runtime->resolve( ['postType' => 'post', 'perPage' => 2] );
    $offset  = $this->runtime->resolve( ['postType' => 'post', 'perPage' => 1, 'offset' => 1, 'queryId' => 'blog'] );

    expect( $named->currentPage() )->toBe( 2 )
        ->and( $named->getPageName() )->toBe( 'query-blog-page' )
        ->and( count( $named->items() ) )->toBe( 1 )
        ->and( $plain->currentPage() )->toBe( 1 )
        ->and( $plain->getPageName() )->toBe( 'page' )
        ->and( $offset->currentPage() )->toBe( 2 )
        ->and( $offset->getPageName() )->toBe( 'query-blog-page' );
} );

it( 'filters by author', function (): void {
    $other = TestUser::create( [
        'name'     => 'Other',
        'email'    => 'other@example.com',
        'password' => 'password',
    ] );

    $mine = publishedPost( $this->user->id, 'Mine' );
    publishedPost( $other->id, 'Theirs' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'author' => $this->user->id] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $mine->id );
} );

it( 'searches across title content excerpt', function (): void {
    $matched = publishedPost( $this->user->id, 'About distributed cache patterns' );
    publishedPost( $this->user->id, 'Pets' );

    $result = $this->runtime->resolve( ['postType' => 'post', 'search' => 'distributed'] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $matched->id );
} );

it( 'filters posts by category through taxQuery IN', function (): void {
    $cat   = PostCategory::create( ['name' => 'Laravel', 'slug' => 'laravel'] );
    $other = PostCategory::create( ['name' => 'PHP', 'slug' => 'php'] );

    $tagged = publishedPost( $this->user->id, 'Eloquent tips' );
    $tagged->categories()->attach( $cat->id );

    $untagged = publishedPost( $this->user->id, 'Random' );
    $untagged->categories()->attach( $other->id );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'category', 'terms' => [$cat->id], 'operator' => 'IN'],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $tagged->id );
} );

it( 'filters posts by post_tag through taxQuery IN', function (): void {
    $tag    = PostTag::create( ['name' => 'Cache', 'slug' => 'cache'] );
    $tagged = publishedPost( $this->user->id, 'Caching' );
    $tagged->tags()->attach( $tag->id );

    publishedPost( $this->user->id, 'Untagged' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'post_tag', 'terms' => [$tag->id]],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $tagged->id );
} );

it( 'ignores taxQuery operators outside the V1 IN subset', function (): void {
    $cat = PostCategory::create( ['name' => 'Laravel', 'slug' => 'laravel'] );
    $a   = publishedPost( $this->user->id, 'A' );
    $a->categories()->attach( $cat->id );
    publishedPost( $this->user->id, 'B' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'category', 'terms' => [$cat->id], 'operator' => 'NOT IN'],
    ] );

    // `NOT IN` is not implemented in V1 — the runtime drops the
    // constraint entirely, so both posts come back.
    expect( $result->total() )->toBe( 2 );
} );

it( 'drops a built-in taxQuery constraint whose operator is not a string', function (): void {
    $cat = PostCategory::create( ['name' => 'Laravel', 'slug' => 'laravel'] );
    $a   = publishedPost( $this->user->id, 'A' );
    $a->categories()->attach( $cat->id );
    publishedPost( $this->user->id, 'B' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'category', 'terms' => [$cat->id], 'operator' => false],
    ] );

    expect( $result->total() )->toBe( 2 );
} );

it( 'treats a null taxQuery operator as the IN default', function (): void {
    $cat = PostCategory::create( ['name' => 'Laravel', 'slug' => 'laravel'] );
    $a   = publishedPost( $this->user->id, 'A' );
    $a->categories()->attach( $cat->id );
    publishedPost( $this->user->id, 'B' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'category', 'terms' => [$cat->id], 'operator' => null],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $a->id );
} );

it( 'does not run the taxQuery filter for mapped taxonomies', function ( string $taxonomy ): void {
    $calls = 0;

    addFilter( 'ap.cmsFramework.query.taxQuery', function ( Builder $query ) use ( &$calls ): Builder {
        $calls++;

        return $query;
    } );

    $cat = PostCategory::create( ['name' => 'Laravel', 'slug' => 'laravel'] );
    $tag = PostTag::create( ['name' => 'Cache', 'slug' => 'cache'] );

    $matched = publishedPost( $this->user->id, 'Matched' );
    $matched->categories()->attach( $cat->id );
    $matched->tags()->attach( $tag->id );

    publishedPost( $this->user->id, 'Unmatched' );

    $terms = in_array( $taxonomy, ['category', 'post_category'], true ) ? [$cat->id] : [$tag->id];

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => $taxonomy, 'terms' => $terms],
    ] );

    expect( $calls )->toBe( 0 )
        ->and( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $matched->id );
} )->with( ['category', 'post_category', 'post_tag', 'tag'] );

it( 'does not run the taxQuery filter for a mapped taxonomy with an operator outside IN', function (): void {
    $calls = 0;

    addFilter( 'ap.cmsFramework.query.taxQuery', function ( Builder $query ) use ( &$calls ): Builder {
        $calls++;

        return $query;
    } );

    $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'category', 'terms' => [1], 'operator' => 'NOT IN'],
    ] );

    expect( $calls )->toBe( 0 );
} );

it( 'leaves the query unfiltered for an unmapped taxonomy with no listener', function (): void {
    publishedPost( $this->user->id, 'A' );
    publishedPost( $this->user->id, 'B' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'packages', 'terms' => [7]],
    ] );

    expect( $result->total() )->toBe( 2 );
} );

it( 'leaves the query unfiltered when listeners ignore the unmapped taxonomy', function (): void {
    addFilter(
        'ap.cmsFramework.query.taxQuery',
        fn ( Builder $query, string $taxonomy ): Builder => 'other' === $taxonomy ? $query->whereRaw( '1 = 0' ) : $query,
    );

    publishedPost( $this->user->id, 'A' );
    publishedPost( $this->user->id, 'B' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'packages', 'terms' => [7]],
    ] );

    expect( $result->total() )->toBe( 2 );
} );

it( 'passes an unmapped taxonomy to the taxQuery filter', function ( string $postType, string $model ): void {
    registerQueryRuntimeType( 'docs', Page::class );

    $received = null;

    addFilter(
        'ap.cmsFramework.query.taxQuery',
        function ( Builder $query, string $taxonomy, array $terms, string $operator, string $postType ) use ( &$received ): Builder {
            $received = [$query->getModel()::class, $taxonomy, $terms, $operator, $postType];

            return $query;
        },
    );

    $this->runtime->resolve( [
        'postType' => $postType,
        'taxQuery' => ['taxonomy' => 'packages', 'terms' => ['7', 9]],
    ] );

    expect( $received )->toBe( [$model, 'packages', [7, 9], 'IN', $postType] );
} )->with( [
    'posts'       => ['post', Post::class],
    'pages'       => ['page', Page::class],
    'custom type' => ['docs', Page::class],
] );

it( 'passes operators outside IN to the taxQuery filter so a listener can implement them', function (): void {
    addFilter(
        'ap.cmsFramework.query.taxQuery',
        fn ( Builder $query, string $taxonomy, array $terms, string $operator ): Builder => 'NOT IN' === $operator
            ? $query->whereNotIn( 'id', $terms )
            : $query,
    );

    $excluded = publishedPost( $this->user->id, 'Excluded' );
    $kept     = publishedPost( $this->user->id, 'Kept' );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'packages', 'terms' => [$excluded->id], 'operator' => 'not in'],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $kept->id );
} );

it( 'normalizes the operator it passes to the taxQuery filter', function (): void {
    $received = null;

    addFilter(
        'ap.cmsFramework.query.taxQuery',
        function ( Builder $query, string $taxonomy, array $terms, string $operator ) use ( &$received ): Builder {
            $received = [$taxonomy, $operator];

            return $query;
        },
    );

    $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'product.cat', 'terms' => [7], 'operator' => "  not \t exists "],
    ] );

    expect( $received )->toBe( ['product.cat', 'NOT EXISTS'] );
} );

it( 'does not run the taxQuery filter for a malformed taxonomy or an unknown operator', function ( array $taxQuery ): void {
    $calls = 0;

    addFilter( 'ap.cmsFramework.query.taxQuery', function ( Builder $query ) use ( &$calls ): Builder {
        $calls++;

        return $query;
    } );

    $this->runtime->resolve( ['postType' => 'post', 'taxQuery' => $taxQuery] );

    expect( $calls )->toBe( 0 );
} )->with( [
    'markup in taxonomy'    => [['taxonomy' => '<b>packages</b>', 'terms' => [7]]],
    'markup in operator'    => [['taxonomy' => 'packages', 'terms' => [7], 'operator' => '<i>in</i>']],
    'unknown operator'      => [['taxonomy' => 'packages', 'terms' => [7], 'operator' => 'BETWEEN']],
    'sql in operator'       => [['taxonomy' => 'packages', 'terms' => [7], 'operator' => 'IN) OR 1=1 --']],
    'space in taxonomy'     => [['taxonomy' => 'my packages', 'terms' => [7]]],
    'taxonomy over 64 long' => [['taxonomy' => str_repeat( 'a', 65 ), 'terms' => [7]]],
    'trailing newline'      => [['taxonomy' => "packages\n", 'terms' => [7]]],
    'false operator'        => [['taxonomy' => 'packages', 'terms' => [7], 'operator' => false]],
    'array operator'        => [['taxonomy' => 'packages', 'terms' => [7], 'operator' => ['IN']]],
] );

it( 'does not run the taxQuery filter without a taxonomy or terms', function ( array $taxQuery ): void {
    $calls = 0;

    addFilter( 'ap.cmsFramework.query.taxQuery', function ( Builder $query ) use ( &$calls ): Builder {
        $calls++;

        return $query;
    } );

    $this->runtime->resolve( ['postType' => 'post', 'taxQuery' => $taxQuery] );

    expect( $calls )->toBe( 0 );
} )->with( [
    'no terms'            => [['taxonomy' => 'packages', 'terms' => []]],
    'no taxonomy'         => [['terms' => [7]]],
    'non-string taxonomy' => [['taxonomy' => 7, 'terms' => [7]]],
] );

it( 'applies a taxQuery listener constraint with ordering and pagination intact', function (): void {
    $oldest = publishedPost( $this->user->id, 'Oldest', null, 30 );
    $middle = publishedPost( $this->user->id, 'Middle', null, 20 );
    $newest = publishedPost( $this->user->id, 'Newest', null, 10 );

    publishedPost( $this->user->id, 'Other term', null, 5 );

    $termPosts = [7 => [$oldest->id, $middle->id, $newest->id]];

    addFilter(
        'ap.cmsFramework.query.taxQuery',
        fn ( Builder $query, string $taxonomy, array $terms ): Builder => 'packages' === $taxonomy
            ? $query->whereIn( 'id', $termPosts[ $terms[0] ] )
            : $query,
    );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'perPage'  => 2,
        'order'    => 'asc',
        'taxQuery' => ['taxonomy' => 'packages', 'terms' => [7]],
    ] );

    expect( $result->total() )->toBe( 3 )
        ->and( $result->lastPage() )->toBe( 2 )
        ->and( array_map( fn ( Post $post ): int => $post->id, $result->items() ) )->toBe( [$oldest->id, $middle->id] );
} );

it( 'applies every taxQuery listener when each returns the builder', function (): void {
    $first  = publishedPost( $this->user->id, 'First' );
    $second = publishedPost( $this->user->id, 'Second' );
    $third  = publishedPost( $this->user->id, 'Third' );

    addFilter(
        'ap.cmsFramework.query.taxQuery',
        fn ( Builder $query ): Builder => $query->whereIn( 'id', [$first->id, $second->id] ),
    );
    addFilter(
        'ap.cmsFramework.query.taxQuery',
        fn ( Builder $query ): Builder => $query->whereIn( 'id', [$second->id, $third->id] ),
    );

    $result = $this->runtime->resolve( [
        'postType' => 'post',
        'taxQuery' => ['taxonomy' => 'packages', 'terms' => [7]],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $second->id );
} );

it( 'keeps the published constraint when a taxQuery listener constrains the query', function (): void {
    $published = publishedPage( $this->user->id, 'Published' );
    $draft     = draftPage( $this->user->id, 'Draft' );

    addFilter(
        'ap.cmsFramework.query.taxQuery',
        fn ( Builder $query ): Builder => $query->whereIn( 'id', [$published->id, $draft->id] ),
    );

    $result = $this->runtime->resolve( [
        'postType' => 'page',
        'taxQuery' => ['taxonomy' => 'packages', 'terms' => [7]],
    ] );

    expect( $result->total() )->toBe( 1 )
        ->and( $result->items()[0]->id )->toBe( $published->id );
} );

it( 'throws when an unknown postType is requested', function (): void {
    $this->runtime->resolve( ['postType' => 'something-unregistered'] );
} )->throws( InvalidArgumentException::class, 'unknown post type' );

it( 'excludes draft pages by default', function (): void {
    $published = publishedPage( $this->user->id, 'Live' );
    draftPage( $this->user->id, 'Hidden' );

    $result = $this->runtime->resolve( ['postType' => 'page'] );

    expect( collect( $result->items() )->pluck( 'id' )->all() )->toBe( [$published->id] );
} );

it( 'excludes draft records of a custom type with a published scope', function (): void {
    registerQueryRuntimeType( 'docs', Page::class );
    $published = publishedPage( $this->user->id, 'Live' );
    draftPage( $this->user->id, 'Hidden' );

    $result = $this->runtime->resolve( ['postType' => 'docs'] );

    expect( collect( $result->items() )->pluck( 'id' )->all() )->toBe( [$published->id] );
} );

it( 'excludes draft records of a custom type with only a status column', function (): void {
    registerQueryRuntimeType( 'notes', QueryRuntimeStatusColumnModel::class );
    $published = publishedPage( $this->user->id, 'Live' );
    draftPage( $this->user->id, 'Hidden' );

    $result = $this->runtime->resolve( ['postType' => 'notes'] );

    expect( collect( $result->items() )->pluck( 'id' )->all() )->toBe( [$published->id] );
} );

it( 'uses a #[Scope] published scope on a custom type, so scheduled records stay hidden', function (): void {
    registerQueryRuntimeType( 'events', QueryRuntimeAttributeScopeModel::class );
    $published = publishedPage( $this->user->id, 'Live' );
    $scheduled = publishedPage( $this->user->id, 'Tomorrow' );
    $scheduled->forceFill( ['published_at' => now()->addDay()] )->save();

    $result = $this->runtime->resolve( ['postType' => 'events'] );

    expect( collect( $result->items() )->pluck( 'id' )->all() )->toBe( [$published->id] );
} );

it( 'does not filter on status when the column is only fillable, not in the table', function (): void {
    registerQueryRuntimeType( 'people', QueryRuntimeFillableStatusWithoutColumnModel::class );

    $result = $this->runtime->resolve( ['postType' => 'people'] );

    expect( collect( $result->items() )->pluck( 'id' )->all() )->toBe( [$this->user->id] );
} );

it( 'ignores a status attribute passed to resolve', function ( string $postType ): void {
    registerQueryRuntimeType( 'docs', Page::class );
    publishedPost( $this->user->id, 'Live post' );
    publishedPage( $this->user->id, 'Live page' );
    $draftPost = Post::create( [
        'title'     => 'Draft post',
        'slug'      => 'draft-post',
        'author_id' => $this->user->id,
        'status'    => 'draft',
    ] );
    $draftPage = draftPage( $this->user->id, 'Draft page' );

    $ids = collect( $this->runtime->resolve( ['postType' => $postType, 'status' => 'draft'] )->items() )->pluck( 'id' );

    expect( $ids )->toHaveCount( 1 )
        ->and( $ids->all() )->not->toContain( 'post' === $postType ? $draftPost->id : $draftPage->id );
} )->with( ['post', 'page', 'docs'] );

it( 'honours a status attribute passed to resolveTrusted', function (): void {
    registerQueryRuntimeType( 'docs', Page::class );
    registerQueryRuntimeType( 'notes', QueryRuntimeStatusColumnModel::class );
    publishedPost( $this->user->id, 'Live post' );
    publishedPage( $this->user->id, 'Live page' );
    $draftPost = Post::create( [
        'title'     => 'Draft post',
        'slug'      => 'draft-post',
        'author_id' => $this->user->id,
        'status'    => 'draft',
    ] );
    $draftPage = draftPage( $this->user->id, 'Draft page' );

    $ids = fn ( string $postType ): array => collect(
        $this->runtime->resolveTrusted( ['postType' => $postType, 'status' => 'draft'] )->items(),
    )->pluck( 'id' )->all();

    expect( $ids( 'post' ) )->toBe( [$draftPost->id] )
        ->and( $ids( 'page' ) )->toBe( [$draftPage->id] )
        ->and( $ids( 'docs' ) )->toBe( [$draftPage->id] )
        ->and( $ids( 'notes' ) )->toBe( [$draftPage->id] );
} );

it( 'lists published content from resolveTrusted when no status is given', function (): void {
    $published = publishedPage( $this->user->id, 'Live' );
    draftPage( $this->user->id, 'Hidden' );

    $result = $this->runtime->resolveTrusted( ['postType' => 'page'] );

    expect( collect( $result->items() )->pluck( 'id' )->all() )->toBe( [$published->id] );
} );

it( 'keeps excluding draft and scheduled posts', function (): void {
    $published = publishedPost( $this->user->id, 'Live' );
    Post::create( [
        'title'     => 'Draft',
        'slug'      => 'draft',
        'author_id' => $this->user->id,
        'status'    => 'draft',
    ] );
    publishedPost( $this->user->id, 'Scheduled', now()->addDay()->toDateTimeString() );

    $result = $this->runtime->resolve( ['postType' => 'post'] );

    expect( collect( $result->items() )->pluck( 'id' )->all() )->toBe( [$published->id] );
} );

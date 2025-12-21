<?php

declare( strict_types = 1 );

/**
 * Post Controller for the CMS Framework Blog Module.
 *
 * This controller handles CRUD operations for posts including listing,
 * creating, showing, updating, and deleting post records through API endpoints.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests\PostRequest;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources\PostResource;
use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * API controller for managing posts.
 *
 * Provides RESTful API endpoints for post management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
class PostController extends Controller
{
    use AuthorizesRequests;

    /**
     * The blog manager instance.
     *
     * @since 1.0.0
     */
    protected BlogManager $blogManager;

    /**
     * Create a new controller instance.
     *
     * @since 1.0.0
     */
    public function __construct( BlogManager $blogManager )
    {
        $this->blogManager = $blogManager;
    }

    /**
     * Display a listing of posts.
     *
     * Retrieves a paginated list of posts and returns them as a JSON resource collection.
     *
     * @since 1.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of post resources.
     */
    public function index( Request $request ): AnonymousResourceCollection
    {
        $this->authorize( 'viewAny', Post::class );

        $filters = $request->only( ['status', 'category', 'tag', 'author', 'year', 'month', 'search'] );
        $posts   = $this->blogManager->getArchiveQuery( $filters )->paginate( 15 );

        return PostResource::collection( $posts );
    }

    /**
     * Store a newly created post.
     *
     * Validates the incoming request data and creates a new post with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 1.0.0
     *
     * @param  PostRequest  $request  The HTTP request containing post data.
     *
     * @return JsonResponse The JSON response containing the created post resource.
     */
    public function store( PostRequest $request ): JsonResponse
    {
        $this->authorize( 'create', Post::class );

        $validated  = $request->validated();
        $categories = $validated['categories'] ?? [];
        $tags       = $validated['tags'] ?? [];

        unset( $validated['categories'], $validated['tags'] );

        $post = Post::create( $validated );

        // Sync categories and tags
        if ( ! empty( $categories ) ) {
            $post->categories()->sync( $categories );
        }

        if ( ! empty( $tags ) ) {
            $post->tags()->sync( $tags );
        }

        $post->load( ['author', 'categories', 'tags'] );

        return response()->json( new PostResource( $post ), 201 );
    }

    /**
     * Display the specified post.
     *
     * Retrieves a single post by ID and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The ID of the post to retrieve.
     *
     * @return PostResource The post resource.
     */
    public function show( int $id ): PostResource
    {
        $post = Post::with( ['author', 'categories', 'tags'] )->findOrFail( $id );
        $this->authorize( 'view', $post );

        return new PostResource( $post );
    }

    /**
     * Update the specified post.
     *
     * Validates the incoming request data and updates the post with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 1.0.0
     *
     * @param  PostRequest  $request  The HTTP request containing updated post data.
     * @param  int  $id  The ID of the post to update.
     *
     * @return PostResource The updated post resource.
     */
    public function update( PostRequest $request, int $id ): PostResource
    {
        $post = Post::findOrFail( $id );
        $this->authorize( 'update', $post );

        $validated  = $request->validated();
        $categories = $validated['categories'] ?? null;
        $tags       = $validated['tags'] ?? null;

        unset( $validated['categories'], $validated['tags'] );

        $post->update( $validated );

        // Sync categories and tags if provided
        if ( null !== $categories ) {
            $post->categories()->sync( $categories );
        }

        if ( null !== $tags ) {
            $post->tags()->sync( $tags );
        }

        $post->load( ['author', 'categories', 'tags'] );

        return new PostResource( $post );
    }

    /**
     * Remove the specified post.
     *
     * Deletes a post from the database and returns a successful response
     * with no content.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The ID of the post to delete.
     *
     * @return Response A response with 204 status code.
     */
    public function destroy( int $id ): Response
    {
        $post = Post::findOrFail( $id );
        $this->authorize( 'delete', $post );

        $post->delete();

        return response()->noContent();
    }

    /**
     * Get posts by date archive.
     *
     * @since 1.0.0
     *
     * @param  int  $year  Year to filter by.
     * @param  int|null  $month  Month to filter by (optional).
     * @param  int|null  $day  Day to filter by (optional).
     */
    public function archiveByDate( int $year, ?int $month = null, ?int $day = null ): AnonymousResourceCollection
    {
        $this->authorize( 'viewAny', Post::class );

        $posts = $this->blogManager->getPostsByDate( $year, $month, $day );

        return PostResource::collection( $posts );
    }

    /**
     * Get posts by author.
     *
     * @since 1.0.0
     *
     * @param  int  $authorId  Author ID to filter by.
     */
    public function archiveByAuthor( int $authorId ): AnonymousResourceCollection
    {
        $this->authorize( 'viewAny', Post::class );

        $posts = $this->blogManager->getPostsByAuthor( $authorId );

        return PostResource::collection( $posts );
    }

    /**
     * Get posts by category.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Category slug to filter by.
     */
    public function archiveByCategory( string $slug ): AnonymousResourceCollection
    {
        $this->authorize( 'viewAny', Post::class );

        $posts = $this->blogManager->getPostsByCategory( $slug );

        return PostResource::collection( $posts );
    }

    /**
     * Get posts by tag.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Tag slug to filter by.
     */
    public function archiveByTag( string $slug ): AnonymousResourceCollection
    {
        $this->authorize( 'viewAny', Post::class );

        $posts = $this->blogManager->getPostsByTag( $slug );

        return PostResource::collection( $posts);
    }
}

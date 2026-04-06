<?php

declare(strict_types=1);

/**
 * Post Controller for the CMS Framework Blog Module.
 *
 * This controller handles CRUD operations for posts including listing,
 * creating, showing, updating, and deleting post records through API endpoints.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Controllers\Concerns\HasIncludableRelationships;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests\BulkPostRequest;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests\PostRequest;
use ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources\PostResource;
use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * API controller for managing posts.
 *
 * Provides RESTful API endpoints for post management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
#[Group('Posts', weight: 1)]
class PostController extends Controller
{
    use AuthorizesRequests;
    use HasIncludableRelationships;

    /**
     * The relationships that can be included via the include query parameter.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $includableRelationships = ['author', 'categories', 'tags'];

    /**
     * The default relationships to load when no include parameter is provided.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $defaultIncludes = ['author', 'categories', 'tags'];

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
    public function __construct(BlogManager $blogManager)
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
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $filters  = $request->only(['status', 'category', 'tag', 'author', 'year', 'month', 'search']);
        $includes = $this->getRequestedIncludes($request);
        $posts    = $this->blogManager->getArchiveQuery($filters)->with($includes)->paginate(15);

        return PostResource::collection($posts);
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
    public function store(PostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $validated  = $request->validated();
        $categories = $validated['categories'] ?? [];
        $tags       = $validated['tags'] ?? [];

        unset($validated['categories'], $validated['tags']);

        $post = Post::create($validated);

        // Sync categories and tags
        if (! empty($categories)) {
            $post->categories()->sync($categories);
        }

        if (! empty($tags)) {
            $post->tags()->sync($tags);
        }

        $post->load($this->getRequestedIncludes($request));

        return response()->json(new PostResource($post), 201);
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
    public function show(Request $request, int $id): PostResource
    {
        $post = Post::findOrFail($id);
        $this->authorize('view', $post);

        $post->load($this->getRequestedIncludes($request));

        return new PostResource($post);
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
    public function update(PostRequest $request, int $id): PostResource
    {
        $post = Post::findOrFail($id);
        $this->authorize('update', $post);

        $validated  = $request->validated();
        $categories = $validated['categories'] ?? null;
        $tags       = $validated['tags'] ?? null;

        unset($validated['categories'], $validated['tags']);

        $post->update($validated);

        // Sync categories and tags if provided
        if (null !== $categories) {
            $post->categories()->sync($categories);
        }

        if (null !== $tags) {
            $post->tags()->sync($tags);
        }

        $post->load($this->getRequestedIncludes($request));

        return new PostResource($post);
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
    public function destroy(int $id): Response
    {
        $post = Post::findOrFail($id);
        $this->authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }

    /**
     * Perform a bulk action on multiple posts.
     *
     * Processes the requested action on each post individually, respecting
     * authorization policies. Returns a summary of successes and failures.
     *
     * @since 1.1.0
     *
     * @param  BulkPostRequest  $request  The validated bulk action request.
     *
     * @return JsonResponse Summary with processed count, failed count, and error details.
     */
    public function bulk(BulkPostRequest $request): JsonResponse
    {
        $action    = $request->validated('action');
        $ids       = $request->validated('ids');
        $processed = 0;
        $errors    = [];

        $posts = Post::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $post = $posts->get($id);

            if (null === $post) {
                $errors[$id] = __('Post not found.');

                continue;
            }

            $policyMethod = $this->getBulkPolicyMethod($action);

            if (! $request->user()->can($policyMethod, $post)) {
                $errors[$id] = __('You do not have permission to :action this post.', ['action' => $action]);

                continue;
            }

            try {
                $this->executeBulkAction($action, $post);
                $processed++;
            } catch (Throwable $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        return response()->json([
            'processed' => $processed,
            'failed'    => count($errors),
            'errors'    => $errors,
        ]);
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
    public function archiveByDate(Request $request, int $year, ?int $month = null, ?int $day = null): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $posts = $this->blogManager->getPostsByDate($year, $month, $day);
        $posts->load($this->getRequestedIncludes($request));

        return PostResource::collection($posts);
    }

    /**
     * Get posts by author.
     *
     * @since 1.0.0
     *
     * @param  int  $authorId  Author ID to filter by.
     */
    public function archiveByAuthor(Request $request, int $authorId): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $posts = $this->blogManager->getPostsByAuthor($authorId);
        $posts->load($this->getRequestedIncludes($request));

        return PostResource::collection($posts);
    }

    /**
     * Get posts by category.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Category slug to filter by.
     */
    public function archiveByCategory(Request $request, string $slug): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $posts = $this->blogManager->getPostsByCategory($slug);
        $posts->load($this->getRequestedIncludes($request));

        return PostResource::collection($posts);
    }

    /**
     * Get posts by tag.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Tag slug to filter by.
     */
    public function archiveByTag(Request $request, string $slug): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $posts = $this->blogManager->getPostsByTag($slug);
        $posts->load($this->getRequestedIncludes($request));

        return PostResource::collection($posts);
    }

    /**
     * Get the policy method for a bulk action.
     *
     * @since 1.1.0
     *
     * @param  string  $action  The bulk action name.
     *
     * @return string The policy method name.
     */
    protected function getBulkPolicyMethod(string $action): string
    {
        return match ($action) {
            'delete', 'archive' => 'delete',
            'publish'           => 'publish',
            'draft'             => 'update',
        };
    }

    /**
     * Execute a bulk action on a single post.
     *
     * @since 1.1.0
     *
     * @param  string  $action  The bulk action to perform.
     * @param  Post  $post  The post to perform the action on.
     */
    protected function executeBulkAction(string $action, Post $post): void
    {
        match ($action) {
            'delete'  => $post->delete(),
            'publish' => $post->update([
                'status'       => ContentStatus::Published->value,
                'published_at' => $post->published_at ?? now(),
            ]),
            'draft'   => $post->update([
                'status'       => ContentStatus::Draft->value,
                'published_at' => null,
            ]),
            'archive' => $post->delete(),
        };
    }
}

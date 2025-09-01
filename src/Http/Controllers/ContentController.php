<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\ContentRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\ContentResource;
use ArtisanPackUI\CMSFramework\Models\Content;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

/**
 * Content Controller.
 *
 * Handles CRUD operations for content items in the CMS Framework.
 * Manages content creation, retrieval, updating, and deletion with term associations.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.1.0
 */
#[OA\Schema(
    schema: "Content",
    type: "object",
    description: "Content item in the CMS",
    required: ["title", "content", "content_type", "status"],
    properties: [
        new OA\Property(property: "id", type: "integer", description: "Unique identifier", example: 1),
        new OA\Property(property: "title", type: "string", description: "Content title", example: "My Blog Post"),
        new OA\Property(property: "content", type: "string", description: "Content body", example: "This is the content body..."),
        new OA\Property(property: "excerpt", type: "string", nullable: true, description: "Content excerpt", example: "Brief summary..."),
        new OA\Property(property: "content_type", type: "string", description: "Type of content", example: "post"),
        new OA\Property(property: "status", type: "string", description: "Content status", enum: ["draft", "published", "archived"], example: "published"),
        new OA\Property(property: "slug", type: "string", nullable: true, description: "URL-friendly slug", example: "my-blog-post"),
        new OA\Property(property: "meta_title", type: "string", nullable: true, description: "SEO meta title", example: "My Blog Post - SEO Title"),
        new OA\Property(property: "meta_description", type: "string", nullable: true, description: "SEO meta description", example: "SEO description for search engines"),
        new OA\Property(property: "featured_image", type: "string", nullable: true, description: "Featured image URL", example: "/images/featured.jpg"),
        new OA\Property(property: "author_id", type: "integer", nullable: true, description: "Author user ID", example: 1),
        new OA\Property(property: "published_at", type: "string", format: "date-time", nullable: true, description: "Publication date", example: "2025-08-26T10:00:00Z"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Creation timestamp", example: "2025-08-26T10:00:00Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Last update timestamp", example: "2025-08-26T10:00:00Z"),
        new OA\Property(
            property: "terms",
            type: "array",
            description: "Associated taxonomy terms",
            items: new OA\Items(type: "integer", description: "Term ID")
        )
    ]
)]
#[OA\Schema(
    schema: "ContentRequest",
    type: "object",
    description: "Content creation/update request",
    required: ["title", "content", "content_type", "status"],
    properties: [
        new OA\Property(property: "title", type: "string", description: "Content title", example: "My Blog Post"),
        new OA\Property(property: "content", type: "string", description: "Content body", example: "This is the content body..."),
        new OA\Property(property: "excerpt", type: "string", nullable: true, description: "Content excerpt", example: "Brief summary..."),
        new OA\Property(property: "content_type", type: "string", description: "Type of content", example: "post"),
        new OA\Property(property: "status", type: "string", description: "Content status", enum: ["draft", "published", "archived"], example: "published"),
        new OA\Property(property: "slug", type: "string", nullable: true, description: "URL-friendly slug", example: "my-blog-post"),
        new OA\Property(property: "meta_title", type: "string", nullable: true, description: "SEO meta title", example: "My Blog Post - SEO Title"),
        new OA\Property(property: "meta_description", type: "string", nullable: true, description: "SEO meta description", example: "SEO description for search engines"),
        new OA\Property(property: "featured_image", type: "string", nullable: true, description: "Featured image URL", example: "/images/featured.jpg"),
        new OA\Property(property: "author_id", type: "integer", nullable: true, description: "Author user ID", example: 1),
        new OA\Property(property: "published_at", type: "string", format: "date-time", nullable: true, description: "Publication date", example: "2025-08-26T10:00:00Z"),
        new OA\Property(
            property: "terms",
            type: "array",
            nullable: true,
            description: "Associated taxonomy terms",
            items: new OA\Items(type: "integer", description: "Term ID")
        )
    ]
)]
class ContentController
{
    use AuthorizesRequests;

    /**
     * Display a listing of all content items.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection Collection of content resources.
     */
    #[OA\Get(
        path: "/api/cms/content",
        operationId: "getContentList",
        description: "Retrieve a list of all content items. Requires appropriate permissions to view content.",
        summary: "List all content items",
        security: [["sanctum" => []]],
        tags: ["Content Management"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful operation",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Content")
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden - insufficient permissions",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 429,
                description: "Rate limit exceeded (60 requests per minute)",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function index()
    {
        $this->authorize( 'viewAny', Content::class );

        return ContentResource::collection( Content::all() );
    }

    /**
     * Store a newly created content item in storage.
     *
     * @since 1.1.0
     *
     * @param ContentRequest $request The validated request data.
     * @return ContentResource The newly created content resource.
     */
    #[OA\Post(
        path: "/api/cms/content",
        operationId: "createContent",
        description: "Create a new content item with optional taxonomy term associations.",
        summary: "Create new content",
        security: [["sanctum" => []]],
        tags: ["Content Management"],
        requestBody: new OA\RequestBody(
            description: "Content data",
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/ContentRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Content created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Content")
            ),
            new OA\Response(
                response: 400,
                description: "Validation error",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden - insufficient permissions",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 429,
                description: "Rate limit exceeded (60 requests per minute)",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function store( ContentRequest $request )
    {
        $this->authorize( 'create', Content::class );

        $validated = $request->validated();

        // Extract terms from the validated data
        $terms = $validated['terms'] ?? null;
        unset($validated['terms']);

        // Create the content
        $content = Content::create( $validated );

        // Sync terms if provided
        if ($terms !== null) {
            $content->terms()->sync($terms);
        }

        return new ContentResource( $content );
    }

    /**
     * Display the specified content item.
     *
     * @since 1.1.0
     *
     * @param Content $content The content item to display.
     * @return ContentResource The content resource.
     */
    #[OA\Get(
        path: "/api/cms/content/{id}",
        operationId: "getContentById",
        description: "Retrieve a specific content item by ID. Requires appropriate permissions to view the content.",
        summary: "Get content by ID",
        security: [["sanctum" => []]],
        tags: ["Content Management"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Content ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful operation",
                content: new OA\JsonContent(ref: "#/components/schemas/Content")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden - insufficient permissions",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 404,
                description: "Content not found",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 429,
                description: "Rate limit exceeded (60 requests per minute)",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function show( Content $content )
    {
        $this->authorize( 'view', $content );

        return new ContentResource( $content );
    }

    /**
     * Update the specified content item in storage.
     *
     * @since 1.1.0
     *
     * @param ContentRequest $request The validated request data.
     * @param Content        $content The content item to update.
     * @return ContentResource The updated content resource.
     */
    #[OA\Put(
        path: "/api/cms/content/{id}",
        operationId: "updateContent",
        description: "Update an existing content item with optional taxonomy term associations.",
        summary: "Update content",
        security: [["sanctum" => []]],
        tags: ["Content Management"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Content ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            description: "Updated content data",
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/ContentRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Content updated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Content")
            ),
            new OA\Response(
                response: 400,
                description: "Validation error",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden - insufficient permissions",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 404,
                description: "Content not found",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 429,
                description: "Rate limit exceeded (60 requests per minute)",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function update( ContentRequest $request, Content $content )
    {
        $this->authorize( 'update', $content );

        $validated = $request->validated();

        // Extract terms from the validated data
        $terms = $validated['terms'] ?? null;
        unset($validated['terms']);

        // Update the content
        $content->update( $validated );

        // Sync terms if provided
        if ($terms !== null) {
            $content->terms()->sync($terms);
        }

        return new ContentResource( $content );
    }

    /**
     * Remove the specified content item from storage.
     *
     * @since 1.1.0
     *
     * @param Content $content The content item to delete.
     * @return \Illuminate\Http\JsonResponse Empty JSON response.
     */
    #[OA\Delete(
        path: "/api/cms/content/{id}",
        operationId: "deleteContent",
        description: "Delete a specific content item. This action is irreversible. Requires appropriate permissions to delete the content.",
        summary: "Delete content",
        security: [["sanctum" => []]],
        tags: ["Content Management"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Content ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Content deleted successfully"
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden - insufficient permissions",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 404,
                description: "Content not found",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            ),
            new OA\Response(
                response: 429,
                description: "Rate limit exceeded (60 requests per minute)",
                content: new OA\JsonContent(ref: "#/components/schemas/Error")
            )
        ]
    )]
    public function destroy( Content $content )
    {
        $this->authorize( 'delete', $content );

        $content->delete();

        return response()->json();
    }
}

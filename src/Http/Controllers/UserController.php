<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\UserRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\UserResource;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

/**
 * User Controller.
 *
 * Handles CRUD operations for user accounts in the CMS Framework.
 * Manages user creation, retrieval, updating, and deletion with role assignments.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.1.0
 */
#[OA\Schema(
    schema: "User",
    type: "object",
    description: "User account in the CMS",
    required: ["username", "email"],
    properties: [
        new OA\Property(property: "id", type: "integer", description: "Unique identifier", example: 1),
        new OA\Property(property: "username", type: "string", description: "Unique username", example: "johndoe"),
        new OA\Property(property: "email", type: "string", format: "email", description: "User email address", example: "john@example.com"),
        new OA\Property(property: "email_verified_at", type: "string", format: "date-time", nullable: true, description: "Email verification timestamp", example: "2025-08-26T10:00:00Z"),
        new OA\Property(property: "role_id", type: "integer", nullable: true, description: "Associated role ID", example: 2),
        new OA\Property(property: "first_name", type: "string", nullable: true, description: "User's first name", example: "John"),
        new OA\Property(property: "last_name", type: "string", nullable: true, description: "User's last name", example: "Doe"),
        new OA\Property(property: "website", type: "string", nullable: true, description: "User's website URL", example: "https://johndoe.com"),
        new OA\Property(property: "bio", type: "string", nullable: true, description: "User biography", example: "Software developer and blogger"),
        new OA\Property(property: "links", type: "object", nullable: true, description: "Social media and other links"),
        new OA\Property(property: "settings", type: "object", nullable: true, description: "User-specific settings"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Creation timestamp", example: "2025-08-26T10:00:00Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Last update timestamp", example: "2025-08-26T10:00:00Z")
    ]
)]
#[OA\Schema(
    schema: "UserRequest",
    type: "object",
    description: "User creation/update request",
    required: ["username", "email"],
    properties: [
        new OA\Property(property: "username", type: "string", description: "Unique username", example: "johndoe"),
        new OA\Property(property: "email", type: "string", format: "email", description: "User email address", example: "john@example.com"),
        new OA\Property(property: "password", type: "string", description: "User password (for creation only)", example: "secretpassword123"),
        new OA\Property(property: "role_id", type: "integer", nullable: true, description: "Associated role ID", example: 2),
        new OA\Property(property: "first_name", type: "string", nullable: true, description: "User's first name", example: "John"),
        new OA\Property(property: "last_name", type: "string", nullable: true, description: "User's last name", example: "Doe"),
        new OA\Property(property: "website", type: "string", nullable: true, description: "User's website URL", example: "https://johndoe.com"),
        new OA\Property(property: "bio", type: "string", nullable: true, description: "User biography", example: "Software developer and blogger"),
        new OA\Property(property: "links", type: "object", nullable: true, description: "Social media and other links"),
        new OA\Property(property: "settings", type: "object", nullable: true, description: "User-specific settings")
    ]
)]
class UserController
{
	use AuthorizesRequests;

	/**
	 * Display a listing of all users.
	 *
	 * @since 1.1.0
	 *
	 * @return \Illuminate\Http\JsonResponse Collection of user data.
	 */
	#[OA\Get(
		path: "/api/cms/users",
		operationId: "getUserList",
		description: "Retrieve a list of all users. Requires administrative permissions.",
		summary: "List all users",
		security: [["sanctum" => []]],
		tags: ["User Management"],
		responses: [
			new OA\Response(
				response: 200,
				description: "Successful operation",
				content: new OA\JsonContent(
					type: "object",
					properties: [
						new OA\Property(
							property: "data",
							type: "array",
							items: new OA\Items(ref: "#/components/schemas/User")
						)
					]
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
				description: "Rate limit exceeded (30 requests per minute)",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			)
		]
	)]
	public function index()
	{
		$this->authorize( 'viewAny', User::class );

		// Get all users and ensure we're returning them all
		$users = User::all();

		// Return the users as a JSON resource with a 'data' wrapper
		return response()->json([
			'data' => $users->map(function ($user) {
				return [
					'id' => $user->id,
					'username' => $user->username,
					'email' => $user->email,
					'email_verified_at' => $user->email_verified_at,
					'password' => $user->password,
					'role_id' => $user->role_id,
					'first_name' => $user->first_name,
					'last_name' => $user->last_name,
					'website' => $user->website,
					'bio' => $user->bio,
					'links' => $user->links,
					'settings' => $user->settings,
					'created_at' => $user->created_at,
					'updated_at' => $user->updated_at,
				];
			})
		]);
	}

	/**
	 * Create a new user.
	 *
	 * @since 1.1.0
	 *
	 * @return \ArtisanPackUI\CMSFramework\Http\Resources\UserResource Created user resource.
	 */
	#[OA\Post(
		path: "/api/cms/users",
		operationId: "createUser",
		description: "Create a new user account. Requires administrative permissions.",
		summary: "Create a new user",
		security: [["sanctum" => []]],
		tags: ["User Management"],
		requestBody: new OA\RequestBody(
			required: true,
			content: new OA\JsonContent(ref: "#/components/schemas/UserRequest")
		),
		responses: [
			new OA\Response(
				response: 201,
				description: "User created successfully",
				content: new OA\JsonContent(ref: "#/components/schemas/User")
			),
			new OA\Response(
				response: 400,
				description: "Bad request - validation failed",
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
				response: 422,
				description: "Unprocessable Entity - validation errors",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			),
			new OA\Response(
				response: 429,
				description: "Rate limit exceeded (30 requests per minute)",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			)
		]
	)]
	public function store( UserRequest $request )
	{
		$this->authorize( 'create', User::class );

		return new UserResource( User::create( $request->validated() ) );
	}

	/**
	 * Display a specific user.
	 *
	 * @since 1.1.0
	 *
	 * @return \ArtisanPackUI\CMSFramework\Http\Resources\UserResource User resource.
	 */
	#[OA\Get(
		path: "/api/cms/users/{id}",
		operationId: "getUserById",
		description: "Retrieve a specific user by ID. Requires appropriate permissions.",
		summary: "Get user by ID",
		security: [["sanctum" => []]],
		tags: ["User Management"],
		parameters: [
			new OA\Parameter(
				name: "id",
				description: "User ID",
				in: "path",
				required: true,
				schema: new OA\Schema(type: "integer", example: 1)
			)
		],
		responses: [
			new OA\Response(
				response: 200,
				description: "Successful operation",
				content: new OA\JsonContent(ref: "#/components/schemas/User")
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
				description: "User not found",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			),
			new OA\Response(
				response: 429,
				description: "Rate limit exceeded (30 requests per minute)",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			)
		]
	)]
	public function show( $id )
	{
		// Find the user manually instead of relying on model binding
		$user = User::findOrFail($id);

		$this->authorize( 'view', $user );

		return new UserResource( $user );
	}

	/**
	 * Update a specific user.
	 *
	 * @since 1.1.0
	 *
	 * @return \ArtisanPackUI\CMSFramework\Http\Resources\UserResource Updated user resource.
	 */
	#[OA\Put(
		path: "/api/cms/users/{id}",
		operationId: "updateUser",
		description: "Update a specific user by ID. Requires appropriate permissions.",
		summary: "Update user by ID",
		security: [["sanctum" => []]],
		tags: ["User Management"],
		parameters: [
			new OA\Parameter(
				name: "id",
				description: "User ID",
				in: "path",
				required: true,
				schema: new OA\Schema(type: "integer", example: 1)
			)
		],
		requestBody: new OA\RequestBody(
			required: true,
			content: new OA\JsonContent(ref: "#/components/schemas/UserRequest")
		),
		responses: [
			new OA\Response(
				response: 200,
				description: "User updated successfully",
				content: new OA\JsonContent(ref: "#/components/schemas/User")
			),
			new OA\Response(
				response: 400,
				description: "Bad request - validation failed",
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
				description: "User not found",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			),
			new OA\Response(
				response: 422,
				description: "Unprocessable Entity - validation errors",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			),
			new OA\Response(
				response: 429,
				description: "Rate limit exceeded (30 requests per minute)",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			)
		]
	)]
	public function update( UserRequest $request, $id )
	{
		// Find the user manually instead of relying on model binding
		$user = User::findOrFail($id);

		$this->authorize( 'update', $user );

		$user->update( $request->validated() );

		// Refresh the user to get the updated data
		$user->refresh();

		return new UserResource( $user );
	}

	/**
	 * Delete a specific user.
	 *
	 * @since 1.1.0
	 *
	 * @return \Illuminate\Http\JsonResponse Empty response indicating successful deletion.
	 */
	#[OA\Delete(
		path: "/api/cms/users/{id}",
		operationId: "deleteUser",
		description: "Delete a specific user by ID. Requires appropriate permissions.",
		summary: "Delete user by ID",
		security: [["sanctum" => []]],
		tags: ["User Management"],
		parameters: [
			new OA\Parameter(
				name: "id",
				description: "User ID",
				in: "path",
				required: true,
				schema: new OA\Schema(type: "integer", example: 1)
			)
		],
		responses: [
			new OA\Response(
				response: 200,
				description: "User deleted successfully",
				content: new OA\JsonContent(
					type: "object",
					properties: []
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
				response: 404,
				description: "User not found",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			),
			new OA\Response(
				response: 429,
				description: "Rate limit exceeded (30 requests per minute)",
				content: new OA\JsonContent(ref: "#/components/schemas/Error")
			)
		]
	)]
	public function destroy( $id )
	{
		// Find the user manually instead of relying on model binding
		$user = User::findOrFail($id);

		$this->authorize( 'delete', $user );

		$user->delete();

		return response()->json();
	}
}

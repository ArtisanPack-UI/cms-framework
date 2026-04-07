<?php

declare( strict_types=1 );

/**
 * User Controller for the CMS Framework Users Module.
 *
 * This controller handles CRUD operations for users including listing,
 * creating, showing, updating, and deleting user records through API endpoints.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Http\Controllers\Concerns\HasIncludableRelationships;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Requests\BulkUserRequest;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\UserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

/**
 * API controller for managing users.
 *
 * Provides RESTful API endpoints for user management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
#[Group( 'Users', weight: 10 )]
class UserController extends Controller
{
    use HasIncludableRelationships;

    /**
     * The relationships that can be included via the include query parameter.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $includableRelationships = ['roles'];

    /**
     * The default relationships to load when no include parameter is provided.
     *
     * @since 1.1.0
     *
     * @var array<int, string>
     */
    protected array $defaultIncludes = ['roles'];

    /**
     * Display a listing of users.
     *
     * Retrieves a paginated list of users with their associated roles
     * and returns them as a JSON resource collection.
     *
     * @since 1.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of user resources.
     */
    public function index( Request $request ): AnonymousResourceCollection
    {
        $userModel = config( 'artisanpack.cms-framework.user_model' );
        $includes  = $this->getRequestedIncludes( $request );
        $users     = $userModel::with( $includes )->paginate( 15 );

        return UserResource::collection( $users );
    }

    /**
     * Store a newly created user.
     *
     * Validates the incoming request data and creates a new user with the
     * provided information. The password is automatically hashed before storage.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request containing user data.
     *
     * @return JsonResponse The created user resource with a 201 status code.
     */
    public function store( Request $request ): JsonResponse
    {
        $userModel = config( 'artisanpack.cms-framework.user_model' );

        $validated = $request->validate( [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ] );

        $validated['password'] = bcrypt( $validated['password'] );

        $user = $userModel::create( $validated );
        $user->load( $this->getRequestedIncludes( $request ) );

        return (new UserResource( $user ))->response()->setStatusCode( 201 );
    }

    /**
     * Display the specified user.
     *
     * Retrieves a single user by ID with their associated roles
     * and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param  int|string  $id  The ID of the user to retrieve.
     *
     * @return UserResource The user resource with loaded roles.
     */
    public function show( Request $request, string|int $id ): UserResource
    {
        $userModel = config( 'artisanpack.cms-framework.user_model' );
        $includes  = $this->getRequestedIncludes( $request );
        $user      = $userModel::with( $includes )->findOrFail( $id );

        return new UserResource( $user );
    }

    /**
     * Update the specified user.
     *
     * Validates the incoming request data and updates the user with the
     * provided information. Only provided fields are updated (partial updates).
     * Passwords are automatically hashed if provided.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request containing updated user data.
     * @param  int|string  $id  The ID of the user to update.
     *
     * @return UserResource The updated user resource with loaded roles.
     */
    public function update( Request $request, string|int $id ): UserResource
    {
        $userModel = config( 'artisanpack.cms-framework.user_model' );
        $user      = $userModel::findOrFail( $id );
        $validated = $request->validate( [
            'name'     => 'sometimes|required|string|max:255',
            'email'    => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|required|string|min:8',
        ] );

        if ( isset( $validated['password'] ) ) {
            $validated['password'] = bcrypt( $validated['password'] );
        }

        $user->update( $validated );
        $user->load( $this->getRequestedIncludes( $request ) );

        return new UserResource( $user );
    }

    /**
     * Remove the specified user.
     *
     * Deletes a user from the database and returns a successful response
     * with no content.
     *
     * @since 1.0.0
     *
     * @param  int|string  $id  The ID of the user to delete.
     *
     * @return Response A response with 204 status code.
     */
    public function destroy( string|int $id ): Response
    {
        $userModel = config( 'artisanpack.cms-framework.user_model' );
        $user      = $userModel::findOrFail( $id );
        $user->delete();

        return response()->noContent();
    }

    /**
     * Perform a bulk action on multiple users.
     *
     * Processes the requested action on each user individually.
     * Returns a summary of successes and failures.
     *
     * @since 1.1.0
     *
     * @param  BulkUserRequest  $request  The validated bulk action request.
     *
     * @return JsonResponse Summary with processed count, failed count, and error details.
     */
    public function bulk( BulkUserRequest $request ): JsonResponse
    {
        $action     = $request->validated( 'action' );
        $ids        = $request->validated( 'ids' );
        $permission = $this->getBulkPermission( $action );
        $processed  = 0;
        $errors     = [];

        $userModel = config( 'artisanpack.cms-framework.user_model' );
        $users     = $userModel::whereIn( 'id', $ids )->get()->keyBy( 'id' );

        foreach ( $ids as $id ) {
            $user = $users->get( $id );

            if ( null === $user ) {
                $errors[ $id ] = __( 'User not found.' );

                continue;
            }

            // Prevent users from performing bulk actions on themselves
            if ( $request->user() && $user->id === $request->user()->id ) {
                $errors[ $id ] = __( 'You cannot perform bulk actions on your own account.' );

                continue;
            }

            if ( ! Gate::forUser( $request->user() )->allows( $permission, $user ) ) {
                $errors[ $id ] = __( 'You do not have permission to :action this user.', ['action' => $action] );

                continue;
            }

            try {
                $this->executeBulkAction( $action, $user );
                $processed++;
            } catch ( Throwable $e ) {
                report( $e );
                $errors[ $id ] = __( 'Failed to :action user.', ['action' => $action] );
            }
        }

        return response()->json( [
            'processed' => $processed,
            'failed'    => count( $errors ),
            'errors'    => $errors,
        ] );
    }

    /**
     * Get the Gate permission for a bulk action.
     *
     * @since 1.1.0
     *
     * @param  string  $action  The bulk action name.
     *
     * @return string The Gate permission name.
     */
    protected function getBulkPermission( string $action ): string
    {
        return match ( $action ) {
            'delete'              => 'users.delete',
            'activate', 'deactivate' => 'users.manage',
            default               => throw new InvalidArgumentException( __( 'Unsupported bulk action: :action', ['action' => $action] ) ),
        };
    }

    /**
     * Execute a bulk action on a single user.
     *
     * @since 1.1.0
     *
     * @param  string  $action  The bulk action to perform.
     * @param  mixed  $user  The user model instance to perform the action on.
     */
    protected function executeBulkAction( string $action, mixed $user ): void
    {
        match ( $action ) {
            'delete'     => $user->delete(),
            'activate'   => $user->update( ['email_verified_at' => now()] ),
            'deactivate' => $user->update( ['email_verified_at' => null] ),
            default      => throw new InvalidArgumentException( __( 'Unsupported bulk action: :action', ['action' => $action] ) ),
        };
    }
}

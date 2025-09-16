<?php

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $userModel = config( 'cms-framework.user_model' );
        $users     = $userModel::with( 'roles' )->paginate( 15 );

        return UserResource::collection( $users );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store( Request $request ): UserResource
    {
        $userModel = config( 'cms-framework.user_model' );

        $validated = $request->validate( [
                                             'name'     => 'required|string|max:255',
                                             'email'    => 'required|string|email|max:255|unique:users',
                                             'password' => 'required|string|min:8',
                                         ] );

        $validated['password'] = bcrypt( $validated['password'] );

        $user = $userModel::create( $validated );
        $user->load( 'roles' );

        return new UserResource( $user );
    }

    /**
     * Display the specified resource.
     */
    public function show( string | int $id ): UserResource
    {
        $userModel = config( 'cms-framework.user_model' );
        $user      = $userModel::with( 'roles' )->findOrFail( $id );

        return new UserResource( $user );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( Request $request, string | int $id ): UserResource
    {
        $userModel = config( 'cms-framework.user_model' );
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
        $user->load( 'roles' );

        return new UserResource( $user );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( string | int $id ): JsonResponse
    {
        $userModel = config( 'cms-framework.user_model' );
        $user      = $userModel::findOrFail( $id );
        $user->delete();

        return response()->json( [], 204 );
    }
}

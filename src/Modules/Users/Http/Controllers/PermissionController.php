<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\PermissionResource;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * RESTful API controller for managing Permission records.
 *
 * Each method calls `authorize()` against {@see PermissionPolicy} so
 * only users with the matching capability can list / read / mutate
 * permissions. Slug + name uniqueness is enforced via validation.
 *
 * @since 1.0.0
 */
#[Group('Permissions', weight: 12)]
class PermissionController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Permission::class, 'permission');
    }

    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::with('roles')->paginate(15);

        return PermissionResource::collection($permissions);
    }

    public function store(Request $request): PermissionResource
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:permissions,name',
            'slug'        => 'sometimes|string|max:255|unique:permissions,slug',
            'description' => 'nullable|string|max:255',
        ]);

        $permission = Permission::create($validated);
        $permission->load('roles');

        return new PermissionResource($permission);
    }

    public function show(Permission $permission): PermissionResource
    {
        $permission->load('roles');

        return new PermissionResource($permission);
    }

    public function update(Request $request, Permission $permission): PermissionResource
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255|unique:permissions,name,'.$permission->id,
            'slug'        => 'sometimes|required|string|max:255|unique:permissions,slug,'.$permission->id,
            'description' => 'sometimes|nullable|string|max:255',
        ]);

        $permission->update($validated);
        $permission->load('roles');

        return new PermissionResource($permission);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([], 204);
    }
}

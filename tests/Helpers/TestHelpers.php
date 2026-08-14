<?php

declare( strict_types=1 );

if ( ! function_exists( 'grantPermissions' ) ) {
    /**
     * Grant a set of permission slugs to a user through a throwaway role.
     *
     * Mirrors how the deny-by-default plugin/theme/AI abilities resolve in
     * production: a permission row must exist (so rbac's `Gate::before`
     * matches the ability) and the user must reach it through a role. Creates
     * the permissions and a single test role, attaches everything, then
     * refreshes the in-memory relation and permission cache so the very next
     * `can()` on the same instance sees the grant.
     *
     * @param  object  $user  The user to grant permissions to.
     * @param  string  ...$slugs  The permission slugs to grant.
     *
     * @return object The same user instance, for chaining.
     */
    function grantPermissions( object $user, string ...$slugs ): object
    {
        $role = ArtisanPackUI\CMSFramework\Modules\Users\Models\Role::firstOrCreate(
            [ 'slug' => 'test-privileged' ],
            [ 'name' => 'Test Privileged' ],
        );

        $permissionIds = [];
        foreach ( $slugs as $slug ) {
            $permission = ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission::firstOrCreate(
                [ 'slug' => $slug ],
                [ 'name' => $slug ],
            );

            $permissionIds[] = $permission->getKey();
        }

        $role->permissions()->syncWithoutDetaching( $permissionIds );
        $user->roles()->syncWithoutDetaching( [ $role->getKey() ] );

        $user->load( 'roles' );

        if ( method_exists( $user, 'flushPermissionCache' ) ) {
            $user->flushPermissionCache();
        }

        return $user;
    }
}

if ( ! function_exists( 'invokeMethod' ) ) {
    /**
     * Helper function to invoke protected/private methods for testing.
     *
     * @param  object  $object  The object instance
     * @param  string  $methodName  The method name to invoke
     * @param  array  $parameters  Parameters to pass to the method
     *
     * @return mixed The method's return value
     */
    function invokeMethod( object $object, string $methodName, array $parameters = [] ): mixed
    {
        $reflection = new ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );

        return $method->invokeArgs( $object, $parameters );
    }
}

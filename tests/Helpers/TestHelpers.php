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

if ( ! function_exists( 'testPluginsDirectory' ) ) {
    /**
     * Absolute path to the plugins directory this test process uses.
     *
     * Under `pest --parallel` each process gets its own directory (see
     * `TestCase::getEnvironmentSetUp()`), so tests must build plugin paths
     * from the configured directory rather than `base_path( 'plugins' )`, or
     * one process's cleanup deletes fixtures another is using (#458).
     *
     * @param  string  $path  Path inside the plugins directory.
     *
     * @return string The absolute path.
     */
    function testPluginsDirectory( string $path = '' ): string
    {
        $directory = base_path( (string) config( 'cms.plugins.directory', 'plugins' ) );

        return '' === $path ? $directory : $directory . '/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'testThemesDirectory' ) ) {
    /**
     * Absolute path to the themes directory this test process uses.
     *
     * The themes counterpart of {@see testPluginsDirectory()} (#458).
     *
     * @param  string  $path  Path inside the themes directory.
     *
     * @return string The absolute path.
     */
    function testThemesDirectory( string $path = '' ): string
    {
        $directory = base_path( (string) config( 'cms.themes.directory', 'themes' ) );

        return '' === $path ? $directory : $directory . '/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'withIsolatedStoragePath' ) ) {
    /**
     * Run a callback with the application's storage path pointed at a fresh
     * temporary directory, then restore it and remove the directory.
     *
     * Testbench's `storage/` is shared by every `pest --parallel` process, so
     * a test that asserts nothing is left in `storage/app/temp` can see
     * another process's download there (#458).
     *
     * @template TResult
     *
     * @param  callable(string): TResult  $callback  Receives the storage path.
     *
     * @return TResult What the callback returned.
     */
    function withIsolatedStoragePath( callable $callback ): mixed
    {
        $original = storage_path();
        $isolated = sys_get_temp_dir() . '/cms-framework-storage-' . bin2hex( random_bytes( 16 ) );

        Illuminate\Support\Facades\File::ensureDirectoryExists( $isolated . '/app/temp' );
        app()->useStoragePath( $isolated );

        try {
            return $callback( $isolated );
        } finally {
            app()->useStoragePath( $original );
            Illuminate\Support\Facades\File::deleteDirectory( $isolated );
        }
    }
}

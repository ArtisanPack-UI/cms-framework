<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Users\Providers;

use ArtisanPackUI\CMSFramework\Modules\Users\Http\Middleware\CheckRole;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\RoleManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use ArtisanPackUI\CMSFramework\Modules\Users\Policies\PermissionPolicy;
use ArtisanPackUI\CMSFramework\Modules\Users\Policies\RolePolicy;
use ArtisanPackUI\CMSFramework\Modules\Users\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the cms-framework Users module.
 *
 * - Registers the RoleManager + PermissionManager singletons.
 * - Binds the cms-framework Role / Permission subclasses as the
 *   `artisanpack.rbac.models.*` so rbac internals (Gate, observers,
 *   commands, middleware) resolve through this package's models.
 * - Aliases the `role:` route middleware. The `permission:` middleware
 *   is shipped by `artisanpack-ui/rbac` directly.
 * - Loads the API routes.
 */
class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton( RoleManager::class, fn () => new RoleManager );
        $this->app->singleton( PermissionManager::class, fn () => new PermissionManager );
    }

    public function boot(): void
    {
        $this->bindRbacModels();
        $this->registerMiddleware();
        $this->registerPolicies();

        Route::prefix( 'api/v1' )
            ->middleware( 'api' )
            ->group( __DIR__ . '/../routes/api.php' );
    }

    /**
     * Register the User + Role + Permission policies. The
     * `authorize()` calls in the controllers depend on these
     * bindings being in place. The User policy is bound against the
     * host-configured user model; it is skipped when the model has not
     * been configured yet ( in which case the user endpoints would fail
     * earlier anyway ).
     */
    protected function registerPolicies(): void
    {
        $userModel = config( 'artisanpack.cms-framework.user_model' );

        if ( is_string( $userModel ) && '' !== $userModel ) {
            Gate::policy( $userModel, UserPolicy::class );
        }

        Gate::policy( Role::class, RolePolicy::class );
        Gate::policy( Permission::class, PermissionPolicy::class );
    }

    /**
     * Point rbac at the cms-framework Role + Permission subclasses so
     * `Role::create()`, observers, the `permission:` middleware, the
     * Gate cache, and the rbac Artisan commands all resolve through
     * this package's models.
     */
    protected function bindRbacModels(): void
    {
        config( [
            'artisanpack.rbac.models.role'       => Role::class,
            'artisanpack.rbac.models.permission' => Permission::class,
        ] );
    }

    /**
     * Register the `role:` route middleware. rbac already aliases
     * `permission:`; this pairs them so consumers can write
     * `Route::middleware('role:admin')` alongside
     * `Route::middleware('permission:pages.publish')`.
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware( 'role', CheckRole::class );
    }
}

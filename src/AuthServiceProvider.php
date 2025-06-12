<?php
/**
 * Authentication Service Provider
 *
 * Registers and bootstraps authentication and authorization services for the CMS Framework.
 * This provider maps models to their respective policies for authorization.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\AuthServiceProvider
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Policies\UserPolicy;
use ArtisanPackUI\CMSFramework\Policies\RolePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Authentication Service Provider
 *
 * Handles the registration of policies for the CMS Framework's models,
 * enabling Laravel's authorization features for the application.
 *
 * @since 1.0.0
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * Maps model classes to their corresponding policy classes for authorization.
     *
     * @since 1.0.0
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * Registers the policies defined in the $policies property with the Laravel
     * authorization system, enabling policy-based authorization for the application.
     *
     * @since 1.0.0
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
    }
}

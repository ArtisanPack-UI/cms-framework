<?php

declare( strict_types=1 );

/**
 * Resolves the host application's configured user model factory.
 *
 * Shared by the package's factories so that host apps whose user
 * model is not `App\Models\User` still create the right user when a
 * factory needs to populate a user foreign key.
 *
 * @since 2.9.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Database\Factories\Concerns;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides `resolveUserFactory()` to factories that need to seed a
 * user foreign key without hard-coding `App\Models\User`.
 *
 * @since 2.9.0
 */
trait ResolvesUserFactory
{
    /**
     * Resolve the configured user model's factory so host apps whose
     * `auth.providers.users.model` is not `App\Models\User` still
     * create the right user. Mirrors `Post::author()`'s use of the
     * same config key.
     *
     * Returns `null` when the host's user model is missing, isn't an
     * Eloquent model, or doesn't expose a factory — in which case the
     * caller must supply the user foreign key explicitly rather than
     * fatally erroring.
     *
     * @since 2.1.0
     *
     * @return Factory|null The resolved user factory, or null when unavailable.
     */
    protected function resolveUserFactory(): ?Factory
    {
        $userModel = config( 'auth.providers.users.model' );

        if ( ! is_string( $userModel ) || ! class_exists( $userModel ) ) {
            return null;
        }

        if ( ! is_subclass_of( $userModel, Model::class ) ) {
            return null;
        }

        // `HasFactory` is the conventional gate; if the host's User
        // model doesn't expose a factory, fall back to leaving the
        // foreign key null so the caller must supply it explicitly.
        if ( ! in_array( HasFactory::class, class_uses_recursive( $userModel ), true ) ) {
            return null;
        }

        /** @var Factory $factory */
        $factory = $userModel::factory();

        return $factory;
    }
}

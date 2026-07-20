<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Policies;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for DynamicContentType.
 *
 * All checks gate on the `manage_dynamic_content` capability.
 *
 * @since 2.4.0
 */
class DynamicContentTypePolicy
{
    public function viewAny( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.viewAny', 'manage_dynamic_content' ) );
    }

    public function view( Authenticatable $user, DynamicContentType $type ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.view', 'manage_dynamic_content', $type ) );
    }

    public function create( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.create', 'manage_dynamic_content' ) );
    }

    public function update( Authenticatable $user, DynamicContentType $type ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.update', 'manage_dynamic_content', $type ) );
    }

    public function delete( Authenticatable $user, DynamicContentType $type ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.delete', 'manage_dynamic_content', $type ) );
    }
}

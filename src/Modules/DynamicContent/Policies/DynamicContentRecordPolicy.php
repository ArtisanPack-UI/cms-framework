<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Policies;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecord;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for DynamicContentRecord.
 *
 * All checks default to the global `manage_dynamic_content` capability,
 * but each check invokes an `applyFilters()` hook that receives the
 * capability slug AND the target {@see DynamicContentType} (when known)
 * so hosts can gate per-type — e.g. return a type-scoped capability like
 * `manage_dynamic_content_records.integrations` to keep credential
 * records off-limits to marketing editors.
 *
 * @since 2.4.0
 */
class DynamicContentRecordPolicy
{
    /**
     * Type-aware viewAny check. Callers with a known type should invoke
     * this via `Gate::check('viewAnyForType', $type)` rather than the
     * type-less {@see viewAny()} — the type-less variant can't be scoped.
     */
    public function viewAnyForType( Authenticatable $user, DynamicContentType $type ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.record.viewAny', 'manage_dynamic_content', $type ) );
    }

    public function viewAny( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.record.viewAny', 'manage_dynamic_content', null ) );
    }

    public function view( Authenticatable $user, DynamicContentRecord $record ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.record.view', 'manage_dynamic_content', $record->type ) );
    }

    /**
     * Type-aware create check. Callers with a known type should invoke
     * this via `Gate::check('createForType', $type)`.
     */
    public function createForType( Authenticatable $user, DynamicContentType $type ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.record.create', 'manage_dynamic_content', $type ) );
    }

    public function create( Authenticatable $user ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.record.create', 'manage_dynamic_content', null ) );
    }

    public function update( Authenticatable $user, DynamicContentRecord $record ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.record.update', 'manage_dynamic_content', $record->type ) );
    }

    public function delete( Authenticatable $user, DynamicContentRecord $record ): bool
    {
        return $user->can( applyFilters( 'ap.cmsFramework.abilities.dynamicContent.record.delete', 'manage_dynamic_content', $record->type ) );
    }
}

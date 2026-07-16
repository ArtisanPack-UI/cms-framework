<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Admin type list — shows admin-created + code-registered types.
 *
 * @since 2.4.0
 */
class TypeList extends Component
{
    public function delete( int $typeId ): void
    {
        $type = DynamicContentType::findOrFail( $typeId );

        Gate::authorize( 'delete', $type );

        app( DynamicContentTypeManager::class )->delete( $type );
    }

    public function render(): View
    {
        Gate::authorize( 'viewAny', DynamicContentType::class );

        return view( 'ap-cms-dynamic-content::livewire.type-list', [
            'types' => app( DynamicContentTypeRegistry::class )->all(),
        ] );
    }
}

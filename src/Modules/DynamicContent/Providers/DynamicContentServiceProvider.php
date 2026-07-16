<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Providers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire\CollectionEditor;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire\FieldBuilder;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire\SingletonEditor;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Livewire\TypeList;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentRecordManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\FieldTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\ModifierRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecord;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecordValue;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Policies\DynamicContentRecordPolicy;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Policies\DynamicContentTypePolicy;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services\DynamicContentAccessor;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services\DynamicContentResolver;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Wires up the DynamicContent module.
 *
 * @since 2.4.0
 */
class DynamicContentServiceProvider extends ServiceProvider
{
    public const CAPABILITY = 'manage_dynamic_content';

    public function register(): void
    {
        $this->app->singleton( FieldTypeRegistry::class, fn () => new FieldTypeRegistry() );
        $this->app->singleton( ModifierRegistry::class, fn () => new ModifierRegistry() );
        $this->app->singleton( DynamicContentTypeRegistry::class, fn () => new DynamicContentTypeRegistry() );

        $this->app->singleton( DynamicContentTypeManager::class, fn ( $app ) => new DynamicContentTypeManager(
            $app->make( DynamicContentTypeRegistry::class ),
        ) );

        $this->app->singleton( DynamicContentRecordManager::class, fn () => new DynamicContentRecordManager() );

        $this->app->singleton( DynamicContentAccessor::class, fn ( $app ) => new DynamicContentAccessor(
            $app->make( DynamicContentTypeRegistry::class ),
        ) );

        $this->app->singleton( DynamicContentResolver::class, fn ( $app ) => new DynamicContentResolver(
            $app->make( DynamicContentTypeRegistry::class ),
            $app->make( DynamicContentAccessor::class ),
            $app->make( FieldTypeRegistry::class ),
            $app->make( ModifierRegistry::class ),
        ) );

        $this->loadHelpers();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        Route::prefix( 'api/v1' )
            ->middleware( 'api' )
            ->group( __DIR__ . '/../routes/api.php' );

        Gate::policy( DynamicContentType::class, DynamicContentTypePolicy::class );
        Gate::policy( DynamicContentRecord::class, DynamicContentRecordPolicy::class );

        // Resolver output is already HTML-safe: text-shaped field types
        // escape at render time, rich-text/image stay intentionally raw.
        // Emit via raw `echo` so rich-text markup isn't double-escaped.
        Blade::directive( 'dynamicContent', function ( string $expression ): string {
            return "<?php echo app( \\ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentResolver::class )->render( (string) ( {$expression} ) ); ?>";
        } );

        $this->deferCapabilityRegistration();
        $this->registerRecordEvents();
        $this->registerViews();
        $this->registerLivewireComponents();
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom( __DIR__ . '/../resources/views', 'ap-cms-dynamic-content' );
    }

    protected function registerLivewireComponents(): void
    {
        if ( ! class_exists( Livewire::class ) ) {
            return;
        }

        Livewire::component( 'ap-cms-dynamic-content.type-list', TypeList::class );
        Livewire::component( 'ap-cms-dynamic-content.field-builder', FieldBuilder::class );
        Livewire::component( 'ap-cms-dynamic-content.singleton-editor', SingletonEditor::class );
        Livewire::component( 'ap-cms-dynamic-content.collection-editor', CollectionEditor::class );
    }

    protected function loadHelpers(): void
    {
        $helpersPath = __DIR__ . '/../helpers.php';

        if ( file_exists( $helpersPath ) ) {
            require_once $helpersPath;
        }
    }

    /**
     * Defer the `manage_dynamic_content` capability registration until the
     * app has fully booted. Running `Schema::hasTable('permissions')` inside
     * a synchronous `boot()` costs one DB round-trip on every request; the
     * capability only needs to exist once, at real bootstrap time (console
     * migrations, one-shot bootstrap seeders), so we lift the probe into
     * `booted()` where it runs once per process.
     */
    protected function deferCapabilityRegistration(): void
    {
        $this->app->booted( function (): void {
            if ( ! class_exists( PermissionManager::class ) ) {
                return;
            }

            if ( ! Schema::hasTable( 'permissions' ) ) {
                return;
            }

            $this->app->make( PermissionManager::class )
                ->register( self::CAPABILITY, __( 'Manage Dynamic Content' ) );
        } );
    }

    /**
     * Invalidate resolver-level cache AND accessor per-request memo on
     * record write, so downstream reads see the fresh value.
     */
    protected function registerRecordEvents(): void
    {
        $bust = function ( DynamicContentRecord|DynamicContentRecordValue $model ): void {
            $record = $model instanceof DynamicContentRecord ? $model : $model->record;

            if ( null === $record || null === $record->type ) {
                return;
            }

            $slug = $record->type->slug;

            Cache::forget( 'dc.sig.' . $slug );

            if ( $this->app->resolved( DynamicContentAccessor::class ) ) {
                $this->app->make( DynamicContentAccessor::class )->forget( $slug );
            }
        };

        DynamicContentRecord::saved( $bust );
        DynamicContentRecord::deleted( $bust );
        DynamicContentRecordValue::saved( $bust );
        DynamicContentRecordValue::deleted( $bust );
    }
}

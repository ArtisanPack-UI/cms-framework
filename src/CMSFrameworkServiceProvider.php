<?php

declare( strict_types=1 );

/**
 * Service provider for the CMS Framework.
 *
 * This class handles the registration and bootstrapping of the framework, including loading migrations and views and
 * providing necessary hooks for customization using the Eventy system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\CMSFramework\Ai\Agents\CategorySuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\ExcerptGenerationAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\PostTitleSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\SlugSuggestionAgent;
use ArtisanPackUI\CMSFramework\Ai\Agents\TagSuggestionAgent;
use ArtisanPackUI\CMSFramework\Livewire\Ai\AiTools;
use ArtisanPackUI\CMSFramework\Modules\Admin\Providers\AdminServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Providers\AdminWidgetServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Providers\BlogServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Providers\ContentTypesServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Core\Providers\CoreServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Providers\DynamicContentServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Providers\NotificationServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\OpenApi\Providers\OpenApiServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Modules\Pages\Providers\PagesServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Providers\PluginsServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Settings\Providers\SettingsServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Providers\SiteEditorServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Themes\Providers\ThemesServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Users\Managers\PermissionManager;
use ArtisanPackUI\CMSFramework\Modules\Users\Providers\UserServiceProvider;
use ArtisanPackUI\CMSFramework\Support\HookAliases;
use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use ArtisanPackUI\VisualEditor\VisualEditor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Livewire\Livewire;

/**
 * Registers and bootstraps the CMS Framework within the application.
 *
 * The service provider is responsible for binding the framework to the container,
 * initializing necessary components during the bootstrapping process,
 * and loading framework-specific resources, such as migrations.
 *
 * @since 1.0.0
 */
class CMSFrameworkServiceProvider extends ServiceProvider
{

    /**
     * The full set of AI feature keys the cms-framework exposes.
     *
     * Single source of truth: {@see AiController::features()},
     * {@see AiTools::enabledFeatures()}, and any host bundle wiring
     * (React/Vue apps that hit `/api/v1/cms/ai/features`) all read from
     * here so a future CMS AI feature only lands in one place.
     *
     * @since 2.3.0
     *
     * @var array<int, string>
     */
    public const AI_FEATURE_KEYS = [
        'cms.post_title',
        'cms.excerpt',
        'cms.suggest_tags',
        'cms.suggest_category',
        'cms.suggest_slug',
    ];
    /**
     * Permission slugs and human-readable names registered into
     * cms-framework's RBAC tables when visual-editor is detected.
     *
     * Plan 12 §4.6: seed only — visual-editor's policies remain on
     * the "any authenticated user" baseline for V1. Apps adopting
     * the editor can build admin UI against these stable slugs
     * without waiting for V1.1's policy delegation.
     *
     * @since 2.0.0
     *
     * @var array<string, string>
     */
    protected const VISUAL_EDITOR_PERMISSIONS = [
        'visual_editor.access'              => 'Access Visual Editor',
        'visual_editor.posts.edit'          => 'Edit Posts in Visual Editor',
        'visual_editor.pages.edit'          => 'Edit Pages in Visual Editor',
        'visual_editor.templates.edit'      => 'Edit Templates in Visual Editor',
        'visual_editor.template-parts.edit' => 'Edit Template Parts in Visual Editor',
        'visual_editor.patterns.edit'       => 'Edit Patterns in Visual Editor',
        'visual_editor.global-styles.edit'  => 'Edit Global Styles in Visual Editor',
        'visual_editor.navigation.edit'     => 'Edit Navigation in Visual Editor',
    ];

    /**
     * Declare the AI features this package owns.
     *
     * Auto-discovered by `artisanpack-ui/ai`'s `FeatureRegistry` (see
     * AI RFC — GitHub discussion #8). When the AI package is absent
     * this method is simply never called and has no effect — the
     * cms-framework still boots without AI wiring, and the AI Livewire
     * component + REST endpoints stay unregistered.
     *
     * @since 2.3.0
     *
     * @return array<string, array{ agent: class-string, package: string }>
     */
    public function aiFeatures(): array
    {
        return [
            'cms.post_title'       => [
                'agent'   => PostTitleSuggestionAgent::class,
                'package' => 'artisanpack-ui/cms-framework',
            ],
            'cms.excerpt'          => [
                'agent'   => ExcerptGenerationAgent::class,
                'package' => 'artisanpack-ui/cms-framework',
            ],
            'cms.suggest_tags'     => [
                'agent'   => TagSuggestionAgent::class,
                'package' => 'artisanpack-ui/cms-framework',
            ],
            'cms.suggest_category' => [
                'agent'   => CategorySuggestionAgent::class,
                'package' => 'artisanpack-ui/cms-framework',
            ],
            'cms.suggest_slug'     => [
                'agent'   => SlugSuggestionAgent::class,
                'package' => 'artisanpack-ui/cms-framework',
            ],
        ];
    }

    /**
     * Boots the CMS framework and loads database migration files.
     *
     * This method is triggered during the Laravel bootstrapping process to initialize
     * the CMS framework and register migration paths for the system.
     *
     * @since 1.0.0
     * @see   CMSFrameworkServiceProvider
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     */
    public function boot(): void
    {
        HookAliases::register();

        $this->mergeConfiguration();
        $this->validateConfiguration();

        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../config/cms-framework.php' => config_path( 'artisanpack/cms-framework.php' ),
            ], 'artisanpack-package-config' );

            $this->publishes( [
                __DIR__ . '/../resources/types' => resource_path( 'types/cms-framework' ),
            ], 'cms-types' );
        }

        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        $this->registerVisualEditorBridge();

        $this->registerAiSurfaces();
    }

    /**
     * Registers Post + Page into visual-editor's resource map when both
     * packages are installed.
     *
     * Detection is `class_exists` against the main VisualEditor class
     * (not the `HasBlockContent` trait — that one is polyfilled in
     * `src/Compatibility/visual-editor.php` so models load standalone).
     * When the gate evaluates to false the callback is never added, so
     * cms-framework boots cleanly without visual-editor in `composer.json`.
     *
     * The callback returns defaults *first* and merges the existing
     * `$resources` array on top, so anything a host app puts in
     * `config('artisanpack.visual-editor.resources')` keeps winning on
     * key collision (visual-editor itself merges static config over
     * filter results — see plan 12 §4.1).
     *
     * Public so tests (and other runtime callers) can re-trigger the
     * registration after stubbing the gate or mutating filter callbacks.
     *
     * @since 2.0.0
     */
    public function registerVisualEditorBridge(): void
    {
        if ( ! class_exists( VisualEditor::class ) ) {
            return;
        }

        addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
            return array_merge( [
                'posts' => Post::class,
                'pages' => Page::class,
            ], $resources );
        } );

        addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
            return $this->autoRegisterCustomContentTypes( $resources );
        } );

        $this->registerVisualEditorPermissions();
    }

    /**
     * Registers a singleton instance of the CMSFramework within the application container.
     *
     * This method is called by the Laravel framework during the bootstrapping process to run the CMS framework.
     *
     * @since 1.0.0
     * @see   CMSFrameworkServiceProvider
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cms-framework.php', 'artisanpack-cms-framework-temp',
        );

        $this->app->register( UserServiceProvider::class );
        $this->app->register( AdminServiceProvider::class );
        $this->app->register( AdminWidgetServiceProvider::class );
        $this->app->register( CoreServiceProvider::class );
        $this->app->register( SettingsServiceProvider::class );
        $this->app->register( NotificationServiceProvider::class );
        $this->app->register( ContentTypesServiceProvider::class );
        $this->app->register( DynamicContentServiceProvider::class );
        $this->app->register( BlogServiceProvider::class );
        $this->app->register( PagesServiceProvider::class );
        $this->app->register( ThemesServiceProvider::class );
        $this->app->register( SiteEditorServiceProvider::class );
        $this->app->register( PluginsServiceProvider::class );
        $this->app->register( OpenApiServiceProvider::class );
    }

    /**
     * Registers the CMS AI trigger surfaces (Livewire component +
     * `/api/v1/cms/ai/*` REST endpoints).
     *
     * Both are guarded on the {@see FeatureRegistry} contract from
     * `artisanpack-ui/ai`: when the AI package isn't installed the
     * registrations are skipped so the cms-framework still boots.
     *
     * The REST endpoints are the framework-agnostic path — React and
     * Vue apps hit them directly without either shared package
     * (`@artisanpack-ui/react`, `@artisanpack-ui/vue`) needing to know
     * anything about CMS AI features.
     *
     * @since 2.3.0
     */
    protected function registerAiSurfaces(): void
    {
        if ( ! interface_exists( FeatureRegistry::class ) ) {
            return;
        }

        if ( class_exists( Livewire::class ) ) {
            Livewire::component( 'ap-cms-ai-tools', AiTools::class );
        }

        Route::prefix( 'api/v1/cms/ai' )
            ->middleware( 'api' )
            ->group( __DIR__ . '/Http/routes/ai.php' );
    }

    /**
     * Seeds the `visual_editor.*` permission slugs into cms-framework's
     * RBAC tables.
     *
     * Plan 12 §2.6 / §4.6: seed only — visual-editor's policies remain
     * on the "any authenticated user" baseline through V1. Registering
     * the slugs early lets apps build admin UI against stable names
     * without waiting for V1.1's policy delegation. Idempotent via
     * {@see PermissionManager::register()} (`firstOrCreate`), so a
     * second boot doesn't double-insert.
     *
     * The `Schema::hasTable( 'permissions' )` guard mirrors the one on
     * {@see self::autoRegisterCustomContentTypes()}: it lets a fresh
     * `php artisan migrate` run before the permissions table exists
     * without tripping the bridge.
     *
     * @since 2.0.0
     */
    protected function registerVisualEditorPermissions(): void
    {
        if ( ! Schema::hasTable( 'permissions' ) ) {
            return;
        }

        /** @var PermissionManager $manager */
        $manager = $this->app->make( PermissionManager::class );

        foreach ( self::VISUAL_EDITOR_PERMISSIONS as $slug => $name ) {
            $manager->register( $slug, $name );
        }
    }

    /**
     * Auto-registers custom content types whose models use `HasBlockContent`.
     *
     * Iterates everything `ContentTypeManager` knows about (DB-stored types
     * plus filter-registered types) and adds any whose `model_class` applies
     * the trait into the resource map. The trait is the opt-in: a content
     * type that omits it is silently skipped, with one exception — if the
     * type also declares `'editor'` in its `supports` array we emit a
     * warning, since that combination is almost always an oversight.
     *
     * The DB lookup is gated on `Schema::hasTable('content_types')` so the
     * filter does not blow up during a fresh-install `php artisan migrate`
     * before the content_types table exists.
     *
     * @since 2.0.0
     *
     * @param  array<string, class-string>  $resources
     *
     * @return array<string, class-string>
     */
    protected function autoRegisterCustomContentTypes( array $resources ): array
    {
        if ( ! Schema::hasTable( 'content_types' ) ) {
            return $resources;
        }

        /** @var ContentTypeManager $manager */
        $manager = $this->app->make( ContentTypeManager::class );

        foreach ( $manager->getRegisteredContentTypes() as $type ) {
            $slug       = is_array( $type ) ? ( $type['slug'] ?? null ) : null;
            $modelClass = is_array( $type ) ? ( $type['model_class'] ?? null ) : null;
            $supports   = is_array( $type ) && is_array( $type['supports'] ?? null ) ? $type['supports'] : [];

            if ( ! is_string( $slug ) || ! is_string( $modelClass ) ) {
                continue;
            }

            $usesBlockContent = class_exists( $modelClass )
                && in_array( HasBlockContent::class, class_uses_recursive( $modelClass ), true );

            if ( ! $usesBlockContent ) {
                if ( in_array( 'editor', $supports, true ) ) {
                    Log::warning( sprintf(
                        'Content type [%s] declares editor support but its model [%s] does not use HasBlockContent.',
                        $slug,
                        $modelClass,
                    ) );
                }

                continue;
            }

            $resources[ $slug ] = $modelClass;
        }

        return $resources;
    }

    /**
     * Merges the package's default configuration with the user's customizations.
     *
     * This method ensures that the user's settings in `config/artisanpack.php`
     * take precedence over the package's default values.
     *
     * @since 1.0.0
     */
    protected function mergeConfiguration(): void
    {
        // Get the package's default configuration.
        $packageDefaults = config( 'artisanpack-cms-framework-temp', [] );

        // Get the user's custom configuration from config/artisanpack.php.
        $userConfig = config( 'artisanpack.cms-framework', [] );

        // Merge them, with the user's config overwriting the defaults.
        $mergedConfig = array_replace_recursive( $packageDefaults, $userConfig );

        // Set the final, correctly merged configuration.
        config( ['artisanpack.cms-framework' => $mergedConfig] );
    }

    /**
     * Validates the package configuration.
     *
     * This method ensures that required configuration values are properly set.
     * Validation is skipped when running in console mode to allow setup commands
     * like `vendor:publish` to run before configuration is complete.
     *
     * @throws InvalidArgumentException If required configuration is missing (non-console only).
     *
     * @since 1.0.0
     */
    protected function validateConfiguration(): void
    {
        // Skip validation in console mode to allow setup commands (vendor:publish,
        // package:discover, etc.) to run before the config has been published.
        if ( $this->app->runningInConsole() ) {
            return;
        }

        $userModel = config( 'artisanpack.cms-framework.user_model' );

        if ( null === $userModel ) {
            throw new InvalidArgumentException(
                'The CMS Framework user_model configuration is not set. ' .
                'Please publish the configuration file using: ' .
                'php artisan vendor:publish --tag=artisanpack-package-config ' .
                'Then set the user_model value in config/artisanpack/cms-framework.php to your User model class. ' .
                'Example: \'user_model\' => \\App\\Models\\User::class',
            );
        }
    }
}

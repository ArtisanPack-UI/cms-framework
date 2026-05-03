<?php

/**
 * Site Editor Service Provider
 *
 * Registers the SiteEditor module's resolvers, REST routes, and migrations.
 *
 * Permissions for site-editor entities (`visual_editor.templates.edit`,
 * `visual_editor.template-parts.edit`, etc.) are seeded by the parent
 * {@see \ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider} via the
 * G5 (#98) bridge. SiteEditor relies on those slugs being present without
 * registering its own.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Providers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission\GlobalStylesEmitter;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\EntityResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\VisualEditor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * @since 1.2.0
 */
class SiteEditorServiceProvider extends ServiceProvider
{
    /**
     * @since 1.2.0
     */
    public function register(): void
    {
        $this->app->singleton( TemplateResolver::class, function ( $app ) {
            return new TemplateResolver( $app->make( ThemeManager::class ) );
        } );

        $this->app->singleton( TemplatePartResolver::class, function ( $app ) {
            return new TemplatePartResolver( $app->make( ThemeManager::class ) );
        } );

        $this->app->singleton( PatternResolver::class, function ( $app ) {
            return new PatternResolver( $app->make( ThemeManager::class ) );
        } );

        $this->app->singleton( GlobalStylesResolver::class, function ( $app ) {
            return new GlobalStylesResolver( $app->make( ThemeManager::class ) );
        } );

        $this->app->singleton( GlobalStylesEmitter::class, function ( $app ) {
            return new GlobalStylesEmitter( $app->make( GlobalStylesResolver::class ) );
        } );
    }

    /**
     * @since 1.2.0
     */
    public function boot(): void
    {
        $this->loadRoutesFrom( __DIR__ . '/../routes/api.php' );

        $this->registerVisualEditorSiteEditorFilters();
        $this->registerGlobalStylesBladeDirective();
        $this->registerGlobalStylesObserver();
    }

    /**
     * Register cms-framework site-editor entities into visual-editor's
     * `ap.visual-editor.{templates,template-parts,patterns}` filters.
     *
     * Behind a `class_exists` guard so cms-framework boots cleanly without
     * visual-editor in `composer.json`. Public so tests can re-trigger the
     * registration after stubbing the gate or mutating filter callbacks.
     *
     * Each filter merges cms-framework's resolved map *under* the existing
     * map so app-level config / earlier contributors keep winning on key
     * collision (mirrors `CMSFrameworkServiceProvider::registerVisualEditorBridge`'s
     * merge order on `ap.visual-editor.resources`).
     *
     * @since 1.2.0
     */
    public function registerVisualEditorSiteEditorFilters(): void
    {
        if ( ! class_exists( VisualEditor::class ) ) {
            return;
        }

        addFilter( 'ap.visual-editor.templates', function ( array $templates ): array {
            return array_merge( $this->buildTemplateFilterMap( $this->app->make( TemplateResolver::class ) ), $templates );
        } );

        addFilter( 'ap.visual-editor.template-parts', function ( array $parts ): array {
            return array_merge( $this->buildTemplateFilterMap( $this->app->make( TemplatePartResolver::class ) ), $parts );
        } );

        addFilter( 'ap.visual-editor.patterns', function ( array $patterns ): array {
            return array_merge( $this->app->make( PatternResolver::class )->toFilterMap(), $patterns );
        } );

        // Singleton filter — `?ResolvedGlobalStyles` array shape (or null when no
        // active theme). cms-framework's resolution always wins when present;
        // the prior value (typically null from the default callback) only
        // surfaces when there is no active theme.
        addFilter( 'ap.visual-editor.global-styles', function ( $existing ) {
            $resolved = $this->app->make( GlobalStylesResolver::class )->resolve();

            return null !== $resolved ? $resolved->toFilterEntry() : $existing;
        } );
    }

    /**
     * Build the slug-keyed filter map for an EntityResolver. Templates and
     * template-parts share the same shape so the same builder serves both.
     *
     * @since 1.2.0
     *
     * @return array<string, array<string, mixed>>
     */
    protected function buildTemplateFilterMap( EntityResolver $resolver ): array
    {
        $map = [];

        foreach ( $resolver->all() as $entity ) {
            $map[ $entity->slug ] = $entity->toFilterEntry();
        }

        return $map;
    }

    /**
     * Register the `@cmsGlobalStyles` Blade directive. Themes opt in by
     * including the directive in their root layout; it expands to a
     * `<style>` block carrying the {@see GlobalStylesEmitter} output.
     *
     * @since 1.2.0
     */
    protected function registerGlobalStylesBladeDirective(): void
    {
        // User-controlled values flow through the emitter as raw strings
        // (palette colors, font names, custom property values). Neutralize
        // any `</` sequence so a payload like `red;}</style><script>` cannot
        // close the surrounding `<style>` block and execute markup. `<\/`
        // is invalid CSS outside strings (where it parses as `</`) but the
        // HTML5 raw-text parser only terminates `<style>` on the literal
        // `</style`, so the escape neutralizes the breakout without altering
        // CSS semantics for any well-formed input.
        Blade::directive( 'cmsGlobalStyles', function (): string {
            return "<?php echo '<style id=\"cms-global-styles\">' . str_ireplace( '</', '<\\\\/', app( \\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Emission\\GlobalStylesEmitter::class )->emit() ) . '</style>'; ?>";
        } );
    }

    /**
     * Wire model-level cache invalidation: any save or delete on the
     * `global_styles` table busts the emitter cache for the affected theme.
     *
     * @since 1.2.0
     */
    protected function registerGlobalStylesObserver(): void
    {
        $invalidate = function (): void {
            $this->app->make( GlobalStylesEmitter::class )->invalidate();
        };

        GlobalStyles::saved( $invalidate );
        GlobalStyles::deleted( $invalidate );
    }
}

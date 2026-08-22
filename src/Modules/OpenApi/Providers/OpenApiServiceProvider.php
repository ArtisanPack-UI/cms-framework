<?php

declare( strict_types=1 );

/**
 * OpenAPI Service Provider for the CMS Framework.
 *
 * Registers the CMS Framework API documentation with Scramble,
 * providing auto-generated OpenAPI 3.x specification for all endpoints.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\OpenApi\Providers;

use ArtisanPackUI\CMSFramework\Modules\OpenApi\Console\Commands\ExportOpenApiSpecCommand;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registers the CMS Framework API with Scramble for OpenAPI documentation.
 *
 * @since 1.1.0
 */
class OpenApiServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 1.1.0
     */
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();
    }

    /**
     * Bootstrap any application services.
     *
     * @since 1.1.0
     */
    public function boot(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->commands( [
                ExportOpenApiSpecCommand::class,
            ] );
        }

        if ( ! $this->isEnabled() ) {
            return;
        }

        $this->registerCmsApi();
    }

    /**
     * Check if the OpenAPI documentation is enabled.
     *
     * @since 1.1.0
     *
     * @return bool True if the OpenAPI documentation is enabled.
     */
    protected function isEnabled(): bool
    {
        return (bool) config( 'artisanpack.cms-framework.openapi.enabled', true );
    }

    /**
     * Register the CMS Framework API with Scramble.
     *
     * @since 1.1.0
     */
    protected function registerCmsApi(): void
    {
        $config = config( 'artisanpack.cms-framework.openapi', [] );

        $uiPath       = $config['ui_path'] ?? '/docs/api/cms';
        $documentPath = $config['document_path'] ?? '/docs/api/cms.json';

        Scramble::registerApi( 'cms', $this->buildScrambleConfig( $config ) )
            ->routes( function ( Route $route ) {
                return $this->isCmsFrameworkRoute( $route );
            } )
            ->expose(
                ui: $uiPath,
                document: $documentPath,
            )
            ->afterOpenApiGenerated( function ( OpenApi $openApi ): void {
                $this->addSecuritySchemes( $openApi );
            } );
    }

    /**
     * Build the Scramble configuration array from the package config.
     *
     * @since 1.1.0
     *
     * @param  array<string, mixed>  $config  The OpenAPI configuration array.
     *
     * @return array<string, mixed> The Scramble-compatible configuration.
     */
    protected function buildScrambleConfig( array $config ): array
    {
        $info = $config['info'] ?? [];

        $scrambleConfig             = [];
        $scrambleConfig['api_path'] = 'api/v1';
        $scrambleConfig['ui']       = ['title' => $info['title'] ?? 'ArtisanPack CMS Framework API'];

        $scrambleConfig['info'] = [
            'version'     => $info['version'] ?? $this->resolveDefaultVersion(),
            'description' => $info['description'] ?? '',
        ];

        return $scrambleConfig;
    }

    /**
     * Resolve the fallback API version from the package's own `composer.json`.
     *
     * The `openapi.info.version` config drives the advertised spec version, but
     * when it is absent this derives the version from `composer.json` rather than
     * a hardcoded literal that silently goes stale between releases.
     *
     * @since 2.9.0
     *
     * @return string The package version, or `'0.0.0'` when it cannot be read.
     */
    protected function resolveDefaultVersion(): string
    {
        $composerPath = __DIR__ . '/../../../../composer.json';

        if ( File::exists( $composerPath ) ) {
            $decoded = json_decode( ( string ) File::get( $composerPath ), true );

            if ( is_array( $decoded ) && ! empty( $decoded['version'] ) && is_string( $decoded['version'] ) ) {
                return $decoded['version'];
            }
        }

        return '0.0.0';
    }

    /**
     * Determine if a route belongs to the CMS Framework.
     *
     * Checks whether the route's controller resides within the CMS Framework namespace.
     *
     * @since 1.1.0
     *
     * @param  Route  $route  The route to check.
     *
     * @return bool True if the route belongs to the CMS Framework.
     */
    protected function isCmsFrameworkRoute( Route $route ): bool
    {
        if ( ! Str::startsWith( $route->uri, 'api/v1' ) && ! Str::startsWith( $route->uri, 'v1' ) ) {
            return false;
        }

        $action = $route->getAction( 'controller' );

        if ( null === $action || ! is_string( $action ) ) {
            return false;
        }

        return Str::startsWith( $action, 'ArtisanPackUI\\CMSFramework\\' );
    }

    /**
     * Add security schemes to the OpenAPI specification.
     *
     * @since 1.1.0
     *
     * @param  OpenApi  $openApi  The OpenAPI specification instance.
     */
    protected function addSecuritySchemes( OpenApi $openApi ): void
    {
        $openApi->secure(
            SecurityScheme::http( 'bearer', 'JWT' ),
        );
    }
}

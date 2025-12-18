<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\CustomJsonUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\GitHubUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\GitLabUpdateSource;
use Exception;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Update Checker Factory
 *
 * Factory for creating update checkers with auto-detection of source type.
 *
 * @since 2.0.0
 */
class UpdateCheckerFactory
{
    /**
     * Available update sources (in priority order).
     *
     * @since 2.0.0
     *
     * @var array<class-string<UpdateSourceInterface>>
     */
    protected static array $sources = [
        GitHubUpdateSource::class,
        GitLabUpdateSource::class,
        CustomJsonUpdateSource::class, // Fallback - supports any URL
    ];

    /**
     * Build an update checker for the given URL and type.
     *
     * @since 2.0.0
     *
     * @param  string  $url  Update source URL (GitHub, GitLab, custom JSON, etc.)
     * @param  string  $type  Update type: 'application', 'plugin', 'theme'
     * @param  string  $slug  Unique identifier (e.g., 'digital-shopfront-cms')
     * @param  string|null  $currentVersion  Current version (auto-detected if null)
     */
    public static function buildUpdateChecker(
        string $url,
        string $type,
        string $slug,
        ?string $currentVersion = null,
    ): UpdateChecker {
        // Detect current version if not provided
        if (! $currentVersion) {
            $currentVersion = static::detectCurrentVersion($type, $slug);
        }

        // Detect and instantiate appropriate source
        $source = static::detectSource($url, $currentVersion);

        // Create and return UpdateChecker
        return new UpdateChecker($source, $type, $slug);
    }

    /**
     * Register a custom update source.
     *
     * Adds a new source class to the beginning of the sources list
     * so it has priority over default sources.
     *
     * @since 2.0.0
     *
     * @param  class-string<UpdateSourceInterface>  $sourceClass  Source class name
     */
    public static function registerSource(string $sourceClass): void
    {
        if (! in_array($sourceClass, static::$sources)) {
            array_unshift(static::$sources, $sourceClass);
        }
    }

    /**
     * Detect the appropriate update source for the given URL.
     *
     * @since 2.0.0
     *
     * @param  string  $url  Update source URL
     * @param  string  $currentVersion  Current version
     */
    protected static function detectSource(string $url, string $currentVersion): UpdateSourceInterface
    {
        foreach (static::$sources as $sourceClass) {
            try {
                /** @var UpdateSourceInterface $source */
                $source = new $sourceClass($url, $currentVersion);

                if ($source->supports($url)) {
                    return $source;
                }
            } catch (InvalidArgumentException $e) {
                // Skip sources that can't parse this URL
                continue;
            }
        }

        // Fallback to custom JSON (always supports)
        return new CustomJsonUpdateSource($url, $currentVersion);
    }

    /**
     * Detect the current version based on type and slug.
     *
     * @since 2.0.0
     *
     * @param  string  $type  Update type
     * @param  string  $slug  Item slug
     *
     * @return string Current version
     */
    protected static function detectCurrentVersion(string $type, string $slug): string
    {
        return match ($type) {
            'application' => config('app.version', '0.0.0'),
            'plugin'      => static::getPluginVersion($slug),
            'theme'       => static::getThemeVersion($slug),
            default       => '0.0.0',
        };
    }

    /**
     * Get plugin version from plugin manager.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Plugin slug
     *
     * @return string Plugin version
     */
    protected static function getPluginVersion(string $slug): string
    {
        try {
            $pluginModel = \ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin::class;

            if (! class_exists($pluginModel)) {
                return '0.0.0';
            }

            $plugin = $pluginModel::where('slug', $slug)->first();

            return $plugin?->version ?? '0.0.0';
        } catch (Exception $e) {
            return '0.0.0';
        }
    }

    /**
     * Get theme version from theme manifest.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  Theme slug
     *
     * @return string Theme version
     */
    protected static function getThemeVersion(string $slug): string
    {
        $themePath = base_path("themes/{$slug}/theme.json");

        if (! File::exists($themePath)) {
            return '0.0.0';
        }

        try {
            $contents = File::get($themePath);
            $manifest = json_decode($contents, true);

            // Check if json_decode failed or didn't return an array
            if (null === $manifest || ! is_array($manifest)) {
                // Log the JSON error for debugging
                $jsonError = json_last_error_msg();
                \Illuminate\Support\Facades\Log::warning('Invalid JSON in theme manifest', [
                    'theme'      => $slug,
                    'path'       => $themePath,
                    'json_error' => $jsonError,
                ]);

                return '0.0.0';
            }

            return $manifest['version'] ?? '0.0.0';
        } catch (Exception $e) {
            return '0.0.0';
        }
    }
}

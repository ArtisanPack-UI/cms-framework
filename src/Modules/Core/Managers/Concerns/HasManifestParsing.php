<?php

declare( strict_types=1 );

/**
 * HasManifestParsing Trait
 *
 * Provides shared manifest parsing, slug validation, and path traversal
 * prevention logic for package managers (plugins, themes, etc.).
 *
 *
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Trait for shared manifest parsing and path validation across package managers.
 *
 * Extracts common logic from PluginManager and ThemeManager to ensure
 * consistent manifest parsing, slug validation, and path traversal
 * prevention across all discoverable package types.
 *
 * @since 1.1.0
 */
trait HasManifestParsing
{
    /**
     * Validate a slug format.
     *
     * Ensures the slug only contains alphanumeric characters, hyphens,
     * and underscores to prevent path traversal and injection attacks.
     *
     * @since 1.1.0
     *
     * @param  string  $slug  The slug to validate.
     *
     * @return bool True if the slug is valid, false otherwise.
     */
    public function validateSlug( string $slug ): bool
    {
        return (bool) preg_match( '/^[a-zA-Z0-9_-]+$/', $slug );
    }

    /**
     * Parse a JSON manifest file.
     *
     * Reads and decodes a JSON manifest file (plugin.json, theme.json, etc.),
     * returning null if the file doesn't exist or contains invalid JSON.
     *
     * @since 1.1.0
     *
     * @param  string  $manifestPath  Absolute path to the manifest file.
     *
     * @return array|null Parsed manifest data, or null on error.
     */
    protected function parseManifest( string $manifestPath ): ?array
    {
        if ( ! File::exists( $manifestPath ) ) {
            return null;
        }

        $content  = File::get( $manifestPath );
        $manifest = json_decode( $content, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return null;
        }

        return $manifest;
    }

    /**
     * Resolve and validate a path within a base directory.
     *
     * Resolves the real filesystem path and verifies it is contained within
     * the expected base directory to prevent path traversal attacks.
     *
     * @since 1.1.0
     *
     * @param  string  $itemPath  The path to validate.
     * @param  string  $basePath  The base directory the path must be within.
     *
     * @return string|null The resolved real path, or null if invalid or outside base directory.
     */
    protected function resolveSecurePath( string $itemPath, string $basePath ): ?string
    {
        $realItemPath = realpath( $itemPath );

        if ( false === $realItemPath ) {
            return null;
        }

        $realBasePath = realpath( $basePath );

        if ( false === $realBasePath || 0 !== strpos( $realItemPath, $realBasePath . DIRECTORY_SEPARATOR ) ) {
            return null;
        }

        return $realItemPath;
    }
}

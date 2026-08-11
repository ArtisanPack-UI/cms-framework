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
     * Check the optional `update` manifest key that declares where a package's
     * self-updates come from.
     *
     * Plugins and themes spell this key identically on purpose — two spellings
     * for the same concept is a documentation tax forever — so the rules live
     * here rather than in either manager. Returns the failure reason instead of
     * throwing so each manager can raise its own exception type.
     *
     * Both forms are transport-restricted to https: the resolved URL is handed
     * to an update source that downloads an archive the host then extracts over
     * its own `plugins/` or `themes/` directory.
     *
     * @since 2.8.0
     *
     * @param  mixed  $update  Raw `update` value from the manifest.
     *
     * @return string|null Failure reason, or null when the key is well-formed.
     */
    protected function checkUpdateSourceManifestField( mixed $update ): ?string
    {
        if ( ! is_array( $update ) || array_is_list( $update ) ) {
            return 'Invalid update. Must be an object with a "github" or "url" key.';
        }

        $github = $update['github'] ?? null;
        $url    = $update['url'] ?? null;

        if ( null === $github && null === $url ) {
            return 'Invalid update. Must declare "github" ("owner/repo") or "url" (absolute https URL).';
        }

        if ( null !== $github
            && ! $this->isRepositoryShorthand( $github )
            && ! $this->isAbsoluteHttpsUrl( $github ) ) {
            return 'Invalid update.github. Must be "owner/repo" or an absolute https repository URL.';
        }

        if ( null !== $url && ! $this->isAbsoluteHttpsUrl( $url ) ) {
            return 'Invalid update.url. Must be an absolute https URL.';
        }

        return null;
    }

    /**
     * Whether a manifest value is an `owner/repo` repository shorthand.
     *
     * Both segments must carry at least one non-dot character. The character
     * class alone accepts `../..`, `./x` and `x/.`, and the update managers
     * interpolate the shorthand into a URL that `GitHubUpdateSource::parseUrl()`
     * then splits back into the owner and repository of an api.github.com path
     * — so a dot-only segment becomes a relative path segment in the API
     * request. The host stays github.com either way, so this is not a
     * redirection primitive; it is an input that reaches a URL builder as
     * something other than a repository name, which is worth refusing outright
     * rather than reasoning about.
     *
     * @since 2.8.0
     *
     * @param  mixed  $value  Value to test.
     *
     * @return bool True when the value is a usable owner/repo pair.
     */
    protected function isRepositoryShorthand( mixed $value ): bool
    {
        if ( ! is_string( $value ) || 1 !== preg_match( '#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $value, $matches ) ) {
            return false;
        }

        return '' !== trim( $matches[1], '.' ) && '' !== trim( $matches[2], '.' );
    }

    /**
     * Whether a manifest value is a well-formed absolute https URL.
     *
     * @since 2.8.0
     *
     * @param  mixed  $value  Value to test.
     *
     * @return bool True when the value is an https URL.
     */
    protected function isAbsoluteHttpsUrl( mixed $value ): bool
    {
        return is_string( $value )
            && str_starts_with( $value, 'https://' )
            && false !== filter_var( $value, FILTER_VALIDATE_URL );
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

<?php

declare(strict_types=1);

/**
 * Plugin Exception
 *
 * Exception class for plugin-related errors in the CMS framework.
 * Handles plugin installation, activation, deactivation, and management errors
 * with specific error codes and context data.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

use Throwable;

/**
 * Plugin Exception Class
 *
 * Specialized exception for plugin management operations.
 */
class PluginException extends CMSException
{
    // Plugin-specific error codes
    public const PLUGIN_NOT_FOUND = 1001;
    public const PLUGIN_INVALID_STRUCTURE = 1002;
    public const PLUGIN_ALREADY_EXISTS = 1003;
    public const PLUGIN_ALREADY_ACTIVE = 1004;
    public const PLUGIN_ALREADY_INACTIVE = 1005;
    public const PLUGIN_INSTALLATION_FAILED = 1006;
    public const PLUGIN_ACTIVATION_FAILED = 1007;
    public const PLUGIN_DEACTIVATION_FAILED = 1008;
    public const PLUGIN_UPDATE_FAILED = 1009;
    public const PLUGIN_UNINSTALL_FAILED = 1010;
    public const PLUGIN_COMPOSER_ERROR = 1011;
    public const PLUGIN_DEPENDENCY_ERROR = 1012;
    public const PLUGIN_INVALID_CLASS = 1013;
    public const PLUGIN_DOWNLOAD_FAILED = 1014;
    public const PLUGIN_EXTRACTION_FAILED = 1015;

    /**
     * Error category for plugins.
     */
    protected string $category = 'plugin';

    /**
     * Create a plugin not found exception.
     */
    public static function pluginNotFound(string $pluginSlug, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin '{$pluginSlug}' not found",
            code: self::PLUGIN_NOT_FOUND,
            context: ['plugin_slug' => $pluginSlug],
            userMessage: $userMessage ?? "The requested plugin could not be found."
        );
    }

    /**
     * Create an invalid structure exception.
     */
    public static function invalidStructure(string $pluginPath, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin at '{$pluginPath}' has invalid structure: {$reason}",
            code: self::PLUGIN_INVALID_STRUCTURE,
            context: ['plugin_path' => $pluginPath, 'reason' => $reason],
            userMessage: $userMessage ?? "The plugin file structure is invalid."
        );
    }

    /**
     * Create an already exists exception.
     */
    public static function alreadyExists(string $pluginSlug, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin '{$pluginSlug}' already exists",
            code: self::PLUGIN_ALREADY_EXISTS,
            context: ['plugin_slug' => $pluginSlug],
            userMessage: $userMessage ?? "A plugin with this name already exists."
        );
    }

    /**
     * Create an already active exception.
     */
    public static function alreadyActive(string $pluginSlug, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin '{$pluginSlug}' is already active",
            code: self::PLUGIN_ALREADY_ACTIVE,
            context: ['plugin_slug' => $pluginSlug],
            userMessage: $userMessage ?? "This plugin is already active."
        );
    }

    /**
     * Create an already inactive exception.
     */
    public static function alreadyInactive(string $pluginSlug, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin '{$pluginSlug}' is already inactive",
            code: self::PLUGIN_ALREADY_INACTIVE,
            context: ['plugin_slug' => $pluginSlug],
            userMessage: $userMessage ?? "This plugin is already inactive."
        );
    }

    /**
     * Create an installation failed exception.
     */
    public static function installationFailed(string $pluginSlug, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin '{$pluginSlug}' installation failed: {$reason}",
            code: self::PLUGIN_INSTALLATION_FAILED,
            previous: $previous,
            context: ['plugin_slug' => $pluginSlug, 'reason' => $reason],
            userMessage: $userMessage ?? "Plugin installation failed. Please try again."
        );
    }

    /**
     * Create an activation failed exception.
     */
    public static function activationFailed(string $pluginSlug, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin '{$pluginSlug}' activation failed: {$reason}",
            code: self::PLUGIN_ACTIVATION_FAILED,
            previous: $previous,
            context: ['plugin_slug' => $pluginSlug, 'reason' => $reason],
            userMessage: $userMessage ?? "Plugin activation failed. Please check the plugin configuration."
        );
    }

    /**
     * Create a deactivation failed exception.
     */
    public static function deactivationFailed(string $pluginSlug, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin '{$pluginSlug}' deactivation failed: {$reason}",
            code: self::PLUGIN_DEACTIVATION_FAILED,
            previous: $previous,
            context: ['plugin_slug' => $pluginSlug, 'reason' => $reason],
            userMessage: $userMessage ?? "Plugin deactivation failed. Please try again."
        );
    }

    /**
     * Create a composer error exception.
     */
    public static function composerError(string $pluginSlug, string $composerError, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Composer error for plugin '{$pluginSlug}': {$composerError}",
            code: self::PLUGIN_COMPOSER_ERROR,
            previous: $previous,
            context: ['plugin_slug' => $pluginSlug, 'composer_error' => $composerError],
            userMessage: $userMessage ?? "Plugin dependency management failed."
        );
    }

    /**
     * Create a dependency error exception.
     */
    public static function dependencyError(string $pluginSlug, array $missingDependencies, ?string $userMessage = null): static
    {
        $dependencyList = implode(', ', $missingDependencies);
        
        return new static(
            message: "Plugin '{$pluginSlug}' has missing dependencies: {$dependencyList}",
            code: self::PLUGIN_DEPENDENCY_ERROR,
            context: ['plugin_slug' => $pluginSlug, 'missing_dependencies' => $missingDependencies],
            userMessage: $userMessage ?? "This plugin requires additional dependencies to be installed."
        );
    }

    /**
     * Create an invalid class exception.
     */
    public static function invalidClass(string $pluginSlug, string $className, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin '{$pluginSlug}' class '{$className}' is invalid: {$reason}",
            code: self::PLUGIN_INVALID_CLASS,
            context: ['plugin_slug' => $pluginSlug, 'class_name' => $className, 'reason' => $reason],
            userMessage: $userMessage ?? "The plugin contains invalid code and cannot be loaded."
        );
    }

    /**
     * Create a download failed exception.
     */
    public static function downloadFailed(string $url, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin download from '{$url}' failed: {$reason}",
            code: self::PLUGIN_DOWNLOAD_FAILED,
            previous: $previous,
            context: ['url' => $url, 'reason' => $reason],
            userMessage: $userMessage ?? "Plugin download failed. Please check the URL and try again."
        );
    }

    /**
     * Create an extraction failed exception.
     */
    public static function extractionFailed(string $zipPath, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Plugin extraction from '{$zipPath}' failed: {$reason}",
            code: self::PLUGIN_EXTRACTION_FAILED,
            previous: $previous,
            context: ['zip_path' => $zipPath, 'reason' => $reason],
            userMessage: $userMessage ?? "Plugin file extraction failed. The file may be corrupted."
        );
    }
}
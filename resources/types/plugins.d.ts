/**
 * TypeScript types for the CMS Framework Plugins module.
 *
 * Mirrors the Plugin model shape as it would appear in API responses.
 *
 * @since 1.1.0
 */

import type { DateTimeString, Metadata } from './common';

// ---------------------------------------------------------------------------
// Enums
// ---------------------------------------------------------------------------

/**
 * Update types for the update checker system.
 *
 * Mirrors `UpdateType` PHP enum.
 */
export type UpdateType = 'application' | 'plugin' | 'theme';

// ---------------------------------------------------------------------------
// API Response Types
// ---------------------------------------------------------------------------

/**
 * Structured plugin author, per the plugin.json `author` object form.
 */
export interface PluginAuthor {
	/** The author's display name. */
	name: string;
	/** The author's email address. */
	email?: string;
	/** The author's URL. */
	url?: string;
}

/**
 * A plugin as returned by `PluginManager` discovery (`discoverPlugins()` and
 * `getPlugin()`).
 */
export interface DiscoveredPlugin {
	/** The unique plugin slug. */
	slug: string;
	/** The plugin display name. */
	name: string;
	/** The installed version string. */
	version: string;
	/** The plugin description. */
	description: string;
	/**
	 * The raw manifest `author` value — a plain string or the documented
	 * object form. Prefer `author_name` for display.
	 */
	author: string | PluginAuthor;
	/**
	 * Author name normalized to a string. Sourced from the untrusted manifest
	 * and NOT escaped — escape it on output.
	 */
	author_name: string;
	/** Whether the plugin is currently active. */
	is_active: boolean;
	/** Absolute path to the plugin directory. */
	path: string;
	/** The full parsed plugin.json manifest. */
	manifest: Record<string, unknown>;
}

/**
 * Plugin response shape.
 */
export interface PluginResponse {
	/** The plugin ID. */
	id: number;
	/** The unique plugin slug. */
	slug: string;
	/** The plugin display name. */
	name: string;
	/** The installed version string. */
	version: string;
	/** Whether the plugin is currently active. */
	is_active: boolean;
	/** The fully-qualified service provider class name, or null. */
	service_provider: string | null;
	/** Plugin manifest/metadata. */
	meta: Metadata;
	/** When the plugin was installed. */
	installed_at: DateTimeString;
	/** When the record was created. */
	created_at: DateTimeString;
	/** When the record was last updated. */
	updated_at: DateTimeString;
}

// ---------------------------------------------------------------------------
// Dependencies & conflicts (#45)
// ---------------------------------------------------------------------------

/**
 * The `requires` map from a plugin.json manifest. Host/runtime constraints sit
 * at the top level; plugin-to-plugin dependencies are keyed by slug under
 * `plugins`.
 */
export interface PluginManifestRequires {
	/** Semver constraint on the cms-framework host, e.g. `^2.9`. */
	'cms-framework'?: string;
	/** Semver constraint on the PHP runtime, e.g. `^8.2`. */
	php?: string;
	/** Required plugins, keyed by slug to a semver constraint. */
	plugins?: Record<string, string>;
}

/**
 * A required plugin whose installed version fails its declared constraint.
 */
export interface PluginVersionMismatch {
	/** The required plugin slug. */
	slug: string;
	/** The declared semver constraint. */
	required: string;
	/** The version actually installed. */
	installed: string;
}

/**
 * An installed plugin that matches a declared conflict.
 */
export interface PluginConflictEntry {
	/** The conflicting plugin slug. */
	slug: string;
	/** The declared conflict constraint. */
	constraint: string;
	/** The version actually installed. */
	installed: string;
}

/**
 * Resolved dependency status for a single plugin. Mirrors
 * `DependencyResult::toArray()`. Every bucket is empty when its class of
 * problem is absent; `satisfied` is true only when all buckets are empty.
 */
export interface PluginDependencyStatus {
	/** Whether every declared requirement and conflict is satisfied. */
	satisfied: boolean;
	/** Required plugin slugs that are not installed. */
	missing: string[];
	/** Required plugin slugs installed but not active. */
	inactive: string[];
	/** Requirements whose installed version fails the constraint. */
	version_mismatch: PluginVersionMismatch[];
	/** Installed plugins that match a declared conflict. */
	conflicts: PluginConflictEntry[];
}

/**
 * Response of `GET /api/v1/plugins/{slug}/dependencies`.
 */
export interface PluginDependenciesResponse {
	/** The plugin slug queried. */
	slug: string;
	/** The declared `requires` manifest map. */
	requires: PluginManifestRequires;
	/** The declared `conflicts` map, keyed by slug to a constraint. */
	conflicts: Record<string, string>;
	/** The resolved dependency status. */
	status: PluginDependencyStatus;
}

/**
 * Response of `GET /api/v1/plugins/{slug}/dependents`.
 */
export interface PluginDependentsResponse {
	/** The plugin slug queried. */
	slug: string;
	/** Slugs of installed plugins that depend on this plugin. */
	dependents: string[];
	/** Whether this plugin can be deactivated without breaking a dependent. */
	can_deactivate: boolean;
}

/**
 * Successful response of `POST /api/v1/plugins/check-dependencies`.
 */
export interface PluginCheckDependenciesResponse {
	/** A dependency-first activation order for the requested slugs. */
	order: string[];
	/** Per-slug dependency status, plus whether each slug is installed. */
	results: Record<string, PluginDependencyStatus & { installed: boolean }>;
}

/**
 * Error response of `POST /api/v1/plugins/check-dependencies` when the
 * requested set contains a dependency cycle (HTTP 422).
 */
export interface PluginCircularDependencyResponse {
	/** Human-readable failure message. */
	message: string;
	/** Machine-readable error code. */
	code: 'circular_dependency';
	/** The detected cycle, as an ordered list of slugs. */
	cycle: string[];
	/** Per-slug dependency status computed before the cycle was hit. */
	results: Record<string, PluginDependencyStatus & { installed: boolean }>;
}

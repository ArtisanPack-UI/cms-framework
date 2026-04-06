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

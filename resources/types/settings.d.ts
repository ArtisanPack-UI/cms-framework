/**
 * TypeScript types for the CMS Framework Settings module.
 *
 * Mirrors the API Resource, Enum, and FormRequest shapes from:
 * - SettingResource
 * - SettingType enum
 * - SettingRequest
 *
 * @since 1.1.0
 */

import type { DateTimeString } from './common';

// ---------------------------------------------------------------------------
// Enums
// ---------------------------------------------------------------------------

/**
 * Setting value data types.
 *
 * Mirrors `SettingType` PHP enum. Determines how the raw string value
 * is cast when retrieved.
 */
export type SettingType = 'string' | 'integer' | 'boolean' | 'float' | 'json';

// ---------------------------------------------------------------------------
// API Response Types (from Resources)
// ---------------------------------------------------------------------------

/**
 * Setting response shape returned by SettingResource.
 *
 * Note: The `value` field is always returned as a string from the API.
 * Use the `type` field to determine how to parse it on the client.
 */
export interface SettingResponse {
	/** The setting ID (auto-incremented). */
	id: number;
	/** The unique setting key. */
	key: string;
	/** The setting value (serialized as a string). */
	value: string;
	/** The data type of the setting value. */
	type: SettingType;
	/** When the setting was created. */
	created_at: DateTimeString;
	/** When the setting was last updated. */
	updated_at: DateTimeString;
}

// ---------------------------------------------------------------------------
// Request Types (from FormRequests)
// ---------------------------------------------------------------------------

/**
 * Request body for creating or updating a setting (SettingRequest).
 */
export interface SettingRequestData {
	/** The unique setting key (required, lowercase alphanumeric with hyphens). */
	key: string;
	/** The setting value as a string. */
	value: string;
	/** The data type of the value (max 255). */
	type?: string;
}

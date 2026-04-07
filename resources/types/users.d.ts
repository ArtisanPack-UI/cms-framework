/**
 * TypeScript types for the CMS Framework Users module.
 *
 * Mirrors the API Resource and FormRequest shapes from:
 * - UserResource
 * - RoleResource
 * - PermissionResource
 * - PermissionRequest
 * - RoleRequest
 *
 * @since 1.1.0
 */

import type { DateTimeString } from './common';

// ---------------------------------------------------------------------------
// API Response Types (from Resources)
// ---------------------------------------------------------------------------

/**
 * Role summary used in inline relationship data.
 */
export interface RoleSummary {
	/** The role ID. */
	id: number;
	/** The role name. */
	name: string;
	/** The role slug. */
	slug: string;
}

/**
 * Permission summary used in inline relationship data.
 */
export interface PermissionSummary {
	/** The permission ID. */
	id: number;
	/** The permission name. */
	name: string;
	/** The permission slug. */
	slug: string;
}

/**
 * User response shape returned by UserResource.
 *
 * Conditional fields (via `whenLoaded`) are marked optional.
 */
export interface UserResponse {
	/** The user ID. */
	id: number;
	/** The user's full name. */
	name: string;
	/** The user's email address. */
	email: string;
	/** When the email was verified, or null if unverified. */
	email_verified_at: DateTimeString | null;
	/** When the user account was created. */
	created_at: DateTimeString;
	/** When the user account was last updated. */
	updated_at: DateTimeString;
	/** The user's roles. Present when the `roles` relationship is loaded. */
	roles?: RoleSummary[];
}

/**
 * Role response shape returned by RoleResource.
 *
 * Conditional fields (via `whenLoaded`) are marked optional.
 */
export interface RoleResponse {
	/** The role ID. */
	id: number;
	/** The role name. */
	name: string;
	/** The URL-friendly slug. */
	slug: string;
	/** When the role was created. */
	created_at: DateTimeString;
	/** When the role was last updated. */
	updated_at: DateTimeString;
	/** The role's permissions. Present when the `permissions` relationship is loaded. */
	permissions?: PermissionSummary[];
}

/**
 * Permission response shape returned by PermissionResource.
 *
 * Conditional fields (via `whenLoaded`) are marked optional.
 */
export interface PermissionResponse {
	/** The permission ID. */
	id: number;
	/** The permission name. */
	name: string;
	/** The permission slug (e.g., "post.create", "user.edit"). */
	slug: string;
	/** When the permission was created. */
	created_at: DateTimeString;
	/** When the permission was last updated. */
	updated_at: DateTimeString;
	/** The roles that have this permission. Present when the `roles` relationship is loaded. */
	roles?: RoleSummary[];
}

// ---------------------------------------------------------------------------
// Request Types (from FormRequests)
// ---------------------------------------------------------------------------

/**
 * Request body for creating or updating a permission (PermissionRequest).
 */
export interface PermissionRequestData {
	/** The permission name (required, unique, max 255). */
	name: string;
	/** The permission slug (required, unique, dot-separated segments with hyphens, e.g. "post.create"). */
	slug: string;
}

/**
 * Request body for creating or updating a role (RoleRequest).
 */
export interface RoleRequestData {
	/** The role name (required, unique, max 255). */
	name: string;
	/** The URL-friendly slug (required, unique, lowercase alphanumeric with hyphens). */
	slug: string;
	/** Array of permission IDs to attach. */
	permissions?: number[] | null;
}

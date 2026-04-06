/**
 * TypeScript types for the CMS Framework Blog module.
 *
 * Mirrors the API Resource and FormRequest shapes from:
 * - PostResource
 * - PostCategoryResource
 * - PostTagResource
 * - PostRequest
 * - PostCategoryRequest
 * - PostTagRequest
 *
 * @since 1.1.0
 */

import type { ContentStatus } from './content-types';
import type { DateTimeString, Metadata } from './common';

// ---------------------------------------------------------------------------
// API Response Types (from Resources)
// ---------------------------------------------------------------------------

/**
 * Post author summary, included when the `author` relationship is loaded.
 */
export interface PostAuthorResponse {
	/** The author's user ID. */
	id: number;
	/** The author's display name. */
	name: string;
}

/**
 * Post response shape returned by PostResource.
 *
 * Conditional fields (via `whenLoaded`) are marked optional.
 */
export interface PostResponse {
	/** The post ID. */
	id: number;
	/** The post title. */
	title: string;
	/** The URL-friendly slug. */
	slug: string;
	/** The full post content (HTML). */
	content: string | null;
	/** A short excerpt of the post. */
	excerpt: string | null;
	/** The ID of the post author. */
	author_id: number;
	/** The post author summary. Present when the `author` relationship is loaded. */
	author?: PostAuthorResponse;
	/** The publication status of the post. */
	status: ContentStatus;
	/** The date and time the post was published. */
	published_at: DateTimeString | null;
	/** Whether the post is currently published. */
	is_published: boolean;
	/** The full URL permalink. */
	permalink: string;
	/** Arbitrary metadata stored as JSON. */
	metadata: Metadata;
	/** The post categories. Present when the `categories` relationship is loaded. */
	categories?: PostCategoryResponse[];
	/** The post tags. Present when the `tags` relationship is loaded. */
	tags?: PostTagResponse[];
	/** The URL of the featured image, or null if not set. */
	featured_image_url: string | null;
	/** When the post was created. */
	created_at: DateTimeString;
	/** When the post was last updated. */
	updated_at: DateTimeString;
	/** When the post was soft-deleted, or null. */
	deleted_at: DateTimeString | null;
}

/**
 * Post category parent summary, included when the `parent` relationship is loaded.
 */
export interface PostCategoryParentResponse {
	/** The parent category ID. */
	id: number;
	/** The parent category name. */
	name: string;
	/** The parent category slug. */
	slug: string;
}

/**
 * Post category response shape returned by PostCategoryResource.
 */
export interface PostCategoryResponse {
	/** The category ID. */
	id: number;
	/** The category name. */
	name: string;
	/** The URL-friendly slug. */
	slug: string;
	/** A description of the category. */
	description: string | null;
	/** The parent category ID, or null if top-level. */
	parent_id: number | null;
	/** The parent category summary. Present when the `parent` relationship is loaded. */
	parent?: PostCategoryParentResponse;
	/** Child categories. Present when the `children` relationship is loaded. */
	children?: PostCategoryResponse[];
	/** The sort order. */
	order: number;
	/** The full URL permalink. */
	permalink: string;
	/** Arbitrary metadata stored as JSON. */
	metadata: Metadata;
	/** When the category was created. */
	created_at: DateTimeString;
	/** When the category was last updated. */
	updated_at: DateTimeString;
}

/**
 * Post tag response shape returned by PostTagResource.
 */
export interface PostTagResponse {
	/** The tag ID. */
	id: number;
	/** The tag name. */
	name: string;
	/** The URL-friendly slug. */
	slug: string;
	/** A description of the tag. */
	description: string | null;
	/** The full URL permalink. */
	permalink: string;
	/** Arbitrary metadata stored as JSON. */
	metadata: Metadata;
	/** When the tag was created. */
	created_at: DateTimeString;
	/** When the tag was last updated. */
	updated_at: DateTimeString;
}

// ---------------------------------------------------------------------------
// Request Types (from FormRequests)
// ---------------------------------------------------------------------------

/**
 * Request body for creating or updating a post (PostRequest).
 */
export interface PostRequestData {
	/** The post title (required, max 255). */
	title: string;
	/** The URL-friendly slug (required, unique, lowercase alphanumeric with hyphens). */
	slug: string;
	/** The full post content. */
	content?: string | null;
	/** A short excerpt. */
	excerpt?: string | null;
	/** The author's user ID. */
	author_id: number;
	/** The publication status. */
	status: ContentStatus;
	/** The date and time to publish the post. */
	published_at?: string | null;
	/** Arbitrary metadata as a JSON object. */
	metadata?: Record<string, unknown> | null;
	/** Array of category IDs to attach. */
	categories?: number[] | null;
	/** Array of tag IDs to attach. */
	tags?: number[] | null;
}

/**
 * Request body for creating or updating a post category (PostCategoryRequest).
 */
export interface PostCategoryRequestData {
	/** The category name (required, max 255). */
	name: string;
	/** The URL-friendly slug (required, unique, lowercase alphanumeric with hyphens). */
	slug: string;
	/** A description of the category. */
	description?: string | null;
	/** The parent category ID, or null for top-level. */
	parent_id?: number | null;
	/** The sort order (min 0). */
	order?: number;
	/** Arbitrary metadata as a JSON object. */
	metadata?: Record<string, unknown> | null;
}

/**
 * Request body for creating or updating a post tag (PostTagRequest).
 */
export interface PostTagRequestData {
	/** The tag name (required, max 255). */
	name: string;
	/** The URL-friendly slug (required, unique, lowercase alphanumeric with hyphens). */
	slug: string;
	/** A description of the tag. */
	description?: string | null;
	/** The sort order (min 0). */
	order?: number;
	/** Arbitrary metadata as a JSON object. */
	metadata?: Record<string, unknown> | null;
}

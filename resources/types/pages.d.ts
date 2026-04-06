/**
 * TypeScript types for the CMS Framework Pages module.
 *
 * Mirrors the API Resource and FormRequest shapes from:
 * - PageResource
 * - PageCategoryResource
 * - PageTagResource
 * - PageRequest
 * - PageCategoryRequest
 * - PageTagRequest
 *
 * @since 1.1.0
 */

import type { ContentStatus } from './content-types';
import type { DateTimeString, Metadata } from './common';

// ---------------------------------------------------------------------------
// API Response Types (from Resources)
// ---------------------------------------------------------------------------

/**
 * Page author summary, included when the `author` relationship is loaded.
 */
export interface PageAuthorResponse {
	/** The author's user ID. */
	id: number;
	/** The author's display name. */
	name: string;
	/** The author's email address. */
	email: string;
}

/**
 * Page parent summary, included when the `parent` relationship is loaded.
 */
export interface PageParentResponse {
	/** The parent page ID. */
	id: number;
	/** The parent page title. */
	title: string;
	/** The parent page slug. */
	slug: string;
}

/**
 * A single breadcrumb entry in the page hierarchy.
 */
export interface BreadcrumbItem {
	/** The page title. */
	title: string;
	/** The full URL for the page. */
	url: string;
}

/**
 * Page response shape returned by PageResource.
 *
 * Conditional fields (via `whenLoaded`) are marked optional.
 */
export interface PageResponse {
	/** The page ID. */
	id: number;
	/** The page title. */
	title: string;
	/** The URL-friendly slug. */
	slug: string;
	/** The full page content (HTML). */
	content: string | null;
	/** A short excerpt. */
	excerpt: string | null;
	/** The ID of the page author. */
	author_id: number;
	/** The page author summary. Present when the `author` relationship is loaded. */
	author?: PageAuthorResponse;
	/** The parent page ID, or null if top-level. */
	parent_id: number | null;
	/** The parent page summary. Present when the `parent` relationship is loaded. */
	parent?: PageParentResponse | null;
	/** Child pages. Present when the `children` relationship is loaded. */
	children?: PageResponse[];
	/** The sort order. */
	order: number;
	/** The template identifier used for rendering. */
	template: string | null;
	/** The publication status of the page. */
	status: ContentStatus;
	/** The date and time the page was published. */
	published_at: DateTimeString | null;
	/** Whether the page is currently published. */
	is_published: boolean;
	/** The full URL permalink. */
	permalink: string;
	/** The breadcrumb trail from root to this page. */
	breadcrumb: BreadcrumbItem[];
	/** The depth level in the page hierarchy (0 = top-level). */
	depth: number;
	/** Arbitrary metadata stored as JSON. */
	metadata: Metadata;
	/** The page categories. Present when the `categories` relationship is loaded. */
	categories?: PageCategoryResponse[];
	/** The page tags. Present when the `tags` relationship is loaded. */
	tags?: PageTagResponse[];
	/** The URL of the featured image, or null if not set. */
	featured_image_url: string | null;
	/** When the page was created. */
	created_at: DateTimeString;
	/** When the page was last updated. */
	updated_at: DateTimeString;
	/** When the page was soft-deleted, or null. */
	deleted_at: DateTimeString | null;
}

/**
 * Page category parent summary, included when the `parent` relationship is loaded.
 */
export interface PageCategoryParentResponse {
	/** The parent category ID. */
	id: number;
	/** The parent category name. */
	name: string;
	/** The parent category slug. */
	slug: string;
}

/**
 * Page category response shape returned by PageCategoryResource.
 */
export interface PageCategoryResponse {
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
	parent?: PageCategoryParentResponse;
	/** Child categories. Present when the `children` relationship is loaded. */
	children?: PageCategoryResponse[];
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
 * Page tag response shape returned by PageTagResource.
 */
export interface PageTagResponse {
	/** The tag ID. */
	id: number;
	/** The tag name. */
	name: string;
	/** The URL-friendly slug. */
	slug: string;
	/** A description of the tag. */
	description: string | null;
	/** The sort order. */
	order: number;
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
 * Request body for creating or updating a page (PageRequest).
 */
export interface PageRequestData {
	/** The page title (required, max 255). */
	title: string;
	/** The URL-friendly slug (required, unique, lowercase alphanumeric with hyphens). */
	slug: string;
	/** The full page content. */
	content?: string | null;
	/** A short excerpt. */
	excerpt?: string | null;
	/** The author's user ID. */
	author_id: number;
	/** The parent page ID, or null for top-level. */
	parent_id?: number | null;
	/** The sort order (min 0). */
	order?: number | null;
	/** The template identifier (max 255). */
	template?: string | null;
	/** The publication status. */
	status: ContentStatus;
	/** The date and time to publish the page. */
	published_at?: string | null;
	/** Arbitrary metadata as a JSON object. */
	metadata?: Record<string, unknown> | null;
	/** Array of category IDs to attach. */
	categories?: number[] | null;
	/** Array of tag IDs to attach. */
	tags?: number[] | null;
}

/**
 * Request body for creating or updating a page category (PageCategoryRequest).
 */
export interface PageCategoryRequestData {
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
 * Request body for creating or updating a page tag (PageTagRequest).
 */
export interface PageTagRequestData {
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

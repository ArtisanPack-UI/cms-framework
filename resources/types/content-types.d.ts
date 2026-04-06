/**
 * TypeScript types for the CMS Framework ContentTypes module.
 *
 * Mirrors the API Resource, Enum, and FormRequest shapes from:
 * - ContentTypeResource
 * - CustomFieldResource
 * - TaxonomyResource
 * - ColumnType enum
 * - ContentStatus enum
 * - FieldType enum
 * - ContentTypeRequest
 * - CustomFieldRequest
 * - TaxonomyRequest
 *
 * @since 1.1.0
 */

import type { DateTimeString, Metadata } from './common';

// ---------------------------------------------------------------------------
// Enums
// ---------------------------------------------------------------------------

/**
 * Content publication status.
 *
 * Mirrors `ContentStatus` PHP enum.
 */
export type ContentStatus = 'draft' | 'published' | 'scheduled' | 'private';

/**
 * Database column types available for custom fields.
 *
 * Mirrors `ColumnType` PHP enum.
 */
export type ColumnType =
	| 'string'
	| 'text'
	| 'integer'
	| 'bigInteger'
	| 'decimal'
	| 'float'
	| 'double'
	| 'boolean'
	| 'date'
	| 'dateTime'
	| 'time'
	| 'json'
	| 'binary';

/**
 * UI field types available for custom fields.
 *
 * Mirrors `FieldType` PHP enum.
 */
export type FieldType =
	| 'text'
	| 'textarea'
	| 'number'
	| 'select'
	| 'checkbox'
	| 'radio'
	| 'boolean'
	| 'date'
	| 'datetime'
	| 'time'
	| 'email'
	| 'url'
	| 'tel'
	| 'color'
	| 'file'
	| 'image';

/**
 * Features that a content type can support.
 */
export type ContentTypeSupport =
	| 'title'
	| 'content'
	| 'excerpt'
	| 'featured_image'
	| 'author'
	| 'thumbnail'
	| 'comments'
	| 'revisions'
	| 'page_attributes'
	| 'custom_fields';

// ---------------------------------------------------------------------------
// API Response Types (from Resources)
// ---------------------------------------------------------------------------

/**
 * Content type response shape returned by ContentTypeResource.
 */
export interface ContentTypeResponse {
	/** The content type ID. */
	id: number;
	/** The human-readable name. */
	name: string;
	/** The URL-friendly slug (unique identifier). */
	slug: string;
	/** The database table name for this content type's entries. */
	table_name: string;
	/** The fully-qualified Eloquent model class name. */
	model_class: string;
	/** A description of the content type. */
	description: string | null;
	/** Whether this content type supports hierarchical (parent/child) entries. */
	hierarchical: boolean;
	/** Whether this content type has a public archive page. */
	has_archive: boolean;
	/** The URL slug for the archive page, if enabled. */
	archive_slug: string | null;
	/** The list of features this content type supports. */
	supports: ContentTypeSupport[] | null;
	/** Arbitrary metadata stored as JSON. */
	metadata: Metadata;
	/** Whether this content type is publicly accessible. */
	public: boolean;
	/** Whether this content type is shown in the admin panel. */
	show_in_admin: boolean;
	/** The icon identifier for admin display. */
	icon: string | null;
	/** The position in the admin menu. */
	menu_position: number | null;
	/** The number of custom fields associated with this content type. */
	custom_fields_count: number;
	/** When the content type was created. */
	created_at: DateTimeString;
	/** When the content type was last updated. */
	updated_at: DateTimeString;
}

/**
 * Custom field response shape returned by CustomFieldResource.
 */
export interface CustomFieldResponse {
	/** The custom field ID. */
	id: number;
	/** The human-readable name. */
	name: string;
	/** The unique key used in code and database columns. */
	key: string;
	/** The UI field type. */
	type: FieldType;
	/** The database column type. */
	column_type: ColumnType;
	/** A description of the custom field. */
	description: string | null;
	/** Array of content type slugs this field belongs to. */
	content_types: string[];
	/** Map of content type slugs to names for the associated content types. */
	content_types_list: Record<string, string>;
	/** Configuration options for the field (e.g., select choices). */
	options: Record<string, unknown> | null;
	/** The sort order. */
	order: number;
	/** Whether this field is required. */
	required: boolean;
	/** The default value for this field. */
	default_value: string | null;
	/** When the custom field was created. */
	created_at: DateTimeString;
	/** When the custom field was last updated. */
	updated_at: DateTimeString;
}

/**
 * Taxonomy content type summary, included when the `contentType` relationship is loaded.
 */
export interface TaxonomyContentTypeResponse {
	/** The content type ID. */
	id: number;
	/** The content type name. */
	name: string;
	/** The content type slug. */
	slug: string;
}

/**
 * Taxonomy response shape returned by TaxonomyResource.
 */
export interface TaxonomyResponse {
	/** The taxonomy ID. */
	id: number;
	/** The human-readable name. */
	name: string;
	/** The URL-friendly slug (unique identifier). */
	slug: string;
	/** The slug of the content type this taxonomy is attached to. */
	content_type_slug: string;
	/** The content type summary. Present when the `contentType` relationship is loaded. */
	content_type?: TaxonomyContentTypeResponse;
	/** A description of the taxonomy. */
	description: string | null;
	/** Whether this taxonomy supports hierarchical terms. */
	hierarchical: boolean;
	/** Whether this taxonomy is shown in the admin panel. */
	show_in_admin: boolean;
	/** The REST API base path for this taxonomy. */
	rest_base: string | null;
	/** Arbitrary metadata stored as JSON. */
	metadata: Metadata;
	/** The database table name for terms. */
	terms_table: string;
	/** The fully-qualified model class name for terms, or null. */
	term_model: string | null;
	/** When the taxonomy was created. */
	created_at: DateTimeString;
	/** When the taxonomy was last updated. */
	updated_at: DateTimeString;
}

// ---------------------------------------------------------------------------
// Request Types (from FormRequests)
// ---------------------------------------------------------------------------

/**
 * Request body for creating or updating a content type (ContentTypeRequest).
 */
export interface ContentTypeRequestData {
	/** The content type name (required, max 255). */
	name: string;
	/** The URL-friendly slug (required, unique, lowercase alphanumeric with hyphens). */
	slug: string;
	/** The database table name (required, lowercase alphanumeric with underscores). */
	table_name: string;
	/** The fully-qualified Eloquent model class name. */
	model_class: string;
	/** A description of the content type. */
	description?: string | null;
	/** Whether this content type supports hierarchy. */
	hierarchical?: boolean;
	/** Whether this content type has a public archive. */
	has_archive?: boolean;
	/** The archive URL slug. */
	archive_slug?: string | null;
	/** The list of features this content type supports. */
	supports?: ContentTypeSupport[] | null;
	/** Arbitrary metadata as a JSON object. */
	metadata?: Record<string, unknown> | null;
	/** Whether this content type is publicly accessible. */
	public?: boolean;
	/** Whether this content type is shown in the admin panel. */
	show_in_admin?: boolean;
	/** The icon identifier. */
	icon?: string | null;
	/** The admin menu position. */
	menu_position?: number | null;
}

/**
 * Request body for creating or updating a custom field (CustomFieldRequest).
 */
export interface CustomFieldRequestData {
	/** The field name (required, max 255). */
	name: string;
	/** The unique key (required, lowercase alphanumeric with underscores). */
	key: string;
	/** The UI field type. */
	type: FieldType;
	/** The database column type. */
	column_type: ColumnType;
	/** A description of the field. */
	description?: string | null;
	/** Array of content type slugs this field belongs to (at least one required). */
	content_types: string[];
	/** Configuration options for the field. */
	options?: Record<string, unknown> | null;
	/** The sort order (min 0). */
	order?: number;
	/** Whether this field is required. */
	required?: boolean;
	/** The default value for this field. */
	default_value?: string | null;
}

/**
 * Request body for creating or updating a taxonomy (TaxonomyRequest).
 */
export interface TaxonomyRequestData {
	/** The taxonomy name (required, max 255). */
	name: string;
	/** The URL-friendly slug (required, unique, lowercase alphanumeric with underscores). */
	slug: string;
	/** The content type slug this taxonomy belongs to (must exist). */
	content_type_slug: string;
	/** A description of the taxonomy. */
	description?: string | null;
	/** Whether this taxonomy supports hierarchical terms. */
	hierarchical?: boolean;
	/** Whether this taxonomy is shown in the admin panel. */
	show_in_admin?: boolean;
	/** The REST API base path (lowercase alphanumeric with hyphens). */
	rest_base?: string | null;
	/** Arbitrary metadata as a JSON object. */
	metadata?: Record<string, unknown> | null;
}

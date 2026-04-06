/**
 * Common types shared across the CMS Framework API.
 *
 * @since 1.1.0
 */

/** ISO 8601 date-time string (e.g. "2024-01-15T12:00:00.000000Z"). */
export type DateTimeString = string;

/** A JSON-serializable metadata object. */
export type Metadata = Record<string, unknown> | null;

/**
 * Standard Laravel paginated response wrapper.
 *
 * Wraps any resource collection with pagination metadata and navigation links.
 */
export interface PaginatedResponse<T> {
	/** The array of resource items for the current page. */
	data: T[];

	/** Pagination metadata. */
	meta: {
		/** The current page number. */
		current_page: number;
		/** The index of the first item on the current page. */
		from: number | null;
		/** The last available page number. */
		last_page: number;
		/** Pagination links for each page. */
		links: Array<{
			url: string | null;
			label: string;
			active: boolean;
		}>;
		/** Full URL path for the paginated resource. */
		path: string;
		/** Number of items per page. */
		per_page: number;
		/** The index of the last item on the current page. */
		to: number | null;
		/** Total number of items across all pages. */
		total: number;
	};

	/** Navigation links. */
	links: {
		/** URL for the first page. */
		first: string;
		/** URL for the last page. */
		last: string;
		/** URL for the previous page, or null if on the first page. */
		prev: string | null;
		/** URL for the next page, or null if on the last page. */
		next: string | null;
	};
}

/**
 * Standard Laravel single-resource response wrapper.
 */
export interface ResourceResponse<T> {
	data: T;
}

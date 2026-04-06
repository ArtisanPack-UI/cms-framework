/**
 * TypeScript types for the CMS Framework Notifications module.
 *
 * Mirrors the API Resource and Enum shapes from:
 * - NotificationResource
 * - NotificationType enum
 *
 * @since 1.1.0
 */

import type { DateTimeString, Metadata } from './common';

// ---------------------------------------------------------------------------
// Enums
// ---------------------------------------------------------------------------

/**
 * Notification severity/category types.
 *
 * Mirrors `NotificationType` PHP enum.
 */
export type NotificationType = 'error' | 'warning' | 'success' | 'info';

// ---------------------------------------------------------------------------
// API Response Types (from Resources)
// ---------------------------------------------------------------------------

/**
 * Rich notification type object included in notification responses.
 */
export interface NotificationTypeInfo {
	/** The raw enum value. */
	value: NotificationType;
	/** The human-readable label. */
	label: string;
	/** The icon identifier (e.g., "fas.circle-check"). */
	icon: string;
	/** The Tailwind CSS color classes for this type. */
	colorClass: string;
}

/**
 * User-specific notification data from the pivot table.
 *
 * Present when the notification is fetched in the context of a specific user.
 */
export interface NotificationUserData {
	/** Whether the user has read this notification. */
	is_read: boolean;
	/** When the user read this notification, or null. */
	read_at: DateTimeString | null;
	/** Whether the user has dismissed this notification. */
	is_dismissed: boolean;
	/** When the user dismissed this notification, or null. */
	dismissed_at: DateTimeString | null;
}

/**
 * Notification response shape returned by NotificationResource.
 */
export interface NotificationResponse {
	/** The notification ID. */
	id: number;
	/** The notification type with label, icon, and color info. */
	type: NotificationTypeInfo;
	/** The notification title. */
	title: string;
	/** The notification content/message body. */
	content: string;
	/** Arbitrary metadata stored as JSON. */
	metadata: Metadata;
	/** Whether an email was sent for this notification. */
	send_email: boolean;
	/** When the notification was created (ISO 8601). */
	created_at: string;
	/** Human-readable time since creation (e.g., "2 hours ago"). */
	created_at_human: string;
	/** When the notification was last updated (ISO 8601). */
	updated_at: string;
	/** User-specific read/dismissed data. Present when loaded via the pivot table. */
	user_data?: NotificationUserData;
}

/**
 * Notification preference response shape.
 *
 * Represents a user's preference for a specific notification type.
 */
export interface NotificationPreferenceResponse {
	/** The preference ID. */
	id: number;
	/** The user ID this preference belongs to. */
	user_id: number;
	/** The notification type this preference applies to. */
	notification_type: string;
	/** Whether in-app notifications of this type are enabled. */
	is_enabled: boolean;
	/** Whether email notifications of this type are enabled. */
	email_enabled: boolean;
	/** When the preference was created. */
	created_at: DateTimeString;
	/** When the preference was last updated. */
	updated_at: DateTimeString;
}

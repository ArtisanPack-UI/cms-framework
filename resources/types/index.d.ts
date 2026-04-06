/**
 * ArtisanPack UI CMS Framework - TypeScript Type Definitions
 *
 * Provides TypeScript types for all API response shapes, request payloads,
 * and enums used throughout the CMS Framework.
 *
 * Install: `php artisan vendor:publish --tag=cms-types`
 *
 * @since 1.1.0
 */

// Common / shared types
export type {
	DateTimeString,
	Metadata,
	PaginatedResponse,
	ResourceResponse,
} from './common';

// Blog module
export type {
	PostAuthorResponse,
	PostCategoryParentResponse,
	PostCategoryRequestData,
	PostCategoryResponse,
	PostRequestData,
	PostResponse,
	PostTagRequestData,
	PostTagResponse,
} from './blog';

// Pages module
export type {
	BreadcrumbItem,
	PageAuthorResponse,
	PageCategoryParentResponse,
	PageCategoryRequestData,
	PageCategoryResponse,
	PageParentResponse,
	PageRequestData,
	PageResponse,
	PageTagRequestData,
	PageTagResponse,
} from './pages';

// Content Types module
export type {
	ColumnType,
	ContentStatus,
	ContentTypeRequestData,
	ContentTypeResponse,
	ContentTypeSupport,
	CustomFieldRequestData,
	CustomFieldResponse,
	FieldType,
	TaxonomyContentTypeResponse,
	TaxonomyRequestData,
	TaxonomyResponse,
} from './content-types';

// Users module
export type {
	PermissionRequestData,
	PermissionResponse,
	PermissionSummary,
	RoleRequestData,
	RoleResponse,
	RoleSummary,
	UserResponse,
} from './users';

// Settings module
export type {
	SettingRequestData,
	SettingResponse,
	SettingType,
} from './settings';

// Notifications module
export type {
	NotificationPreferenceResponse,
	NotificationResponse,
	NotificationType,
	NotificationTypeInfo,
	NotificationUserData,
} from './notifications';

// Plugins module
export type {
	PluginResponse,
	UpdateType,
} from './plugins';

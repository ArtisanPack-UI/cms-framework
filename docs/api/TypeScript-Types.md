# TypeScript Type Definitions

The CMS Framework ships TypeScript type definitions for all API response shapes, request payloads, and enums. Introduced in v1.1.0, these types give frontend applications compile-time safety when working with the CMS API.

## Publishing

Publish the type definitions into your application:

```bash
php artisan vendor:publish --tag=cms-types
```

This copies the type files to:

```
resources/types/cms-framework/
```

## Files

| File | Description |
|------|-------------|
| `index.d.ts` | Barrel file that re-exports all types from every module. |
| `common.d.ts` | Shared types used across modules. |
| `blog.d.ts` | Post, post category, and post tag types. |
| `pages.d.ts` | Page, page category, page tag, and breadcrumb types. |
| `content-types.d.ts` | Content type, custom field, and taxonomy types. |
| `users.d.ts` | User, role, and permission types. |
| `settings.d.ts` | Setting types. |
| `notifications.d.ts` | Notification and notification preference types. |
| `plugins.d.ts` | Plugin and update types. |

## Common Types

The `common.d.ts` file provides the foundational types used throughout the API:

```typescript
/** ISO 8601 date-time string (e.g. "2024-01-15T12:00:00.000000Z"). */
export type DateTimeString = string;

/** An array guaranteed to contain at least one element. */
export type NonEmptyArray<T> = [T, ...T[]];

/** A JSON-serializable metadata object. */
export type Metadata = Record<string, unknown> | null;

/** Standard Laravel paginated response wrapper. */
export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
}

/** Standard Laravel single-resource response wrapper. */
export interface ResourceResponse<T> {
    data: T;
}
```

## Module Types

Each module file exports types matching its API responses. Some key examples:

- **`blog.d.ts`**: `PostResponse`, `PostRequestData`, `PostCategoryResponse`, `PostTagResponse`, `PostAuthorResponse`
- **`pages.d.ts`**: `PageResponse`, `PageRequestData`, `PageCategoryResponse`, `PageTagResponse`, `BreadcrumbItem`
- **`content-types.d.ts`**: `ContentTypeResponse`, `CustomFieldResponse`, `TaxonomyResponse`, `FieldType`, `ColumnType`, `ContentStatus`
- **`users.d.ts`**: `UserResponse`, `RoleResponse`, `PermissionResponse`, `RoleSummary`, `PermissionSummary`
- **`settings.d.ts`**: `SettingResponse`, `SettingRequestData`, `SettingType`
- **`notifications.d.ts`**: `NotificationResponse`, `NotificationPreferenceResponse`, `NotificationType`
- **`plugins.d.ts`**: `PluginResponse`, `UpdateType`

## Frontend Integration

Import the types directly from the published directory:

```typescript
import type {
    PostResponse,
    PaginatedResponse,
    ResourceResponse,
} from '@/types/cms-framework';

// Typed paginated list
async function fetchPosts(): Promise<PaginatedResponse<PostResponse>> {
    const response = await fetch('/api/v1/posts', {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
        },
    });

    return response.json();
}

// Typed single resource
async function fetchPost(id: number): Promise<ResourceResponse<PostResponse>> {
    const response = await fetch(`/api/v1/posts/${id}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
        },
    });

    return response.json();
}
```

### Path Alias

If your project uses TypeScript path aliases, add one for the published types in your `tsconfig.json`:

```json
{
  "compilerOptions": {
    "paths": {
      "@/types/cms-framework": ["./resources/types/cms-framework/index.d.ts"],
      "@/types/cms-framework/*": ["./resources/types/cms-framework/*"]
    }
  }
}
```

## Keeping Types in Sync

The type definitions are published as static files. When you upgrade the CMS Framework to a new version, re-run the publish command to get updated types:

```bash
php artisan vendor:publish --tag=cms-types --force
```

The `--force` flag overwrites existing files with the latest definitions from the package.

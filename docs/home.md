---
title: CMS Framework Documentation
---

# CMS Framework Documentation

Welcome to the CMS Framework documentation! This Laravel package provides a comprehensive content management system foundation with built-in user management, role-based access control, notifications, and extensible architecture.

## Overview

The CMS Framework is designed to help developers quickly build content management systems with robust features. It provides:

- **User Management System**: Complete CRUD operations for users with role-based access control
- **Content Management**: Blog posts, pages, custom content types, and taxonomies
- **Site Editor** *(2.0.0)*: WordPress-style templates, template parts, patterns, global styles, and menus
- **Visual Editor Integration** *(2.0.0)*: Opt-in bridge to the optional `artisanpack-ui/visual-editor` package
- **Notification System**: In-app notifications with email support and user preferences
- **Settings Management**: Application-wide configuration with type casting and sanitization
- **Admin Framework**: Menu system, widgets, and authorization helpers
- **Theme & Plugin Architecture**: Theme discovery, activation, ZIP upload, lifecycle hooks, and plugin support
- **RBAC** *(2.0.0)*: Roles and permissions powered by the shared `artisanpack-ui/rbac` package
- **RESTful API**: Clean API endpoints for all operations with standardized error responses
- **Bulk Actions**: Bulk operations for posts, pages, and users
- **On-Demand Relationships**: Control eager loading via the `include` query parameter
- **OpenAPI Documentation**: Auto-generated API documentation with Swagger UI
- **TypeScript Types**: Publishable type definitions for frontend integration
- **Type-Safe Enums**: ContentStatus, FieldType, ColumnType, SettingType, and UpdateType enums
- **Laravel Integration**: Seamless integration with Laravel applications

---

## Getting Started

- [[Installation Guide]] - Setup and configuration instructions
- [[Configuration]] - Configuring the CMS Framework for your application
- [[Quick Start]] - Get up and running quickly

---

## Core Modules

### Users Module

Complete user management with roles and permissions.

- [[Users]] - Overview of the users module
- [[users/User Management]] - Managing users in your CMS
- [[users/Roles and Permissions]] - Understanding the RBAC system
- [[users/User API Reference]] - Complete API documentation

### Admin Module

Building blocks for your admin area with automatic route registration.

- [[Admin]] - Overview of the admin module
- [[admin/Menu and Pages]] - Creating admin navigation and pages
- [[admin/Widgets]] - Dashboard widgets system
- [[admin/Authorization]] - Capability-based authorization

### Core Module

Cross-cutting services for assets and utilities.

- [[Core]] - Overview of core services
- [[core/Assets]] - Asset registration and management

### Settings Module

Application-wide configuration storage with type casting.

- [[Settings]] - Overview of settings management
- [[settings/Getting Started]] - Quick start guide
- [[settings/Registering Settings]] - How to register settings
- [[settings/Retrieving and Updating]] - Working with setting values
- [[settings/Sanitization and Types]] - Type casting and validation
- [[settings/Hooks and Events]] - Extending the settings system
- [[settings/Database and Migrations]] - Database structure
- [[settings/Site Settings]] - Built-in `site.*` settings and WP-shape envelope *(2.0.0)*

### Notifications Module

Complete notification system with email support and preferences.

- [[Notifications]] - Overview of the notification system
- [[notifications/Getting Started]] - Quick start guide
- [[notifications/Registering Notifications]] - Defining notification types
- [[notifications/Sending Notifications]] - Sending to users and roles
- [[notifications/Managing Notifications]] - Read, dismiss, and manage
- [[notifications/Notification Preferences]] - User preference system
- [[notifications/API Reference]] - Complete API documentation
- [[notifications/Hooks and Events]] - Extending notifications
- [[notifications/Database and Migrations]] - Database structure

### Themes Module

Flexible theme management with WordPress-style template hierarchy.

- [[Themes]] - Theme system overview and usage
- [[themes/Installing From Zip]] - Upload a theme as a ZIP archive *(2.0.0)*
- [[themes/Lifecycle Hooks]] - Listen to install/activate hooks *(2.0.0)*

### Site Editor Module *(2.0.0)*

WordPress-style site-editor back-end — templates, parts, patterns, global styles, and menus.

- [[Site Editor]] - Module overview
- [[site-editor/Getting Started]] - File + DB authority chain
- [[site-editor/Templates]] - Templates and template parts (H1)
- [[site-editor/Patterns]] - Synced and unsynced block patterns (H2)
- [[site-editor/Global Styles]] - `theme.json`-driven styles, variations, CSS emission (H3)
- [[site-editor/Menus]] - Menus, items, theme-declared locations (H4)
- [[site-editor/Visual Editor Integration]] - `HasBlockContent` adoption and the `block_content` column
- [[site-editor/API Reference]] - Full REST endpoint listing
- [[site-editor/Hooks and Events]] - `ap.visual-editor.*` filters and the `@cmsGlobalStyles` directive

---

## Content Modules

### Blog Module

Full-featured blog with posts, categories, and tags.

- [[Blog]] - Module overview
- [[blog/Query Runtime]] - `QueryRuntime` service for `core/query` block loops *(2.0.0)*
- Posts with drafts, scheduling, and publishing
- Categories with hierarchical structure
- Tags for flexible content organization
- Author relationships and archives

### Pages Module

Hierarchical page management with templates.

- Pages with parent-child relationships
- Template support for custom layouts
- Categories and tags for pages
- Breadcrumb generation

### Content Types Module

Custom content type builder for extensible content.

- [[developer/content types]] - Creating custom content types
- [[developer/custom fields]] - Adding custom fields to content
- [[developer/taxonomies]] - Creating custom taxonomies
- [[developer/enums]] - ContentStatus, FieldType, and ColumnType enums
- [[developer/traits]] - HasContentStatus and HasContentFilters traits

---

## Extension Modules

### Plugins Module (Experimental)

Plugin architecture for extending functionality.

- Plugin discovery and manifest validation
- Activation/deactivation lifecycle
- Migration support for plugins
- Update checking and version management
- Security: path traversal prevention, input sanitization

### Core Updater

System update management for keeping the CMS current.

- Version checking from GitHub, GitLab, or custom sources
- Backup creation before updates
- Rollback support

---

## Developer Resources

### Guides

- [[Developer Guide]] - Extending and customizing the framework
- [[Hooks and Events]] - Filters and actions for extending functionality
- [[developer/hooks reference]] - Complete hooks reference

### API Documentation

- [[api/README]] - REST API overview and authentication
- [[api/Error Responses]] - Standardized JSON error response format
- [[api/Bulk Actions]] - Bulk action endpoints for posts, pages, and users
- [[api/Includable Relationships]] - On-demand relationship loading
- [[api/OpenAPI]] - OpenAPI specification and Swagger UI
- [[api/TypeScript Types]] - TypeScript type definitions for frontend
- [[Routes]] - Complete route registry
- [[Relationships]] - Model relationship documentation

### Reference

- [[Helpers]] - Helper functions reference (ap-prefixed)
- [[Exceptions]] - Exception hierarchy and error handling
- [[developer/enums]] - Type-safe enums reference
- [[developer/traits]] - Shared traits reference

---

## Module Quick Reference

| Module | Purpose | Status |
|--------|---------|--------|
| Users | User management — now powered by `artisanpack-ui/rbac` | Stable |
| Admin | Admin menu, pages, widgets | Stable |
| Core | Assets, utilities | Stable |
| Settings | Application configuration + WP-shape `site.*` envelope *(2.0.0)* | Stable |
| Notifications | In-app and email notifications | Stable |
| Themes | Theme management + ZIP upload + lifecycle hooks *(2.0.0)* | Stable |
| Site Editor | Templates, parts, patterns, global styles, menus | New in 2.0.0 |
| Blog | Posts, categories, tags, `QueryRuntime` for `core/query` *(2.0.0)* | Stable |
| Pages | Hierarchical pages | Stable |
| Content Types | Custom content types | Stable |
| Plugins | Plugin system | Experimental |
| Core Updater | System updates | Experimental |

---

## API Endpoints Overview

All API endpoints use the `/api/cms` prefix with Sanctum authentication.

### User Management
- `GET/POST /users` - List/create users
- `GET/PUT/DELETE /users/{id}` - User operations
- `POST /users/bulk` - Bulk user actions (delete, activate, deactivate)
- `GET/POST /roles` - Role management
- `GET/POST /permissions` - Permission management

### Content
- `GET/POST /posts` - Blog post management
- `POST /posts/bulk` - Bulk post actions (delete, publish, draft)
- `GET/POST /pages` - Page management
- `POST /pages/bulk` - Bulk page actions (delete, publish, draft)
- `GET/POST /content-types` - Content type management

### System
- `GET/POST /settings` - Settings management
- `GET/PUT /settings/site` - WP-shape site-meta envelope *(2.0.0)*
- `GET/POST /notifications` - Notification operations
- `GET/POST /themes` - Theme management
- `POST /themes` - Upload a theme as a ZIP archive *(2.0.0)*
- `GET/POST /plugins` - Plugin management (experimental)

### Site Editor *(2.0.0)*
- `GET/POST/PUT/DELETE /templates` - Templates (file + DB merged)
- `GET/POST/PUT/DELETE /template-parts` - Template parts (with `area`)
- `GET/POST/PUT/DELETE /blocks` - Synced patterns (WP `wp_block` shape)
- `GET/POST/PUT/DELETE /block-patterns/patterns` - Unsynced patterns (theme + user)
- `GET/PUT/DELETE /global-styles` - Singleton-per-theme global styles
- `GET /global-styles/variations` - Theme-shipped style variations
- `GET /global-styles/css` - Resolved CSS payload
- `GET/POST/PUT/DELETE /menus` - Navigation menus
- `GET/POST/PUT/DELETE /menu-items` - Menu items
- `GET/PUT/DELETE /menu-locations` - Menu location assignments

### Documentation
- `GET /docs/api/cms` - Swagger UI (when OpenAPI enabled)
- `GET /docs/api/cms.json` - Raw OpenAPI specification

See [[api/README]] for complete API documentation.

---

## Configuration

The framework uses configuration files to customize behavior:

```php
// config/artisanpack/cms-framework.php
return [
    'user_model' => \App\Models\User::class,
];
```

See [[Configuration]] for all available options.

---

## Support

For issues, feature requests, and contributions:

- **GitLab**: https://gitlab.com/artisanpack-ui/cms-framework
- **Documentation**: https://artisanpack.dev/packages/cms-framework

---

*This documentation covers CMS Framework v2.0.0*

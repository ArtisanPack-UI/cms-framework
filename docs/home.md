---
title: CMS Framework Documentation
---

# CMS Framework Documentation

Welcome to the CMS Framework documentation! This Laravel package provides a comprehensive content management system foundation with built-in user management, role-based access control, notifications, and extensible architecture.

## Overview

The CMS Framework is designed to help developers quickly build content management systems with robust features. It provides:

- **User Management System**: Complete CRUD operations for users with role-based access control
- **Content Management**: Blog posts, pages, custom content types, and taxonomies
- **Notification System**: In-app notifications with email support and user preferences
- **Settings Management**: Application-wide configuration with type casting and sanitization
- **Admin Framework**: Menu system, widgets, and authorization helpers
- **Theme & Plugin Architecture**: Extensible system for themes and plugins
- **RESTful API**: Clean API endpoints for all operations
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

---

## Content Modules

### Blog Module

Full-featured blog with posts, categories, and tags.

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
- [[Routes]] - Complete route registry
- [[Relationships]] - Model relationship documentation

### Reference

- [[Helpers]] - Helper functions reference (ap-prefixed)
- [[Exceptions]] - Exception hierarchy and error handling

---

## Module Quick Reference

| Module | Purpose | Status |
|--------|---------|--------|
| Users | User management, roles, permissions | Stable |
| Admin | Admin menu, pages, widgets | Stable |
| Core | Assets, utilities | Stable |
| Settings | Application configuration | Stable |
| Notifications | In-app and email notifications | Stable |
| Themes | Theme management | Stable |
| Blog | Posts, categories, tags | Stable |
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
- `GET/POST /roles` - Role management
- `GET/POST /permissions` - Permission management

### Content
- `GET/POST /posts` - Blog post management
- `GET/POST /pages` - Page management
- `GET/POST /content-types` - Content type management

### System
- `GET/POST /settings` - Settings management
- `GET/POST /notifications` - Notification operations
- `GET/POST /themes` - Theme management
- `GET/POST /plugins` - Plugin management (experimental)

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

*This documentation covers CMS Framework v1.0.0*

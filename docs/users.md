---
title: Users
---

# User Documentation

Welcome to the user documentation section. This section contains comprehensive guides and references for users of the CMS Framework.

## User Guides

- [User Management](Users-User-Management) - Complete guide to managing users in the system
- [User API Reference](Users-User-Api-Reference) - Technical reference for user-related API endpoints
- [Roles and Permissions](Users-Roles-And-Permissions) - Understanding the role-based access control system

## RBAC migration *(2.0.0)*

In 2.0.0, the Users module's bundled `Role`, `Permission`, `HasRolesAndPermissions` trait, and the three RBAC migrations have been removed in favor of the shared `artisanpack-ui/rbac` package. The class and trait names are preserved at the same paths — cms-framework's versions now subclass the rbac base — so existing imports keep working.

See [UPGRADE-RBAC.md](../UPGRADE-RBAC.md) for the full upgrade walkthrough. For most apps, the upgrade is:

```bash
composer require artisanpack-ui/rbac:^0.1
php artisan migrate
```

## Getting Started

If you're new to the system, we recommend starting with the [User Management](Users-User-Management) guide to understand the basics of user administration and then exploring the roles and permissions system.

For developers integrating with the user system, the [User API Reference](Users-User-Api-Reference) provides detailed information about available endpoints and their usage.
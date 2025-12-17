---
title: CMS Framework Documentation
---

# CMS Framework Documentation

Welcome to the CMS Framework documentation! This Laravel package provides a comprehensive content management system foundation with built-in user management, role-based access control, and extensible architecture.

## Overview

The CMS Framework is designed to help developers quickly build content management systems with robust user management and permission systems. It provides:

- **User Management System**: Complete CRUD operations for users
- **Role-Based Access Control (RBAC)**: Flexible roles and permissions system
- **RESTful API**: Clean API endpoints for all operations
- **Configurable User Model**: Use your existing User model
- **Laravel Integration**: Seamless integration with Laravel applications

## Getting Started

- [[Installation Guide]] - Setup and configuration instructions
- [[Configuration]] - Configuring the CMS Framework for your application
- [[Quick Start]] - Get up and running quickly

## User Management

- [[User Management]] - Managing users in your CMS
- [[Roles and Permissions]] - Understanding the RBAC system
- [[User API Reference]] - Complete API documentation

## Developer Resources

- [[Developer Guide]] - Extending and customizing the framework
- [[API Authentication]] - Securing your API endpoints
- [[Testing]] - Testing your CMS implementation
- [[Hooks and Events]] - Filters and actions you can use to extend functionality

## Core Components

### Users Module
The Users module provides comprehensive user management functionality including:
- User CRUD operations
- Role assignment and management
- Permission-based access control
- Configurable user model support

### Admin Module
Provides the building blocks for your admin area:
- Menu sections, pages, and subpages
- Automatic route registration under /admin with auth middleware
- Capability-based authorization
- Dashboard widgets

See [[Admin]] for details.

### Core Module
Provides cross-cutting services:
- Asset registration and retrieval for admin/public/auth contexts
- Filter hooks to modify asset collections

See [[Core]] for details.

### Settings Module
Provides application-wide configuration storage:
- Register keys with defaults, types, and sanitizers
- Retrieve and update values via helpers
- Backed by a database table with automatic casting

See [[Settings]] for details.

### Themes Module
Provides a flexible theme management system:
- Automatic theme discovery from configured directory
- Theme activation with cache management
- WordPress-style template hierarchy for content types
- View path registration for Laravel's Blade engine
- RESTful API endpoints for theme operations

See [[Themes]] for details.

### Models
- **User Model**: Uses your application's User model with HasRolesAndPermissions trait
- **Role Model**: Manages user roles with name and slug fields
- **Permission Model**: Manages individual permissions with name and slug fields

### API Endpoints
All user management operations are available through RESTful API endpoints:
- `GET /api/v1/users` - List users with pagination
- `POST /api/v1/users` - Create new user
- `GET /api/v1/users/{id}` - Get specific user
- `PUT/PATCH /api/v1/users/{id}` - Update user
- `DELETE /api/v1/users/{id}` - Delete user

## Configuration

The framework uses a simple configuration file to customize behavior:
- `user_model` - Specify your application's User model class

## Support

For issues, feature requests, and contributions, please refer to the project repository.

---

*This documentation covers CMS Framework v2.0.0+*

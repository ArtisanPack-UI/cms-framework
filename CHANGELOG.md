# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Deprecated

### Removed

### Fixed

### Security

## [1.0.0] - 2026-01-02

### Added

- Configuration publishing for module-specific configs
  - Plugins config: `php artisan vendor:publish --tag=cms-plugins-config`
  - Themes config: `php artisan vendor:publish --tag=cms-themes-config`
  - Updates config: `php artisan vendor:publish --tag=cms-updates-config`

### Changed

- Moved developer documentation to `docs/developer/` directory
  - `SKIPPED_TESTS.md` → `docs/developer/Skipped-Tests.md`
  - `COVERAGE.md` → `docs/developer/Test-Coverage.md`
- Updated documentation to reflect PHP 8.2 and Laravel 12 requirements

### Fixed

- Replaced deprecated `mime_content_type()` with `finfo_file()` in PluginManager
- Fixed code style inconsistency in PluginManager exception handling
- Documented all skipped tests with explanations

### Removed

- `V1_RELEASE_CHECKLIST.md` - internal development tracking file

## [1.0.0-beta1] - 2024-12-21

### Added

- Core Updates Module with automatic update checking and management
  - GitHub, GitLab, and Custom JSON update source support
  - Version-specific update downloads with prerelease filtering
  - Automatic backup creation before updates with rollback capability
  - ZIP extraction with nested directory handling
  - Path validation and security checks in backup operations
  - Comprehensive error logging during update operations
  - Artisan commands: `check-for-update`, `perform-update`, `rollback-update`
- Plugin System foundation (experimental)
  - Plugin model with activation/deactivation tracking
  - Plugin manager for lifecycle management
  - Plugin update manager integration
  - Plugin validation and installation exceptions
- Theme System foundation (experimental)
  - Theme manager with theme discovery
  - Theme activation mechanism
  - JSON manifest validation
- Comprehensive input sanitization throughout codebase
  - Applied `sanitizeText()` and `sanitizeInt()` to all user inputs
  - Protected database queries from SQL injection
  - Validated and sanitized all controller inputs
- Type declarations for improved IDE support
  - Added `Builder` type hints to all Eloquent scope methods
  - Added return type declarations across models
  - Improved parameter type hints in managers and services
- Database seeders for default data
  - RolesTableSeeder (Admin, Editor, User roles)
  - PermissionsTableSeeder (content, user, settings, system permissions)
  - SettingsTableSeeder (site configuration defaults)
- Exception hierarchy with base `CMSFrameworkException`
  - ValidationException for validation errors
  - NotFoundException for missing resources
  - UnauthorizedException for authorization failures
  - All module exceptions now extend CMSFrameworkException
- Comprehensive documentation
  - API documentation structure (`docs/api/README.md`)
  - Route registry (`docs/routes.md`)
  - Relationship documentation (`docs/relationships.md`)
  - Helper functions reference (`docs/helpers.md`)
  - Exception handling guide (`docs/exceptions.md`)
  - Skipped tests documentation (now at `docs/developer/Skipped-Tests.md`)
- Improved `.gitattributes` for cleaner package distribution

### Changed

- **License changed from GPL-3.0-or-later to MIT** for better framework compatibility
- Standardized all `@since` annotations to 1.0.0 (removed premature 2.0.0 references)
- Configuration system improvements
  - Fixed publish tag from `artisanpack-package-config` to `cms-framework-config`
  - Corrected config validation to use `artisanpack.cms-framework.user_model`
  - Updated error messages to reflect actual file paths
- Code style improvements (74% PHPCS error reduction)
  - Fixed spacing issues in `declare(strict_types = 1)` statements
  - Fixed reference operator spacing in closures
  - Improved array alignment and formatting
  - Fixed Yoda conditions for comparison safety

### Fixed

- Configuration validation mismatch between publish tag, file path, and config key
- Test configuration (fixed config key from `cms-framework` to `artisanpack.cms-framework`)
- Progress bar in update command (removed misleading fake progress)
- `glob()` error handling for backup operations
- Path traversal security issues in backup ZIP creation
- JSON parsing errors in UpdateCheckerFactory
- Doctrine/DBAL deprecation warnings in migrations
- 706 code style violations (reduced from 941 to 235 errors)
- Input sanitization security vulnerabilities across multiple modules
- Unskipped 2 notification tests (role-based notification functionality now fully tested)

### Security

- Added comprehensive input sanitization using ArtisanPackUI Security package
  - Sanitized all user inputs before database operations
  - Protected against XSS attacks with proper output escaping
  - Validated file paths to prevent directory traversal
- Enhanced authorization with proper policy enforcement
- Improved error handling to prevent information disclosure

### Breaking Changes

- Configuration file publish tag changed to `cms-framework-config`
- Configuration structure now uses `artisanpack.cms-framework` instead of `cms-framework`
- All `@since 2.0.0` annotations changed to `@since 1.0.0`

### Known Limitations

- Plugin system is experimental - full lifecycle hooks not yet implemented
- Theme system is experimental - asset compilation and child themes pending
- 4 plugin-related tests remain skipped (documented in `docs/developer/Skipped-Tests.md`)
- Test coverage report requires Xdebug/PCOV (recommended for CI/CD)
- 235 PHPCS code style warnings remain (mostly spacing and false positives)

## [0.2.4] - 2025-09-02

### Added

- Enhanced user migration with password reset tokens and sessions table support
- Password reset tokens table with email primary key, token storage, and timestamp tracking
- Sessions table with comprehensive session management including user ID foreign key, IP address tracking, user agent storage, and activity indexing
- Table existence checks to prevent conflicts during migration execution

## [0.2.3] - 2025-09-02

### Removed

- Complete removal of all media library references from CMS framework core
- Removed media-related API routes and controller imports from api.php
- Removed MediaLibraryServiceProvider registration from CMSFrameworkServiceProvider
- Removed media library integration documentation
- Removed media-related admin page references from development guide
- Cleaned up media library package discovery ignoring in test configuration

### Changed

- Updated comprehensive CMS development guide to remove media library integration examples
- Restructured package ecosystem documentation to reflect media library as separate package

## [0.2.2] - 2025-09-02

### Added

- Complete media library decoupling and cleanup functionality

## [0.2.1] - 2025-09-02

### Added

- Comprehensive media library integration documentation
- Integration guide for external `artisanpack-ui/media-library` package
- Migration instructions for transitioning from integrated media system

### Changed

- Decoupled media library functionality from CMS framework core
- Updated service provider to remove media manager bindings
- Refactored CMS configuration schema to support external media library integration

### Removed

- Built-in media management system (models, controllers, policies, tests)
- Internal media database migrations and factories
- MediaManager, MediaServiceProvider, and related media classes
- Media-related HTTP controllers, requests, and resources
- All media-related unit and feature tests
- Legacy media documentation

### Breaking Changes

- Media functionality now requires separate `artisanpack-ui/media-library` package installation### Added
- Comprehensive CMS development guide and API documentation
- Analytics system with page views, user sessions, and performance tracking
- Search functionality with full-text search and analytics
- Internationalization support with multi-language capabilities
- Health monitoring and system diagnostics
- Application Performance Monitoring (APM) with metrics collection
- Docker deployment setup with multi-service containers
- Performance testing suite with benchmarking tools
- Security testing suite including penetration testing
- Console commands for content, user, and system management
- Configuration validation and documentation generation
- Caching implementation with Redis support
- Structured logging and audit trail capabilities
- Rate limiting middleware for API protection
- Input sanitization utilities

### Changed

- Updated content management system with enhanced controllers
- Improved user management with additional authentication features
- Enhanced media management with better error handling
- Refined plugin and theme management systems
- Updated all policy classes with improved authorization logic
- Modernized database models with better relationships
- Enhanced API routes with comprehensive endpoints

### Fixed

- Critical security vulnerabilities with input validation
- Error handling and exception management
- Cache implementation and performance issues
- Authorization policy implementations
- Database query optimization
- API response formatting and error codes
- User authentication and session management

### Removed

- Temporary documentation files and test scripts
- Legacy configuration files
- Unused development artifacts

### Security

- Implemented comprehensive input sanitization
- Added CSRF protection across all forms
- Enhanced rate limiting for API endpoints
- Improved authorization checks in all policies
- Added security testing suite for vulnerability detection
- Implemented audit logging for security events
- MediaManagerInterface moved to external package namespace
- Media-related routes and API endpoints moved to external package

## [0.2.0] - 2025-09-01

## [0.1.0] - 2025-07-13

### Added

- Initial test release
- Core CMS framework functionality
- Content management system
- User management with authentication
- Plugin and theme support
- Admin interface and dashboard widgets
- Settings management
- Media management integration
- Two-factor authentication
- Progressive Web App (PWA) support
- Audit logging capabilities
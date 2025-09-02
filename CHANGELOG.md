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
---
title: Installation
---

# Installation Guide

This guide provides detailed installation and setup instructions for the ArtisanPack UI CMS Framework.

## System Requirements

Before installing the CMS Framework, ensure your system meets the following requirements:

- **PHP**: 8.2 or higher
- **Laravel**: 12.0 or higher
- **Laravel Sanctum**: 4.1 or higher
- **Database**: MySQL 8.0+, PostgreSQL 13+, or SQLite 3.35+
- **Memory**: Minimum 256MB PHP memory limit
- **Extensions**: Required PHP extensions include `ext-json`, `ext-mbstring`, `ext-openssl`, `ext-pdo`, `ext-tokenizer`, `ext-xml`

## Installation Steps

### 1. Install via Composer

Install the package using Composer:

```bash
composer require artisanpack-ui/cms-framework
```

### 2. Publish Configuration Files

Publish the configuration files to customize the CMS settings:

```bash
php artisan vendor:publish --tag=cms-config
```

This will create the following configuration files:
- `config/cms.php` - Main CMS configuration
- `config/cms-content.php` - Content types configuration  
- `config/cms-media.php` - Media management configuration
- `config/cms-users.php` - User management configuration

### 3. Run Database Migrations

Create the necessary database tables:

```bash
php artisan migrate
```

This will create tables for:
- Content management (posts, pages, taxonomies)
- User management (users, roles, permissions)
- Media library
- Settings and configuration
- Audit logging

### 4. Publish Assets (Optional)

If you need to customize the admin interface assets:

```bash
php artisan vendor:publish --tag=cms-assets
```

### 5. Seed Default Data (Optional)

To populate your CMS with default content and settings:

```bash
php artisan db:seed --class=CMSFrameworkSeeder
```

## Environment Configuration

Add the following environment variables to your `.env` file:

```env
# CMS Framework Configuration
CMS_ENABLED=true
CMS_DEFAULT_ROLE=editor
CMS_ALLOW_REGISTRATION=true

# Media Configuration  
CMS_MEDIA_DISK=public
CMS_MEDIA_MAX_SIZE=10240
CMS_MEDIA_ALLOWED_TYPES=jpg,jpeg,png,gif,pdf,doc,docx

# Two-Factor Authentication
CMS_2FA_ENABLED=true
CMS_2FA_ISSUER="Your App Name"

# PWA Configuration
CMS_PWA_ENABLED=true
CMS_PWA_NAME="Your CMS"
CMS_PWA_SHORT_NAME="CMS"
```

## Verification

To verify the installation was successful, run:

```bash
php artisan cms:status
```

This command will check:
- Database connectivity
- Required tables exist
- Configuration files are present
- Dependencies are installed

## Post-Installation Setup

### 1. Create an Admin User

Create your first admin user:

```bash
php artisan cms:user:create --admin
```

### 2. Configure Content Types

Register your custom content types in a service provider or configuration file:

```php
use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;

app(ContentTypeManager::class)->register('article', [
    'name' => 'Article',
    'plural' => 'Articles',
    'description' => 'Blog articles and posts',
    'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
]);
```

### 3. Set Up Authentication

Configure Laravel Sanctum for API authentication:

```bash
php artisan sanctum:install
php artisan migrate
```

## Troubleshooting

### Common Issues

**1. Migration Errors**
- Ensure database connection is configured correctly
- Check that your database user has CREATE and ALTER permissions

**2. Permission Errors**
- Verify storage and bootstrap/cache directories are writable
- Run `php artisan storage:link` to create storage symlink

**3. Class Not Found Errors**
- Run `composer dump-autoload` to regenerate autoloader
- Clear configuration cache: `php artisan config:clear`

**4. Memory Limit Issues**
- Increase PHP memory limit in php.ini
- Consider using `php -d memory_limit=512M artisan migrate`

### Getting Help

If you encounter issues not covered here:

1. Check the [troubleshooting guide](performance.md#troubleshooting)
2. Review the [GitHub issues](https://github.com/artisanpack-ui/cms-framework/issues)
3. Consult the [API documentation](api.md)
4. Join our [community discussions](https://github.com/artisanpack-ui/cms-framework/discussions)

## Next Steps

Once installation is complete, continue with:

- [Configuration Guide](configuration.md) - Configure your CMS settings
- [Usage Guide](usage.md) - Learn how to use the CMS features
- [API Documentation](api.md) - Integrate with the REST API
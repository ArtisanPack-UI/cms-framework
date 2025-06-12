# CMS Framework Core Components

This document provides detailed information about the core components of the ArtisanPack UI CMS Framework.

## CMSFramework Class

The `CMSFramework` class serves as the central framework for managing and interacting with various modules of the Content Management System (CMS).

### Namespace
```php
namespace ArtisanPackUI\CMSFramework;
```

### Properties

- `$modules`: Array of all registered modules
- `$adminModules`: Array of admin modules
- `$publicModules`: Array of public modules
- `$authModules`: Array of auth modules
- `$functions`: The Functions utility instance

### Methods

#### __construct()
Registers all modules with the CMSFramework and initializes them.

**@since** 1.0.0

#### getModules(): array
Returns an array of modules to register with the CMSFramework. By default, it includes the Settings module and allows for customization through the `ap.modules.list` filter.

**@since** 1.0.0

**@return** array List of modules to register.

#### init(): void
Initializes all registered modules by calling their `init()` method and triggers the `ap.init` action.

**@since** 1.0.0

#### adminInit(): void
Initializes all admin modules by calling their `adminInit()` method and triggers the `ap.admin.init` action.

**@since** 1.0.0

#### publicInit(): void
Initializes all public modules by calling their `publicInit()` method and triggers the `ap.public.init` action.

**@since** 1.0.0

#### authInit(): void
Initializes all auth modules by calling their `authInit()` method and triggers the `ap.auth.init` action.

**@since** 1.0.0

#### functions(): Functions
Returns the Functions utility instance, which includes an array of functions that have been registered with the CMSFramework.

**@since** 1.0.0

**@return** Functions The Functions utility instance.

## CMSManager Class

The `CMSManager` class acts as a dynamic dispatcher for feature-specific managers, providing a mechanism to handle method calls that are routed through either instance or static invocation.

### Namespace
```php
namespace ArtisanPackUI\CMSFramework;
```

### Properties

- `$featureManagers`: Registry of feature managers, mapping feature names to their respective manager classes.

### Methods

#### __CALLSTATIC(string $method, array $parameters): mixed
Dynamically handles static method calls to the class.

**@since** 1.0.0

**@param** string $method The name of the method being called.
**@param** array $parameters The parameters passed to the method.
**@return** mixed The result from the resolved feature manager or delegated method.

#### __CALL(string $method, array $parameters): mixed
Dynamically handles method calls to the class. Attempts to resolve the method to a registered feature manager or delegates to a feature manager if a prefixed method is detected.

**@since** 1.0.0

**@param** string $method The name of the method being called.
**@param** array $parameters The parameters passed to the method.
**@return** mixed The result from the resolved feature manager or delegated method.

## CMSFrameworkServiceProvider Class

The `CMSFrameworkServiceProvider` class handles the registration and bootstrapping of the framework within a Laravel application.

### Namespace
```php
namespace ArtisanPackUI\CMSFramework;
```

### Methods

#### register(): void
Registers a singleton instance of the CMSFramework within the application container.

**@since** 1.0.0

**@return** void

#### boot(): void
Boots the CMS framework and loads database migration files and views.

**@since** 1.0.0

**@return** void

#### getMigrationDirectories(): array
Returns an array of migration directories to load, allowing for customization through the `ap.migrations.directories` filter.

**@since** 1.0.0

**@return** array List of migration directories.

#### loadViewsFromDirectories($directories): void
Loads views from the specified directories.

**@since** 1.0.0

**@param** array $directories List of directories to load views from.
**@return** void

#### getViewsDirectories(): array
Returns an array of view directories to load, allowing for customization through the `ap.views.directories` filter.

**@since** 1.0.0

**@return** array List of view directories.

## AuthServiceProvider Class

The `AuthServiceProvider` class handles the registration of policies for the CMS Framework's models, enabling Laravel's authorization features for the application.

### Namespace
```php
namespace ArtisanPackUI\CMSFramework;
```

### Properties

- `$policies`: The policy mappings for the application, mapping model classes to their corresponding policy classes.

### Methods

#### boot(): void
Registers the policies defined in the $policies property with the Laravel authorization system.

**@since** 1.0.0

**@return** void

## Accessing the Framework

### Global Helper Function

The framework provides a global helper function `cmsFramework()` that returns an instance of the Functions utility, making it easier to use the framework's functionality throughout an application.

```php
function cmsFramework()
{
    global $cmsFramework;

    if (is_null($cmsFramework)) {
        $cmsFramework = new CMSFramework();
    }

    return $cmsFramework->functions();
}
```

### Facade

The framework also provides a Laravel Facade for accessing the CMSFramework instance.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Facades;
```

#### Usage

The facade is registered as "Package" in the Laravel service container, so you can use it like this:

```php
use ArtisanPackUI\CMSFramework\Facades\CMSFramework;

// or using the alias
use Package;

// Access framework functionality
CMSFramework::functions();
```

## Module Interfaces

The framework defines several interfaces that modules can implement:

### Module Interface
The base interface that all modules must implement.

- `getSlug(): string`: Returns a string identifier for the module
- `functions(): array`: Returns an array of functions that the module provides
- `init(): void`: Initializes the module

### AdminModule Interface
Extends the Module interface for admin-specific functionality.

- `adminInit(): void`: Initializes admin-specific functionality

### PublicModule Interface
Extends the Module interface for public-facing functionality.

- `publicInit(): void`: Initializes public-facing functionality

### AuthModule Interface
Extends the Module interface for authentication-specific functionality.

- `authInit(): void`: Initializes authentication-specific functionality

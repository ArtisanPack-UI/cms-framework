---
title: Implementing Themes in Your ArtisanPack UI CMS
---

# Implementing Themes in Your ArtisanPack UI CMS

The ArtisanPack UI CMS Framework provides a robust and modular foundation for building Content Management Systems, and the newly integrated themes feature allows for flexible and customizable front-end designs. This guide will walk you through the steps to implement and manage themes within your CMS application.

## I. Understanding the Theme Architecture

The theme implementation in ArtisanPack UI mirrors the plugin architecture, leveraging Laravel's extensibility and the Eventy system. Key components include:

- **ArtisanPackUI\CMSFramework\Theme\Theme.php**: This is an abstract base class provided by the CMS Framework. All custom themes you create will extend this class. It defines common properties like $name, $slug, $version, $author, and methods such as register(), boot(), registerMigrations(), registerSettings(), and registerPermissions(). It also provides helper methods like asset() and path() for accessing theme resources.

- **ArtisanPackUI\CMSFramework\Theme\ThemeManager.php**: This class, part of the CMS Framework, is responsible for discovering, activating, and deactivating themes. It interacts with your application's settings to determine the active theme.

- **ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider.php**: The main service provider for the CMS Framework, this class is modified to register the ThemeManager and to dynamically load the currently active theme's ThemeServiceProvider and main Theme class during the application boot process.

- **Theme-Specific ThemeServiceProvider.php**: Each individual theme (e.g., App\Themes\MyAwesomeTheme\ThemeServiceProvider.php) will have its own service provider. This provider is responsible for registering the theme's views and migrations with the CMS Framework via Eventy filters.

- **Theme-Specific Theme.php**: Each individual theme will also have its main Theme class (e.g., App\Themes\MyAwesomeTheme\Theme.php) which extends the abstract ArtisanPackUI\CMSFramework\Theme\Theme class. This is where theme developers define their theme's metadata and register its specific actions and filters.

- **config/cms.php**: This configuration file includes a theme.active setting that specifies the default active theme. This setting can be overridden in the database.

## II. Setting Up Your CMS for Themes

To enable and manage themes in your CMS, you'll need to ensure your application has the necessary directory structure and the core framework components are updated.

### 1. Update config/cms.php

Ensure your config/cms.php file includes the theme.active configuration. This sets a default theme if no other theme is activated.

```php
// config/cms.php

return [
    // ... other CMS configurations ...

    'theme' => [
        /**
         * The currently active theme for the CMS.
         *
         * This value determines which theme's assets and functionalities are loaded.
         * It can be overridden in the database via the SettingsManager.
         *
         * @since 1.0.0
         * @var string
         */
        'active' => env( 'CMS_ACTIVE_THEME', 'default-artisanpack-theme' ), // Define your default theme name here.
    ],

    // ...
];
```

### 2. Ensure CMSFrameworkServiceProvider.php is Updated

Verify that the ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider.php in your vendor directory (or wherever you've placed the framework's source) includes the ThemeManager registration and the loadActiveTheme() method call in its boot() method. This allows the framework to correctly load your themes.

(The full content of the updated CMSFrameworkServiceProvider.php was provided in the previous response and should be applied to your framework's source code.)

### 3. Create the themes Directory

In the root of your Laravel application, create a themes directory. This is where all your custom themes will reside.

```
/your-laravel-app
├── app/
├── config/
├── database/
├── public/
├── resources/
│   └── views/
├── themes/           <-- Create this directory
│   ├── my-custom-theme/
│   └── default-artisanpack-theme/
└── vendor/
```

## III. Developing a Custom Theme

When creating a new theme for your ArtisanPack UI CMS, follow this structure and these guidelines:

### 1. Theme Directory Structure

Inside the themes directory, create a new subdirectory for your theme (e.g., my-custom-theme). Within this theme directory, organize your files as follows:

```
/themes/my-custom-theme/
├── src/
│   ├── Theme.php                <-- Your main theme class, extending BaseTheme
│   └── ThemeServiceProvider.php <-- Your theme's service provider
├── composer.json                <-- Composer file for theme metadata and autoloading
├── resources/
│   ├── views/                   <-- Your Blade templates (e.g., home.blade.php, layouts/app.blade.php)
│   └── assets/
│       ├── css/
│       │   └── style.css
│       └── js/
│           └── script.js
├── database/
│   └── migrations/              <-- Optional: theme-specific database migrations
└── public/                      <-- Optional: for assets if not using Laravel Mix/Vite
    ├── css/
    ├── js/
    └── images/
```

### 2. Create composer.json for Your Theme

Every theme needs a composer.json file for the ThemeManager to discover it and for Composer to autoload its classes.

For your theme's composer.json file (located at `themes/my-custom-theme/composer.json`):

```json
{
    "name": "your-vendor/my-custom-theme",
    "description": "A custom theme for my ArtisanPack UI CMS.",
    "type": "project",
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your-email@example.com"
        }
    ],
    "autoload": {
        "psr-4": {
            "App\\Themes\\MyCustomTheme\\": "src/"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

After creating or modifying composer.json for any theme, always run:

```bash
composer dump-autoload
```

This ensures Composer is aware of your theme's classes and can autoload them.

### 3. Create Your Theme's ThemeServiceProvider.php

This service provider registers your theme's specific views and migrations with the CMS Framework.

For your theme's service provider (located at `themes/my-custom-theme/src/ThemeServiceProvider.php`):

```php
<?php
/**
 * Theme Service Provider for My Custom Theme.
 *
 * This class registers and bootstraps the theme's services, including views,
 * assets, and any theme-specific functionalities.
 *
 * @package    App\Themes\MyCustomTheme
 * @subpackage App\Themes\MyCustomTheme\ThemeServiceProvider
 * @since      1.0.0
 */

namespace App\Themes\MyCustomTheme;

use Illuminate\Support\ServiceProvider;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Registers and bootstraps My Custom Theme within the application.
 *
 * Responsible for binding the theme to the container,
 * initializing components, and loading theme resources.
 *
 * @since 1.0.0
 * @see   ServiceProvider
 */
class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register theme services.
     *
     * @since 1.0.0
     * @return void
     */
    public function register(): void
    {
        // Any theme-specific service bindings can go here.
    }

    /**
     * Boot theme services.
     *
     * Loads theme views and migrations by hooking into ArtisanPack UI filters.
     *
     * @since 1.0.0
     * @return void
     */
    public function boot(): void
    {
        // Register theme-specific views.
        Eventy::filter( 'ap.cms.views.directories', function ( array $directories ) {
            $directories[] = [
                'path'      => __DIR__ . '/../resources/views',
                'namespace' => 'my-custom-theme', // Use a unique namespace for your theme's views.
            ];
            return $directories;
        } );

        // Register theme-specific migrations (optional).
        Eventy::filter( 'ap.cms.migrations.directories', function ( array $directories ) {
            $directories[] = __DIR__ . '/../database/migrations';
            return $directories;
        } );
    }
}
```

### 4. Create Your Main Theme Class (Theme.php)

This is the core of your theme. It extends ArtisanPackUI\CMSFramework\Theme\Theme and defines your theme's meta-information and hooks.

For your main theme class (located at `themes/my-custom-theme/src/Theme.php`):

```php
<?php
/**
 * Main Theme class for My Custom Theme.
 *
 * This class serves as the primary interface for theme developers to register
 * actions, filters, and other theme-specific functionalities within the ArtisanPack UI CMS.
 *
 * @package    App\Themes\MyCustomTheme
 * @subpackage App\Themes\MyCustomTheme\Theme
 * @since      1.0.0
 */

namespace App\Themes\MyCustomTheme;

use ArtisanPackUI\CMSFramework\Theme\Theme as BaseTheme; // Important: Import the base abstract class
use TorMorten\Eventy\Facades\Eventy;

/**
 * Handles theme initialization and registration of hooks for My Custom Theme.
 *
 * Theme developers will extend this class to define their theme's behavior,
 * including its metadata and event registrations.
 *
 * @since 1.0.0
 * @see \ArtisanPackUI\CMSFramework\Theme\Theme
 */
class Theme extends BaseTheme
{
    /**
     * The human-friendly name of the theme.
     *
     * @since 1.0.0
     * @var string
     */
    public string $name = 'My Custom Theme';

    /**
     * The unique, URL-friendly slug of the theme.
     * This slug should match your theme's directory name.
     *
     * @since 1.0.0
     * @var string
     */
    public string $slug = 'my-custom-theme';

    /**
     * The current version of the theme.
     *
     * @since 1.0.0
     * @var string
     */
    public string $version = '1.0.0';

    /**
     * The author of the theme.
     *
     * @since 1.0.0
     * @var string
     */
    public string $author = 'Your Name';

    /**
     * A short description of what the theme does.
     *
     * @since 1.0.0
     * @var string|null
     */
    public string|null $description = 'A starter theme for the ArtisanPack UI CMS.';

    /**
     * Boots theme-specific services and registers hooks.
     *
     * This method is called during the theme activation process to set up
     * theme functionalities, such as enqueueing scripts and styles, or
     * registering custom components and routes specific to the theme.
     *
     * @since 1.0.0
     * @return void
     */
    public function boot(): void
    {
        /**
         * Fires after My Custom Theme has been loaded.
         *
         * This action allows other modules or plugins to interact with
         * or extend the theme's functionalities after it has been fully initialized.
         *
         * @since 1.0.0
         *
         * @param \App\Themes\MyCustomTheme\Theme $this The current theme instance.
         */
        Eventy::action( 'my_custom_theme.loaded', $this );

        // Example: Override the CMS title filter.
        Eventy::filter( 'ap.cms.title', function ( string $title ) {
            return $title . ' - ' . $this->name;
        } );

        // Example: Enqueue theme assets.
        Eventy::action( 'ap.cms.enqueue_scripts', function () {
            echo '<link rel="stylesheet" href="' . $this->asset( 'css/style.css' ) . '">';
            echo '<script src="' . $this->asset( 'js/script.js' ) . '"></script>';
        } );

        // Register theme views in Laravel's view system (optional, as ServiceProvider already does).
        // view()->addNamespace( 'my-custom-theme', $this->path( 'resources/views' ) );
    }

    /**
     * Defines any database migrations specific to this theme.
     *
     * If your theme requires its own database tables (e.g., for custom options,
     * demo content, or specific features), list their migration paths here.
     *
     * @since 1.0.0
     * @return array An array of paths to migration directories relative to the theme's root.
     */
    public function registerMigrations(): array
    {
        return [
            $this->path( 'database/migrations' ),
        ];
    }

    /**
     * Defines any settings that this theme introduces.
     *
     * Theme-specific settings can be automatically registered and managed
     * via the CMS's SettingsManager.
     *
     * @since 1.0.0
     * @return array An array of setting definitions. Each definition should include
     * 'key', 'default', 'type', and 'description'.
     */
    public function registerSettings(): array
    {
        return [
            [
                'key'         => $this->slug . '.header_text',
                'default'     => 'Welcome to My Custom Theme!',
                'type'        => 'string',
                'description' => 'The text displayed in the theme header.',
            ],
            [
                'key'         => $this->slug . '.show_author_info',
                'default'     => true,
                'type'        => 'boolean',
                'description' => 'Determines whether author information is displayed on posts.',
            ],
        ];
    }

    /**
     * Defines any permissions this theme introduces.
     *
     * This method allows you to define custom permissions related to your theme's
     * specific functionalities, which can then be integrated into your CMS's
     * role and permission management system.
     *
     * @since 1.0.0
     * @return array An array of permission definitions.
     */
    public function registerPermissions(): array
    {
        return [
            'my_custom_theme.customize' => [
                'label'       => 'Customize My Custom Theme',
                'description' => 'Allows users to access and modify My Custom Theme options.',
            ],
        ];
    }
}
```

### 5. Create Theme Views

Place your Blade templates in themes/my-custom-theme/resources/views/. You can reference them using the namespace you defined in ThemeServiceProvider.php.

```blade
{{-- themes/my-custom-theme/resources/views/welcome.blade.php --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@php Eventy::filter( 'ap.cms.title', 'My CMS' ); @endphp</title>
    @php Eventy::action( 'ap.cms.enqueue_scripts' ); @endphp
</head>
<body>
    <h1>{{ \ArtisanPackUI\CMSFramework\CMSManager::settings()->get('my-custom-theme.header_text') }}</h1>
    <p>This is a page rendered by My Custom Theme.</p>
</body>
</html>
```

To render this view in your Laravel application's routes:

For rendering the theme view in your routes file (located at `routes/web.php` or a theme-specific route file):

```php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('my-custom-theme::welcome'); // Using the theme's namespace.
});
```

## IV. Managing Themes in Your CMS

To provide a fully functional theme management experience, you'll need to build an administrative interface and Artisan commands.

### 1. Artisan Commands for Theme Management

Create custom Artisan commands that utilize the ThemeManager to interact with themes.

#### ThemeActivateCommand.php (Example)

For the theme activation command (located at `app/Console/Commands/ThemeActivateCommand.php`):

```php
<?php

namespace App\Console\Commands;

use ArtisanPackUI\CMSFramework\Theme\ThemeManager;
use Illuminate\Console\Command;

class ThemeActivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'theme:activate {slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activates a specific theme by its slug.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle( ThemeManager $themeManager )
    {
        $themeSlug = $this->argument( 'slug' );

        try {
            if ( $themeManager->activateTheme( $themeSlug ) ) {
                $this->info( sprintf( 'Theme "%s" activated successfully.', $themeSlug ) );
            } else {
                $this->error( sprintf( 'Failed to activate theme "%s".', $themeSlug ) );
            }
        } catch ( \InvalidArgumentException $e ) {
            $this->error( $e->getMessage() );
            return Command::FAILURE;
        } catch ( \Exception $e ) {
            $this->error( 'An unexpected error occurred: ' . $e->getMessage() );
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
```

#### ThemeDeactivateCommand.php (Example)

For the theme deactivation command (located at `app/Console/Commands/ThemeDeactivateCommand.php`):

```php
<?php

namespace App\Console\Commands;

use ArtisanPackUI\CMSFramework\Theme\ThemeManager;
use Illuminate\Console\Command;

class ThemeDeactivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'theme:deactivate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivates the currently active theme.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle( ThemeManager $themeManager )
    {
        if ( $themeManager->deactivateTheme() ) {
            $this->info( 'Active theme deactivated successfully.' );
        } else {
            $this->warn( 'No active theme to deactivate.' );
        }

        return Command::SUCCESS;
    }
}
```

#### ThemeListCommand.php (Example)

For the theme listing command (located at `app/Console/Commands/ThemeListCommand.php`):

```php
<?php

namespace App\Console\Commands;

use ArtisanPackUI\CMSFramework\Theme\ThemeManager;
use Illuminate\Console\Command;

class ThemeListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'theme:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists all available themes and their status.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle( ThemeManager $themeManager )
    {
        $themes = $themeManager->scanThemes();

        if ( empty( $themes ) ) {
            $this->info( 'No themes found.' );
            return Command::SUCCESS;
        }

        $headers = [ 'Name', 'Slug', 'Version', 'Author', 'Status', 'Description' ];
        $data    = [];

        foreach ( $themes as $slug => $themeData ) {
            $data[] = [
                $themeData['name'],
                $slug,
                $themeData['version'] ?? 'N/A', // Add version from theme.php
                $themeData['author'] ?? 'N/A', // Add author from theme.php
                $themeData['status'],
                $themeData['description'],
            ];
        }

        $this->table( $headers, $data );

        return Command::SUCCESS;
    }
}
```

Register these commands in app/Console/Kernel.php:

For registering the commands in your application's Kernel (located at `app/Console/Kernel.php`):

```php
protected $commands = [
    // ... other commands
    \App\Console\Commands\ThemeActivateCommand::class,
    \App\Console\Commands\ThemeDeactivateCommand::class,
    \App\Console\Commands\ThemeListCommand::class,
];
```

Now you can run these commands from your terminal:

```bash
php artisan theme:list
php artisan theme:activate my-custom-theme
php artisan theme:deactivate
```

### 2. CMS Dashboard User Interface (Conceptual)

For a user-friendly experience, you'll want to build a "Themes" section in your CMS dashboard:

- **Theme Listing**: Display a table or grid of all themes returned by ThemeManager::scanThemes(). Show their name, version, author, description, and current status (active/inactive).
- **Activate/Deactivate Buttons**: For each theme, provide buttons to "Activate" or "Deactivate" it. These buttons would trigger routes that call the corresponding methods on the ThemeManager.
- **Theme Options**: If a theme implements registerSettings(), dynamically generate forms in the UI to allow users to configure these theme-specific settings using the SettingsManager.

By following this guide, you will successfully implement a flexible and manageable themes feature in your ArtisanPack UI CMS, enabling developers to create custom front-ends for their applications.

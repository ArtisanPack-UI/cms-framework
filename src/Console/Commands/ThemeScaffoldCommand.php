<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

/**
 * Theme scaffold command
 * 
 * This command generates the basic structure for new CMS themes.
 * It creates directories, files, and boilerplate code for theme development.
 */
class ThemeScaffoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:make:theme 
                           {name : The theme name}
                           {--author= : Theme author name}
                           {--description= : Theme description}
                           {--version=1.0.0 : Theme version}
                           {--force : Overwrite existing theme}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new CMS theme with scaffold structure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $name = $this->argument('name');
            $studlyName = Str::studly($name);
            $kebabName = Str::kebab($name);
            $snakeName = Str::snake($name);
            
            $this->info("Creating theme: {$name}");
            
            // Get theme metadata
            $metadata = $this->getThemeMetadata($name);
            
            // Define theme paths
            $themePath = app_path("Themes/{$studlyName}");
            
            // Check if theme already exists
            if (File::exists($themePath) && !$this->option('force')) {
                $this->error("Theme '{$name}' already exists. Use --force to overwrite.");
                return Command::FAILURE;
            }
            
            // Create theme structure
            $this->createThemeStructure($themePath, $studlyName, $kebabName, $snakeName, $metadata);
            
            $this->info("✅ Theme '{$name}' created successfully!");
            $this->info("   Path: {$themePath}");
            $this->info("   Files created:");
            $this->info("     - Theme service provider");
            $this->info("     - Theme configuration");
            $this->info("     - Base theme class");
            $this->info("     - Sample templates");
            $this->info("     - Asset directories");
            $this->info("     - README documentation");
            
            $this->warn("Next steps:");
            $this->line("1. Review the generated files in {$themePath}");
            $this->line("2. Customize the theme configuration");
            $this->line("3. Add your CSS/JS assets");
            $this->line("4. Create your template files");
            $this->line("5. Activate the theme: php artisan cms:theme:activate {$kebabName}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error creating theme: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Get theme metadata from options or prompts
     */
    private function getThemeMetadata(string $name): array
    {
        return [
            'name' => $name,
            'author' => $this->option('author') ?: $this->ask('Theme author', 'Theme Developer'),
            'description' => $this->option('description') ?: $this->ask('Theme description', "A custom theme for the CMS"),
            'version' => $this->option('version'),
        ];
    }
    
    /**
     * Create theme directory structure and files
     */
    private function createThemeStructure(string $themePath, string $studlyName, string $kebabName, string $snakeName, array $metadata): void
    {
        // Create directories
        $directories = [
            $themePath,
            "{$themePath}/resources/views",
            "{$themePath}/resources/views/layouts",
            "{$themePath}/resources/views/components",
            "{$themePath}/resources/views/pages",
            "{$themePath}/public/css",
            "{$themePath}/public/js",
            "{$themePath}/public/images",
            "{$themePath}/config",
        ];
        
        foreach ($directories as $dir) {
            File::makeDirectory($dir, 0755, true);
        }
        
        // Create theme service provider
        $this->createServiceProvider($themePath, $studlyName, $metadata);
        
        // Create theme configuration
        $this->createThemeConfig($themePath, $kebabName, $metadata);
        
        // Create base theme class
        $this->createThemeClass($themePath, $studlyName, $metadata);
        
        // Create sample templates
        $this->createSampleTemplates($themePath, $studlyName);
        
        // Create package.json for assets
        $this->createPackageJson($themePath, $kebabName, $metadata);
        
        // Create README
        $this->createReadme($themePath, $studlyName, $metadata);
        
        // Create gitignore
        $this->createGitignore($themePath);
    }
    
    /**
     * Create theme service provider
     */
    private function createServiceProvider(string $themePath, string $studlyName, array $metadata): void
    {
        $content = "<?php

namespace App\\Themes\\{$studlyName};

use Illuminate\\Support\\ServiceProvider;

/**
 * {$studlyName} Theme Service Provider
 * 
 * @author {$metadata['author']}
 * @version {$metadata['version']}
 */
class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register theme services
     */
    public function register(): void
    {
        // Register theme-specific services
    }
    
    /**
     * Bootstrap theme services
     */
    public function boot(): void
    {
        // Load theme views
        \$this->loadViewsFrom(__DIR__ . '/resources/views', '{$studlyName}');
        
        // Publish theme assets
        \$this->publishes([
            __DIR__ . '/public' => public_path('themes/{$studlyName}'),
        ], 'theme-assets');
        
        // Load theme routes if they exist
        if (file_exists(__DIR__ . '/routes/web.php')) {
            \$this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        }
    }
}";
        
        File::put("{$themePath}/ThemeServiceProvider.php", $content);
    }
    
    /**
     * Create theme configuration file
     */
    private function createThemeConfig(string $themePath, string $kebabName, array $metadata): void
    {
        $content = "<?php

return [
    'name' => '{$metadata['name']}',
    'slug' => '{$kebabName}',
    'description' => '{$metadata['description']}',
    'author' => '{$metadata['author']}',
    'version' => '{$metadata['version']}',
    'requires_cms_version' => '^1.0',
    
    // Theme features
    'supports' => [
        'menus' => true,
        'widgets' => true,
        'customizer' => true,
        'thumbnails' => true,
    ],
    
    // Template files
    'templates' => [
        'home' => 'Home Page',
        'page' => 'Default Page',
        'post' => 'Single Post',
        'archive' => 'Archive',
        'search' => 'Search Results',
    ],
    
    // Menu locations
    'menus' => [
        'primary' => 'Primary Navigation',
        'footer' => 'Footer Menu',
    ],
    
    // Widget areas
    'sidebars' => [
        'main-sidebar' => [
            'name' => 'Main Sidebar',
            'description' => 'Primary sidebar for pages and posts',
        ],
        'footer-widgets' => [
            'name' => 'Footer Widgets',
            'description' => 'Widget area in the footer',
        ],
    ],
];";
        
        File::put("{$themePath}/config/theme.php", $content);
    }
    
    /**
     * Create base theme class
     */
    private function createThemeClass(string $themePath, string $studlyName, array $metadata): void
    {
        $content = "<?php

namespace App\\Themes\\{$studlyName};

use ArtisanPackUI\\CMSFramework\\Features\\Themes\\BaseTheme;

/**
 * {$studlyName} Theme
 * 
 * @author {$metadata['author']}
 * @version {$metadata['version']}
 */
class {$studlyName} extends BaseTheme
{
    /**
     * Initialize the theme
     */
    public function init(): void
    {
        // Register theme hooks and filters
        \$this->registerHooks();
        
        // Enqueue theme assets
        \$this->enqueueAssets();
    }
    
    /**
     * Register theme hooks and filters
     */
    protected function registerHooks(): void
    {
        // Add theme support
        add_action('cms.theme.setup', function () {
            // Add theme support for features
            add_theme_support('post-thumbnails');
            add_theme_support('menus');
            add_theme_support('widgets');
        });
        
        // Customize theme output
        add_filter('cms.theme.body_class', [\$this, 'bodyClasses']);
    }
    
    /**
     * Enqueue theme assets
     */
    protected function enqueueAssets(): void
    {
        add_action('cms.theme.enqueue_scripts', function () {
            // Enqueue CSS
            wp_enqueue_style(
                '{$studlyName}-style',
                asset('themes/{$studlyName}/css/style.css'),
                [],
                '{$metadata['version']}'
            );
            
            // Enqueue JavaScript
            wp_enqueue_script(
                '{$studlyName}-script',
                asset('themes/{$studlyName}/js/script.js'),
                [],
                '{$metadata['version']}',
                true
            );
        });
    }
    
    /**
     * Add custom body classes
     */
    public function bodyClasses(\$classes): array
    {
        \$classes[] = 'theme-{$studlyName}';
        return \$classes;
    }
}";
        
        File::put("{$themePath}/{$studlyName}.php", $content);
    }
    
    /**
     * Create sample templates
     */
    private function createSampleTemplates(string $themePath, string $studlyName): void
    {
        // Main layout
        $layoutContent = "<!DOCTYPE html>
<html lang=\"{{ app()->getLocale() }}\">
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <title>@yield('title', config('app.name'))</title>
    
    @stack('meta')
    @stack('styles')
</head>
<body class=\"@yield('body-class')\">
    <header class=\"site-header\">
        <div class=\"container\">
            <h1 class=\"site-title\">
                <a href=\"{{ url('/') }}\">{{ config('app.name') }}</a>
            </h1>
            
            @include('{$studlyName}::components.navigation')
        </div>
    </header>
    
    <main class=\"site-main\">
        <div class=\"container\">
            @yield('content')
        </div>
    </main>
    
    <footer class=\"site-footer\">
        <div class=\"container\">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </footer>
    
    @stack('scripts')
</body>
</html>";
        
        File::put("{$themePath}/resources/views/layouts/app.blade.php", $layoutContent);
        
        // Navigation component
        $navContent = "<nav class=\"main-navigation\">
    <ul class=\"nav-menu\">
        <li><a href=\"{{ url('/') }}\">Home</a></li>
        <li><a href=\"{{ url('/about') }}\">About</a></li>
        <li><a href=\"{{ url('/contact') }}\">Contact</a></li>
    </ul>
</nav>";
        
        File::put("{$themePath}/resources/views/components/navigation.blade.php", $navContent);
        
        // Home page template
        $homeContent = "@extends('{$studlyName}::layouts.app')

@section('title', 'Home - ' . config('app.name'))

@section('content')
<div class=\"hero-section\">
    <h1>Welcome to {{ config('app.name') }}</h1>
    <p>This is the home page of your CMS theme.</p>
</div>

<div class=\"content-section\">
    <h2>Latest Posts</h2>
    {{-- Display latest posts here --}}
</div>
@endsection";
        
        File::put("{$themePath}/resources/views/pages/home.blade.php", $homeContent);
    }
    
    /**
     * Create package.json for asset management
     */
    private function createPackageJson(string $themePath, string $kebabName, array $metadata): void
    {
        $content = [
            'name' => $kebabName . '-theme',
            'version' => $metadata['version'],
            'description' => $metadata['description'],
            'author' => $metadata['author'],
            'scripts' => [
                'dev' => 'npm run development',
                'development' => 'mix',
                'watch' => 'mix watch',
                'watch-poll' => 'mix watch -- --watch-options-poll=1000',
                'hot' => 'mix watch --hot',
                'prod' => 'npm run production',
                'production' => 'mix --production'
            ],
            'devDependencies' => [
                'laravel-mix' => '^6.0.49',
                'sass' => '^1.56.1',
                'sass-loader' => '^13.0.2'
            ]
        ];
        
        File::put("{$themePath}/package.json", json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * Create README file
     */
    private function createReadme(string $themePath, string $studlyName, array $metadata): void
    {
        $content = "# {$metadata['name']} Theme

{$metadata['description']}

## Information

- **Version:** {$metadata['version']}
- **Author:** {$metadata['author']}
- **CMS Framework:** Compatible with ArtisanPack UI CMS Framework

## Installation

1. Place this theme in the `app/Themes/{$studlyName}` directory
2. Install dependencies: `npm install`
3. Build assets: `npm run dev`
4. Activate the theme using the CMS admin or command line

## Directory Structure

```
{$studlyName}/
├── config/
│   └── theme.php           # Theme configuration
├── public/
│   ├── css/               # Compiled CSS files
│   ├── js/                # Compiled JS files
│   └── images/            # Theme images
├── resources/
│   └── views/
│       ├── layouts/       # Layout templates
│       ├── components/    # Reusable components
│       └── pages/         # Page templates
├── {$studlyName}.php      # Main theme class
├── ThemeServiceProvider.php  # Service provider
└── README.md
```

## Development

1. Run `npm run watch` to compile assets automatically
2. Edit templates in `resources/views/`
3. Add styles in `public/css/`
4. Add scripts in `public/js/`

## Customization

- Modify `config/theme.php` to change theme settings
- Edit templates in `resources/views/` directory
- Add custom functionality in the main theme class
- Use the theme service provider to register custom services

## Support

For support with this theme, please contact {$metadata['author']}.
";
        
        File::put("{$themePath}/README.md", $content);
    }
    
    /**
     * Create .gitignore file
     */
    private function createGitignore(string $themePath): void
    {
        $content = "node_modules/
npm-debug.log
yarn-error.log
.DS_Store
Thumbs.db
*.log";
        
        File::put("{$themePath}/.gitignore", $content);
    }
}
<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

/**
 * Plugin scaffold command
 * 
 * This command generates the basic structure for new CMS plugins.
 * It creates directories, files, and boilerplate code for plugin development.
 */
class PluginScaffoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:make:plugin 
                           {name : The plugin name}
                           {--author= : Plugin author name}
                           {--description= : Plugin description}
                           {--version=1.0.0 : Plugin version}
                           {--force : Overwrite existing plugin}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new CMS plugin with scaffold structure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $name = $this->argument('name');
            $studlyName = Str::studly($name);
            $kebabName = Str::kebab($name);
            
            $this->info("Creating plugin: {$name}");
            
            // Get plugin metadata
            $metadata = $this->getPluginMetadata($name);
            
            // Define plugin paths
            $pluginPath = app_path("Plugins/{$studlyName}");
            
            // Check if plugin already exists
            if (File::exists($pluginPath) && !$this->option('force')) {
                $this->error("Plugin '{$name}' already exists. Use --force to overwrite.");
                return Command::FAILURE;
            }
            
            // Create plugin structure
            $this->createPluginStructure($pluginPath, $studlyName, $kebabName, $metadata);
            
            $this->info("✅ Plugin '{$name}' created successfully!");
            $this->info("   Path: {$pluginPath}");
            $this->info("   Files created:");
            $this->info("     - Plugin service provider");
            $this->info("     - Plugin configuration");
            $this->info("     - Main plugin class");
            $this->info("     - Controllers directory");
            $this->info("     - Models directory");
            $this->info("     - Views directory");
            $this->info("     - Routes file");
            $this->info("     - Migrations directory");
            $this->info("     - README documentation");
            
            $this->warn("Next steps:");
            $this->line("1. Review the generated files in {$pluginPath}");
            $this->line("2. Customize the plugin configuration");
            $this->line("3. Add your plugin functionality");
            $this->line("4. Run migrations if needed");
            $this->line("5. Activate the plugin: php artisan cms:plugin:activate {$kebabName}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error creating plugin: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Get plugin metadata from options or prompts
     */
    private function getPluginMetadata(string $name): array
    {
        return [
            'name' => $name,
            'author' => $this->option('author') ?: $this->ask('Plugin author', 'Plugin Developer'),
            'description' => $this->option('description') ?: $this->ask('Plugin description', "A custom plugin for the CMS"),
            'version' => $this->option('version'),
        ];
    }
    
    /**
     * Create plugin directory structure and files
     */
    private function createPluginStructure(string $pluginPath, string $studlyName, string $kebabName, array $metadata): void
    {
        // Create directories
        $directories = [
            $pluginPath,
            "{$pluginPath}/src",
            "{$pluginPath}/src/Controllers",
            "{$pluginPath}/src/Models",
            "{$pluginPath}/src/Middleware",
            "{$pluginPath}/resources/views",
            "{$pluginPath}/database/migrations",
            "{$pluginPath}/database/seeders",
            "{$pluginPath}/config",
            "{$pluginPath}/routes",
            "{$pluginPath}/public/css",
            "{$pluginPath}/public/js",
            "{$pluginPath}/tests",
        ];
        
        foreach ($directories as $dir) {
            File::makeDirectory($dir, 0755, true);
        }
        
        // Create plugin files
        $this->createServiceProvider($pluginPath, $studlyName, $metadata);
        $this->createPluginConfig($pluginPath, $kebabName, $metadata);
        $this->createMainPluginClass($pluginPath, $studlyName, $metadata);
        $this->createRoutes($pluginPath);
        $this->createSampleController($pluginPath, $studlyName);
        $this->createSampleModel($pluginPath, $studlyName);
        $this->createSampleMigration($pluginPath, $studlyName);
        $this->createComposerJson($pluginPath, $kebabName, $metadata);
        $this->createReadme($pluginPath, $studlyName, $metadata);
    }
    
    /**
     * Create plugin service provider
     */
    private function createServiceProvider(string $pluginPath, string $studlyName, array $metadata): void
    {
        $content = "<?php

namespace App\\Plugins\\{$studlyName};

use Illuminate\\Support\\ServiceProvider;

/**
 * {$studlyName} Plugin Service Provider
 * 
 * @author {$metadata['author']}
 * @version {$metadata['version']}
 */
class {$studlyName}ServiceProvider extends ServiceProvider
{
    /**
     * Register plugin services
     */
    public function register(): void
    {
        // Merge plugin configuration
        \$this->mergeConfigFrom(__DIR__ . '/config/plugin.php', 'plugins.{$studlyName}');
        
        // Register plugin singleton
        \$this->app->singleton({$studlyName}::class, function (\$app) {
            return new {$studlyName}();
        });
    }
    
    /**
     * Bootstrap plugin services
     */
    public function boot(): void
    {
        // Load plugin views
        \$this->loadViewsFrom(__DIR__ . '/resources/views', '{$studlyName}');
        
        // Load plugin routes
        \$this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        
        // Load plugin migrations
        \$this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        
        // Publish plugin assets
        \$this->publishes([
            __DIR__ . '/public' => public_path('plugins/{$studlyName}'),
        ], 'plugin-assets');
        
        // Publish plugin configuration
        \$this->publishes([
            __DIR__ . '/config/plugin.php' => config_path('plugins/{$studlyName}.php'),
        ], 'plugin-config');
        
        // Initialize plugin
        if (\$this->app->resolved({$studlyName}::class)) {
            \$this->app->make({$studlyName}::class)->init();
        }
    }
}";
        
        File::put("{$pluginPath}/{$studlyName}ServiceProvider.php", $content);
    }
    
    /**
     * Create plugin configuration
     */
    private function createPluginConfig(string $pluginPath, string $kebabName, array $metadata): void
    {
        $content = "<?php

return [
    'name' => '{$metadata['name']}',
    'slug' => '{$kebabName}',
    'description' => '{$metadata['description']}',
    'author' => '{$metadata['author']}',
    'version' => '{$metadata['version']}',
    'requires_cms_version' => '^1.0',
    
    // Plugin settings
    'enabled' => true,
    'auto_activate' => false,
    
    // Plugin capabilities
    'capabilities' => [
        'admin_menu' => true,
        'settings_page' => true,
        'widgets' => false,
        'shortcodes' => false,
    ],
    
    // Admin menu configuration
    'admin_menu' => [
        'title' => '{$metadata['name']}',
        'capability' => 'manage_plugins',
        'icon' => 'dashicons-admin-plugins',
        'position' => 25,
    ],
    
    // Database tables (if any)
    'tables' => [
        // 'plugin_table_name'
    ],
    
    // Hook priorities
    'hooks' => [
        'init' => 10,
        'admin_init' => 10,
    ],
];";
        
        File::put("{$pluginPath}/config/plugin.php", $content);
    }
    
    /**
     * Create main plugin class
     */
    private function createMainPluginClass(string $pluginPath, string $studlyName, array $metadata): void
    {
        $content = "<?php

namespace App\\Plugins\\{$studlyName};

use ArtisanPackUI\\CMSFramework\\Features\\Plugins\\BasePlugin;

/**
 * {$studlyName} Plugin
 * 
 * @author {$metadata['author']}
 * @version {$metadata['version']}
 */
class {$studlyName} extends BasePlugin
{
    /**
     * Plugin version
     */
    protected string \$version = '{$metadata['version']}';
    
    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        // Register plugin hooks
        \$this->registerHooks();
        
        // Register admin functionality
        \$this->registerAdmin();
        
        // Register frontend functionality
        \$this->registerFrontend();
    }
    
    /**
     * Register plugin hooks and filters
     */
    protected function registerHooks(): void
    {
        // Plugin activation hook
        add_action('cms.plugin.activated', [\$this, 'onActivation']);
        
        // Plugin deactivation hook
        add_action('cms.plugin.deactivated', [\$this, 'onDeactivation']);
        
        // Add custom hooks here
        add_action('init', [\$this, 'initPlugin']);
    }
    
    /**
     * Register admin functionality
     */
    protected function registerAdmin(): void
    {
        if (is_admin()) {
            // Register admin menu
            add_action('admin_menu', [\$this, 'addAdminMenu']);
            
            // Register admin scripts and styles
            add_action('admin_enqueue_scripts', [\$this, 'enqueueAdminAssets']);
        }
    }
    
    /**
     * Register frontend functionality
     */
    protected function registerFrontend(): void
    {
        if (!is_admin()) {
            // Register frontend scripts and styles
            add_action('wp_enqueue_scripts', [\$this, 'enqueueFrontendAssets']);
        }
    }
    
    /**
     * Plugin initialization
     */
    public function initPlugin(): void
    {
        // Plugin initialization code
    }
    
    /**
     * Plugin activation callback
     */
    public function onActivation(): void
    {
        // Create database tables
        \$this->createTables();
        
        // Set default options
        \$this->setDefaultOptions();
    }
    
    /**
     * Plugin deactivation callback
     */
    public function onDeactivation(): void
    {
        // Cleanup code (optional)
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu(): void
    {
        add_menu_page(
            '{$metadata['name']}',
            '{$metadata['name']}',
            'manage_options',
            '{$studlyName}',
            [\$this, 'adminPage'],
            'dashicons-admin-plugins',
            25
        );
    }
    
    /**
     * Display admin page
     */
    public function adminPage(): void
    {
        echo view('{$studlyName}::admin.settings');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(): void
    {
        wp_enqueue_style(
            '{$studlyName}-admin',
            asset('plugins/{$studlyName}/css/admin.css'),
            [],
            \$this->version
        );
        
        wp_enqueue_script(
            '{$studlyName}-admin',
            asset('plugins/{$studlyName}/js/admin.js'),
            ['jquery'],
            \$this->version,
            true
        );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        wp_enqueue_style(
            '{$studlyName}-frontend',
            asset('plugins/{$studlyName}/css/frontend.css'),
            [],
            \$this->version
        );
        
        wp_enqueue_script(
            '{$studlyName}-frontend',
            asset('plugins/{$studlyName}/js/frontend.js'),
            ['jquery'],
            \$this->version,
            true
        );
    }
    
    /**
     * Create plugin database tables
     */
    private function createTables(): void
    {
        // Run migrations if needed
    }
    
    /**
     * Set default plugin options
     */
    private function setDefaultOptions(): void
    {
        // Set default configuration values
    }
}";
        
        File::put("{$pluginPath}/{$studlyName}.php", $content);
    }
    
    /**
     * Create routes file
     */
    private function createRoutes(string $pluginPath): void
    {
        $content = "<?php

use Illuminate\\Support\\Facades\\Route;

/*
|--------------------------------------------------------------------------
| Plugin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register plugin routes for your application.
|
*/

// Plugin frontend routes
Route::group(['prefix' => 'plugin', 'middleware' => 'web'], function () {
    // Add your plugin routes here
});

// Plugin admin routes
Route::group(['prefix' => 'admin/plugin', 'middleware' => ['web', 'auth']], function () {
    // Add your plugin admin routes here
});";
        
        File::put("{$pluginPath}/routes/web.php", $content);
    }
    
    /**
     * Create sample controller
     */
    private function createSampleController(string $pluginPath, string $studlyName): void
    {
        $content = "<?php

namespace App\\Plugins\\{$studlyName}\\Controllers;

use Illuminate\\Http\\Request;
use App\\Http\\Controllers\\Controller;

/**
 * {$studlyName} Plugin Controller
 */
class {$studlyName}Controller extends Controller
{
    /**
     * Display plugin dashboard
     */
    public function index()
    {
        return view('{$studlyName}::admin.dashboard');
    }
    
    /**
     * Show plugin settings
     */
    public function settings()
    {
        return view('{$studlyName}::admin.settings');
    }
    
    /**
     * Update plugin settings
     */
    public function updateSettings(Request \$request)
    {
        // Validate and update settings
        \$request->validate([
            // Add validation rules
        ]);
        
        // Update settings logic
        
        return redirect()->back()->with('success', 'Settings updated successfully!');
    }
}";
        
        File::put("{$pluginPath}/src/Controllers/{$studlyName}Controller.php", $content);
    }
    
    /**
     * Create sample model
     */
    private function createSampleModel(string $pluginPath, string $studlyName): void
    {
        $content = "<?php

namespace App\\Plugins\\{$studlyName}\\Models;

use Illuminate\\Database\\Eloquent\\Model;

/**
 * {$studlyName} Plugin Model
 */
class {$studlyName}Model extends Model
{
    /**
     * The table associated with the model.
     */
    protected \$table = '{$studlyName}_data';
    
    /**
     * The attributes that are mass assignable.
     */
    protected \$fillable = [
        'name',
        'value',
        'status',
    ];
    
    /**
     * The attributes that should be cast.
     */
    protected \$casts = [
        'value' => 'json',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}";
        
        File::put("{$pluginPath}/src/Models/{$studlyName}Model.php", $content);
    }
    
    /**
     * Create sample migration
     */
    private function createSampleMigration(string $pluginPath, string $studlyName): void
    {
        $timestamp = date('Y_m_d_His');
        $tableName = Str::snake($studlyName) . '_data';
        
        $content = "<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->json('value')->nullable();
            \$table->boolean('status')->default(true);
            \$table->timestamps();
            
            \$table->index('name');
            \$table->index('status');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};";
        
        File::put("{$pluginPath}/database/migrations/{$timestamp}_create_{$tableName}_table.php", $content);
    }
    
    /**
     * Create composer.json
     */
    private function createComposerJson(string $pluginPath, string $kebabName, array $metadata): void
    {
        $studlyName = Str::studly($kebabName);
        
        $content = [
            'name' => 'cms-plugins/' . $kebabName,
            'description' => $metadata['description'],
            'version' => $metadata['version'],
            'type' => 'cms-plugin',
            'authors' => [
                [
                    'name' => $metadata['author']
                ]
            ],
            'require' => [
                'php' => '^8.2',
                'artisanpack-ui/cms-framework' => '^1.0'
            ],
            'autoload' => [
                'psr-4' => [
                    "App\\Plugins\\{$studlyName}\\" => "src/"
                ]
            ],
            'extra' => [
                'cms-plugin' => [
                    'name' => $metadata['name'],
                    'main' => "{$studlyName}.php"
                ]
            ]
        ];
        
        File::put("{$pluginPath}/composer.json", json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * Create README file
     */
    private function createReadme(string $pluginPath, string $studlyName, array $metadata): void
    {
        $content = "# {$metadata['name']} Plugin

{$metadata['description']}

## Information

- **Version:** {$metadata['version']}
- **Author:** {$metadata['author']}
- **CMS Framework:** Compatible with ArtisanPack UI CMS Framework

## Installation

1. Place this plugin in the `app/Plugins/{$studlyName}` directory
2. Run `composer install` in the plugin directory
3. Run migrations: `php artisan migrate`
4. Activate the plugin: `php artisan cms:plugin:activate`

## Directory Structure

```
{$studlyName}/
├── src/
│   ├── Controllers/       # Plugin controllers
│   ├── Models/           # Plugin models
│   └── Middleware/       # Plugin middleware
├── resources/
│   └── views/           # Plugin views
├── database/
│   ├── migrations/      # Database migrations
│   └── seeders/        # Database seeders
├── config/
│   └── plugin.php      # Plugin configuration
├── routes/
│   └── web.php         # Plugin routes
├── public/
│   ├── css/           # Plugin CSS
│   └── js/            # Plugin JavaScript
├── tests/             # Plugin tests
├── {$studlyName}.php  # Main plugin class
├── {$studlyName}ServiceProvider.php  # Service provider
├── composer.json      # Composer configuration
└── README.md
```

## Usage

Describe how to use your plugin here.

## Configuration

Plugin settings can be configured in `config/plugin.php` or through the admin interface.

## Hooks and Filters

List the hooks and filters your plugin provides.

## Support

For support with this plugin, please contact {$metadata['author']}.
";
        
        File::put("{$pluginPath}/README.md", $content);
    }
}
# Comprehensive CMS Development Guide

**Building Production-Ready Content Management Systems with ArtisanPack UI CMS Framework**

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Core Architecture](#core-architecture)
4. [Step-by-Step CMS Creation](#step-by-step-cms-creation)
5. [Advanced Features](#advanced-features)
6. [Recommended Package Ecosystem](#recommended-package-ecosystem)
7. [Best Practices](#best-practices)
8. [Performance & Security](#performance--security)
9. [Deployment](#deployment)
10. [Troubleshooting](#troubleshooting)

---

## Introduction

The ArtisanPack UI CMS Framework is a modern, modular Laravel package designed for building sophisticated content management systems. This guide provides a complete walkthrough for developers to create production-ready CMS applications from start to finish.

### Why Choose ArtisanPack UI CMS Framework?

- **Modern Architecture**: Built on Laravel 12+ and PHP 8.2+
- **Frontend Agnostic**: Works with any frontend framework (React, Vue, Angular, etc.)
- **Modular Design**: Feature-based architecture with dedicated service providers
- **Enterprise Ready**: Built-in 2FA, audit logging, and security features
- **Developer Experience**: Comprehensive testing, documentation, and tooling
- **Extensible**: Plugin and theme system for customization

### What You'll Build

By following this guide, you'll create a full-featured CMS with:
- Content management with custom post types
- User authentication and role-based access
- Media library with file management
- Admin dashboard with widgets
- RESTful API with Sanctum authentication
- Progressive Web App (PWA) capabilities
- Theme and plugin system

---

## Getting Started

### Prerequisites

Before starting, ensure you have:
- PHP 8.2 or higher
- Composer 2.0+
- Laravel 12.0+
- Node.js 18+ (for frontend assets)
- MySQL 8.0+, PostgreSQL 13+, or SQLite 3.35+

### System Requirements

**Minimum Requirements:**
- Memory: 256MB PHP memory limit
- Extensions: `ext-json`, `ext-mbstring`, `ext-openssl`, `ext-pdo`, `ext-tokenizer`, `ext-xml`

**Recommended:**
- Memory: 512MB+ PHP memory limit
- Redis for caching and sessions
- Elasticsearch for advanced search (optional)

---

## Core Architecture

### Framework Structure

The CMS framework follows a modular architecture with clear separation of concerns:

```
src/
├── CMSManager.php              # Main framework manager
├── AuthServiceProvider.php     # Authentication provider
├── Contracts/                  # Interface definitions
├── Features/                   # Feature-based modules
│   ├── AdminPages/            # Admin interface management
│   ├── DashboardWidgets/      # Dashboard widget system
│   ├── Settings/              # Configuration management
│   ├── ContentTypes/          # Content type system
│   ├── Media/                 # Media library
│   └── PWA/                   # Progressive Web App
└── Traits/                    # Reusable traits
```

### Key Components

1. **Managers**: Handle business logic for each feature
2. **Service Providers**: Register services and boot features
3. **Contracts**: Define interfaces for dependency injection
4. **Models**: Eloquent models for data persistence
5. **Policies**: Authorization rules for access control

---

## Step-by-Step CMS Creation

### Phase 1: Project Setup and Installation

#### 1.1 Create a New Laravel Project

```bash
# Create new Laravel project
laravel new my-cms
cd my-cms

# Or using Composer
composer create-project laravel/laravel my-cms
cd my-cms
```

#### 1.2 Install the CMS Framework

```bash
# Install the CMS framework
composer require artisanpack-ui/cms-framework

# Publish configuration files
php artisan vendor:publish --tag=cms-config

# Run migrations
php artisan migrate

# Create admin user
php artisan cms:user:create --admin
```

#### 1.3 Configure Environment

Update your `.env` file with CMS-specific configuration:

```env
# CMS Framework Configuration
CMS_ENABLED=true
CMS_DEFAULT_ROLE=editor
CMS_ALLOW_REGISTRATION=true

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_cms
DB_USERNAME=root
DB_PASSWORD=

# Media Configuration
CMS_MEDIA_DISK=public
CMS_MEDIA_MAX_SIZE=10240
CMS_MEDIA_ALLOWED_TYPES=jpg,jpeg,png,gif,pdf,doc,docx

# Two-Factor Authentication
CMS_2FA_ENABLED=true
CMS_2FA_ISSUER="My CMS"

# PWA Configuration
CMS_PWA_ENABLED=true
CMS_PWA_NAME="My CMS"
CMS_PWA_SHORT_NAME="CMS"
```

#### 1.4 Install Sanctum for API Authentication

```bash
php artisan sanctum:install
php artisan migrate
```

### Phase 2: Basic CMS Structure

#### 2.1 Create Custom Admin Routes

Create `routes/cms_admin.php`:

```php
<?php

use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesManager;

/*
|--------------------------------------------------------------------------
| CMS Admin Routes
|--------------------------------------------------------------------------
*/

// Main Dashboard
app(AdminPagesManager::class)->registerPage(
    'Dashboard',
    'dashboard',
    'home',
    component: \App\Http\Livewire\Admin\Dashboard\MainDashboard::class
);

// Content Management
app(AdminPagesManager::class)->registerPage(
    'Content',
    'content',
    'document',
    view: 'admin.content.index'
);

app(AdminPagesManager::class)->registerSubPage(
    'content',
    'Posts',
    'posts',
    component: \App\Http\Livewire\Admin\Content\Posts\Index::class
);

app(AdminPagesManager::class)->registerSubPage(
    'content',
    'Pages',
    'pages',
    component: \App\Http\Livewire\Admin\Content\Pages\Index::class
);


// Settings
app(AdminPagesManager::class)->registerPage(
    'Settings',
    'settings',
    'cog',
    component: \App\Http\Livewire\Admin\Settings\GeneralSettings::class
);
```

#### 2.2 Register Admin Routes

Update `app/Providers/RouteServiceProvider.php`:

```php
public function boot(): void
{
    parent::boot();
    
    // Register CMS admin routes
    if (file_exists(base_path('routes/cms_admin.php'))) {
        require base_path('routes/cms_admin.php');
    }
}
```

#### 2.3 Create Content Types

Register custom content types in `app/Providers/AppServiceProvider.php`:

```php
use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;

public function boot(): void
{
    // Register Article content type
    app(ContentTypeManager::class)->register('article', [
        'name' => 'Article',
        'plural' => 'Articles',
        'description' => 'Blog articles and posts',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'tags'],
        'taxonomies' => ['categories', 'tags'],
    ]);

    // Register Page content type
    app(ContentTypeManager::class)->register('page', [
        'name' => 'Page',
        'plural' => 'Pages',
        'description' => 'Static pages',
        'supports' => ['title', 'editor', 'thumbnail', 'template'],
        'hierarchical' => true,
    ]);

    // Register Product content type
    app(ContentTypeManager::class)->register('product', [
        'name' => 'Product',
        'plural' => 'Products',
        'description' => 'E-commerce products',
        'supports' => ['title', 'editor', 'thumbnail', 'gallery', 'custom_fields'],
        'taxonomies' => ['product_categories'],
        'custom_fields' => [
            'price' => 'decimal',
            'sku' => 'string',
            'inventory' => 'integer',
        ],
    ]);
}
```

### Phase 3: Creating Admin Components

#### 3.1 Main Dashboard Component

Create `app/Http/Livewire/Admin/Dashboard/MainDashboard.php`:

```php
<?php

namespace App\Http\Livewire\Admin\Dashboard;

use Livewire\Component;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;

class MainDashboard extends Component
{
    public function render()
    {
        $widgets = app(DashboardWidgetsManager::class)->getWidgets('main_dashboard');
        
        return view('admin.dashboard.main', compact('widgets'))
            ->layout('components.layouts.admin');
    }
}
```

#### 3.2 Dashboard View

Create `resources/views/admin/dashboard/main.blade.php`:

```blade
<div class="space-y-6">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Overview of your content management system.
            </p>
        </div>
    </div>

    {{-- Dashboard Widgets --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($widgets as $widget)
            {!! $widget->render() !!}
        @endforeach
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Quick Actions
            </h3>
            <div class="mt-6 flow-root">
                <div class="flex space-x-4">
                    <a href="/admin/content/posts/create" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                        <x-artisanpack-icon name="plus" class="w-4 h-4 mr-2" />
                        New Post
                    </a>
                    <a href="/admin/content/pages/create"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <x-artisanpack-icon name="document" class="w-4 h-4 mr-2" />
                        New Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
```

#### 3.3 Custom Dashboard Widgets

Create `app/Http/Livewire/Admin/Widgets/ContentStatsWidget.php`:

```php
<?php

namespace App\Http\Livewire\Admin\Widgets;

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;

class ContentStatsWidget extends DashboardWidget
{
    public string $title = 'Content Statistics';
    public string $description = 'Overview of content in your CMS';
    public int $priority = 10;

    public function render(): string
    {
        $stats = [
            'posts' => \App\Models\Post::count(),
            'pages' => \App\Models\Page::count(),
            'published' => \App\Models\Post::where('status', 'published')->count(),
            'drafts' => \App\Models\Post::where('status', 'draft')->count(),
        ];

        return view('admin.widgets.content-stats', compact('stats'))->render();
    }
}
```

### Phase 4: Content Management

#### 4.1 Content Models

Create content models using Artisan commands:

```bash
# Create Post model
php artisan make:model Post -mfc

# Create Page model  
php artisan make:model Page -mfc

# Create Category model
php artisan make:model Category -mfc

# Create Tag model
php artisan make:model Tag -mfc
```

#### 4.2 Post Model Example

Update `app/Models/Post.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ArtisanPackUI\CMSFramework\Traits\HasContent;

class Post extends Model
{
    use HasFactory, HasContent;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'status',
        'published_at',
        'featured_image',
        'meta_title',
        'meta_description',
        'author_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'meta_data' => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }
}
```

---

## Advanced Features

### Theme System

#### Creating Custom Themes

The CMS framework includes a powerful theme system for customizing appearance and functionality.

Create a theme directory:

```bash
mkdir -p resources/themes/my-theme
mkdir -p resources/themes/my-theme/{views,assets,config}
```

#### Theme Configuration

Create `resources/themes/my-theme/theme.json`:

```json
{
    "name": "My Custom Theme",
    "description": "A beautiful theme for my CMS",
    "version": "1.0.0",
    "author": "Your Name",
    "screenshot": "screenshot.png",
    "supports": [
        "custom-header",
        "custom-background",
        "menus",
        "widgets"
    ],
    "template_parts": {
        "header": "partials/header",
        "footer": "partials/footer",
        "sidebar": "partials/sidebar"
    }
}
```

#### Theme Service Provider

Create `app/Providers/ThemeServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Themes\ThemeManager;

class ThemeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $themeManager = app(ThemeManager::class);
        
        // Register theme
        $themeManager->register('my-theme', [
            'name' => 'My Custom Theme',
            'path' => resource_path('themes/my-theme'),
            'views' => resource_path('themes/my-theme/views'),
            'assets' => resource_path('themes/my-theme/assets'),
        ]);
        
        // Set active theme
        $themeManager->activate('my-theme');
    }
}
```

### Plugin System

#### Creating Plugins

Create a plugin structure:

```bash
mkdir -p app/Plugins/ContactForm/{Controllers,Views,Routes}
```

#### Plugin Class

Create `app/Plugins/ContactForm/ContactFormPlugin.php`:

```php
<?php

namespace App\Plugins\ContactForm;

use ArtisanPackUI\CMSFramework\Features\Plugins\Plugin;

class ContactFormPlugin extends Plugin
{
    protected string $name = 'Contact Form';
    protected string $description = 'Simple contact form plugin';
    protected string $version = '1.0.0';
    
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerShortcodes();
    }
    
    protected function registerRoutes(): void
    {
        require __DIR__ . '/Routes/web.php';
    }
    
    protected function registerViews(): void
    {
        $this->app['view']->addNamespace('contact-form', __DIR__ . '/Views');
    }
    
    protected function registerShortcodes(): void
    {
        add_shortcode('contact_form', [$this, 'renderContactForm']);
    }
    
    public function renderContactForm($attributes = []): string
    {
        return view('contact-form::form', compact('attributes'))->render();
    }
}
```

### API Development

#### API Controllers

Create API controllers for your content:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Resources\PostResource;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::published()
            ->with(['author', 'categories', 'tags'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
            })
            ->when($request->category, function ($query, $category) {
                $query->whereHas('categories', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            })
            ->orderBy('published_at', 'desc')
            ->paginate(10);

        return PostResource::collection($posts);
    }

    public function show(string $slug)
    {
        $post = Post::published()
            ->with(['author', 'categories', 'tags'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new PostResource($post);
    }
}
```

#### API Resources

Create `app/Http/Resources/PostResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),
            'featured_image' => $this->featured_image,
            'meta' => [
                'title' => $this->meta_title,
                'description' => $this->meta_description,
            ],
            'author' => new UserResource($this->whenLoaded('author')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

#### API Routes

Create `routes/api.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{PostController, PageController, CategoryController};

// Public API routes
Route::prefix('v1')->group(function () {
    // Posts
    Route::get('posts', [PostController::class, 'index']);
    Route::get('posts/{slug}', [PostController::class, 'show']);
    
    // Pages
    Route::get('pages', [PageController::class, 'index']);
    Route::get('pages/{slug}', [PageController::class, 'show']);
    
    // Categories
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}/posts', [CategoryController::class, 'posts']);
});

// Protected API routes (require Sanctum authentication)
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Content management
    Route::apiResource('posts', PostController::class)->except(['index', 'show']);
    Route::apiResource('pages', PageController::class)->except(['index', 'show']);
    Route::apiResource('categories', CategoryController::class);
    
    // User management
    Route::get('user', function (Request $request) {
        return $request->user();
    });
});
```

### Progressive Web App (PWA) Integration

#### Enable PWA Features

The CMS framework includes built-in PWA support. Configure in your `.env`:

```env
CMS_PWA_ENABLED=true
CMS_PWA_NAME="My CMS"
CMS_PWA_SHORT_NAME="CMS"
CMS_PWA_THEME_COLOR="#4F46E5"
CMS_PWA_BACKGROUND_COLOR="#FFFFFF"
```

#### PWA Manifest

The framework automatically generates a `manifest.json` file, but you can customize it:

```php
// In a service provider
use ArtisanPackUI\CMSFramework\Features\PWA\PWAManager;

app(PWAManager::class)->configure([
    'name' => config('app.name'),
    'short_name' => config('cms.pwa.short_name'),
    'description' => 'A powerful content management system',
    'icons' => [
        [
            'src' => '/images/icon-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
        ],
        [
            'src' => '/images/icon-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
        ],
    ],
    'start_url' => '/',
    'display' => 'standalone',
    'theme_color' => '#4F46E5',
    'background_color' => '#FFFFFF',
]);
```

#### Service Worker

Create `public/sw.js` for offline functionality:

```javascript
const CACHE_NAME = 'cms-v1';
const urlsToCache = [
    '/',
    '/css/app.css',
    '/js/app.js',
    '/images/logo.png',
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                return cache.addAll(urlsToCache);
            })
    );
});

self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                if (response) {
                    return response;
                }
                return fetch(event.request);
            }
        )
    );
});
```

### Two-Factor Authentication

#### Enable 2FA

The framework includes built-in 2FA support:

```php
// In your User model
use ArtisanPackUI\CMSFramework\Traits\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use TwoFactorAuthenticatable;
    
    // ... rest of your model
}
```

#### 2FA Setup Component

Create a Livewire component for 2FA setup:

```php
<?php

namespace App\Http\Livewire\Profile;

use Livewire\Component;
use Illuminate\Support\Collection;

class TwoFactorAuthenticationForm extends Component
{
    public bool $showingQrCode = false;
    public bool $showingRecoveryCodes = false;
    public Collection $recoveryCodes;

    public function enableTwoFactorAuthentication()
    {
        $this->user->enableTwoFactorAuthentication();
        $this->showingQrCode = true;
        $this->showingRecoveryCodes = true;
        $this->recoveryCodes = collect($this->user->recoveryCodes());
    }

    public function disableTwoFactorAuthentication()
    {
        $this->user->disableTwoFactorAuthentication();
        $this->reset();
    }

    public function regenerateRecoveryCodes()
    {
        $this->user->regenerateRecoveryCodes();
        $this->recoveryCodes = collect($this->user->recoveryCodes());
        $this->showingRecoveryCodes = true;
    }

    public function getUserProperty()
    {
        return auth()->user();
    }

    public function render()
    {
        return view('profile.two-factor-authentication-form');
    }
}
```

---

## Recommended Package Ecosystem

### ArtisanPack UI Packages

The ArtisanPack UI ecosystem provides a comprehensive suite of packages designed to work seamlessly together:

#### 1. **artisanpack-ui/livewire-ui-components**

Essential UI components for building modern interfaces with Livewire.

```bash
composer require artisanpack-ui/livewire-ui-components
```

**Key Features:**
- Pre-built Livewire components
- Form components with validation
- Data tables and pagination
- Modal dialogs and notifications
- Navigation components

**Usage Example:**
```blade
<x-artisanpack-form wire:submit="save">
    <x-artisanpack-input 
        label="Title" 
        wire:model="title" 
        required 
    />
    
    <x-artisanpack-textarea 
        label="Content" 
        wire:model="content"
        rows="10" 
    />
    
    <x-artisanpack-button type="submit" primary>
        Save Post
    </x-artisanpack-button>
</x-artisanpack-form>
```


#### 2. **artisanpack-ui/icons**

Comprehensive icon library with easy integration.

```bash
composer require artisanpack-ui/icons
```

**Usage:**
```blade
<x-artisanpack-icon name="home" class="w-5 h-5" />
<x-artisanpack-icon name="user" size="lg" />
<x-artisanpack-icon name="settings" variant="outline" />
```

#### 3. **artisanpack-ui/security**

Enhanced security features for production applications.

```bash
composer require artisanpack-ui/security
```

**Key Features:**
- Advanced rate limiting
- IP whitelisting/blacklisting
- Security headers management
- Vulnerability scanning
- Automated security monitoring

#### 4. **artisanpack-ui/accessibility**

Ensure your CMS meets accessibility standards.

```bash
composer require artisanpack-ui/accessibility
```

**Features:**
- Accessibility testing tools
- ARIA attribute helpers
- Color contrast validation
- Screen reader optimization
- Keyboard navigation support

### Essential Laravel Ecosystem Packages

#### Content & Media Management

**1. Spatie Image**
```bash
composer require spatie/image
```
Advanced image manipulation and optimization.

**2. Laravel Translatable**
```bash
composer require spatie/laravel-translatable
```
Multi-language content support.

**3. Laravel Sluggable**
```bash
composer require cviebrock/eloquent-sluggable
```
Automatic slug generation for content.

#### Performance & Caching

**1. Laravel Redis**
```bash
composer require predis/predis
```
Redis integration for caching and sessions.

**2. Laravel Horizon**
```bash
composer require laravel/horizon
```
Queue monitoring and management.

**3. Laravel Telescope**
```bash
composer require laravel/telescope --dev
```
Debug and monitoring toolbar.

#### Search & Filtering

**1. Laravel Scout**
```bash
composer require laravel/scout
```
Full-text search integration.

**2. TNTSearch**
```bash
composer require teamtnt/laravel-scout-tntsearch-driver
```
Lightweight search engine for Scout.

**3. Algolia Scout**
```bash
composer require algolia/scout-extended
```
Advanced search with Algolia.

#### API Development

**1. Laravel Passport** (Alternative to Sanctum)
```bash
composer require laravel/passport
```
OAuth2 server implementation.

**2. Spatie Query Builder**
```bash
composer require spatie/laravel-query-builder
```
API query filtering and sorting.

**3. Laravel Fractal**
```bash
composer require spatie/fractal
```
API resource transformation.

#### SEO & Analytics

**1. Laravel Sitemap**
```bash
composer require spatie/laravel-sitemap
```
Automatic sitemap generation.

**2. Laravel Analytics**
```bash
composer require spatie/laravel-analytics
```
Google Analytics integration.

**3. Laravel Meta**
```bash
composer require artesaos/seotools
```
SEO meta tags management.

### Recommended Development Stack

For a complete CMS development environment:

**Backend Stack:**
- Laravel 12+
- ArtisanPack UI CMS Framework
- MySQL/PostgreSQL
- Redis
- Laravel Sanctum/Passport

**Frontend Stack:**
- Livewire + Alpine.js (Traditional)
- OR Inertia.js + Vue.js/React
- Tailwind CSS
- ArtisanPack UI Components

**Development Tools:**
- Laravel Sail (Docker)
- Laravel Telescope
- Laravel Debugbar
- Pest Testing Framework

**Production Essentials:**
- Laravel Horizon (Queue Management)
- Laravel Scout (Search)
- Spatie Media Library
- Security & Accessibility packages

---

## Best Practices

### Code Organization

#### Follow Laravel Conventions

Adhere to Laravel's coding standards and conventions:

```php
// ✅ Good: Follow PSR standards and Laravel conventions
class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->published()
            ->with(['author', 'categories'])
            ->paginate(15);
            
        return response()->json($posts);
    }
}

// ❌ Avoid: Poor naming and structure
class post_controller extends Controller 
{
    function get_posts() {
        return Post::all();
    }
}
```

#### Use Type Declarations

Always use type declarations for better code clarity and IDE support:

```php
// ✅ Good: Explicit type declarations
public function createPost(string $title, array $data): Post
{
    return Post::create([
        'title' => $title,
        'content' => $data['content'] ?? '',
        'status' => $data['status'] ?? 'draft',
    ]);
}

// ❌ Avoid: Missing type declarations
public function createPost($title, $data)
{
    return Post::create(['title' => $title]);
}
```

#### Leverage Service Classes

Extract complex logic into dedicated service classes:

```php
// ✅ Good: Dedicated service class
class PostService
{
    public function publish(Post $post): bool
    {
        if (!$this->canPublish($post)) {
            return false;
        }
        
        $post->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
        
        event(new PostPublished($post));
        
        return true;
    }
    
    private function canPublish(Post $post): bool
    {
        return $post->title && 
               $post->content && 
               auth()->user()->can('publish', $post);
    }
}
```

### Security Best Practices

#### Input Validation

Always validate and sanitize user input:

```php
// Form Request Validation
class CreatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status' => ['required', 'in:draft,published'],
            'categories' => ['array', 'exists:categories,id'],
            'featured_image' => ['nullable', 'image', 'max:2048'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.required' => 'Post title is required',
            'content.required' => 'Post content cannot be empty',
        ];
    }
}
```

#### Authorization Policies

Implement comprehensive authorization policies:

```php
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_posts');
    }
    
    public function view(User $user, Post $post): bool
    {
        return $user->hasPermission('view_posts') || 
               $post->author_id === $user->id;
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermission('create_posts');
    }
    
    public function update(User $user, Post $post): bool
    {
        return $user->hasPermission('edit_posts') || 
               ($post->author_id === $user->id && $user->hasPermission('edit_own_posts'));
    }
    
    public function delete(User $user, Post $post): bool
    {
        return $user->hasPermission('delete_posts') || 
               ($post->author_id === $user->id && $user->hasPermission('delete_own_posts'));
    }
}
```

#### Rate Limiting

Implement rate limiting for API endpoints:

```php
// In RouteServiceProvider or API routes
Route::middleware(['throttle:api'])->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Custom rate limiting
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('contact', [ContactController::class, 'store']);
});
```

### Performance Optimization

#### Database Query Optimization

Optimize database queries to prevent N+1 problems:

```php
// ✅ Good: Eager loading relationships
public function index()
{
    $posts = Post::with(['author', 'categories', 'tags'])
        ->published()
        ->latest('published_at')
        ->paginate(10);
        
    return view('posts.index', compact('posts'));
}

// ❌ Avoid: N+1 query problem
public function index()
{
    $posts = Post::published()->paginate(10);
    // This will cause N+1 queries when accessing $post->author in the view
    return view('posts.index', compact('posts'));
}
```

#### Implement Caching

Use caching strategically for frequently accessed data:

```php
class PostService
{
    public function getPopularPosts(int $limit = 10): Collection
    {
        return Cache::remember('popular_posts', 3600, function () use ($limit) {
            return Post::published()
                ->withCount('views')
                ->orderBy('views_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }
    
    public function getFeaturedPosts(): Collection
    {
        return Cache::tags(['posts', 'featured'])
            ->remember('featured_posts', 1800, function () {
                return Post::featured()->published()->get();
            });
    }
    
    public function clearPostCache(): void
    {
        Cache::tags(['posts'])->flush();
    }
}
```

#### Use Database Indexing

Create appropriate database indexes:

```php
// In migration files
Schema::table('posts', function (Blueprint $table) {
    $table->index(['status', 'published_at']); // For published posts queries
    $table->index(['author_id', 'created_at']); // For author's posts
    $table->index('slug'); // For slug lookups
    $table->fullText(['title', 'content']); // For search functionality
});
```

### Testing Strategies

#### Feature Tests

Write comprehensive feature tests:

```php
class PostManagementTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_authenticated_user_can_create_post(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->post('/api/posts', [
                'title' => 'Test Post',
                'content' => 'This is test content',
                'status' => 'draft',
            ]);
            
        $response->assertSuccessful();
        
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'author_id' => $user->id,
            'status' => 'draft',
        ]);
    }
    
    public function test_guest_cannot_create_post(): void
    {
        $response = $this->post('/api/posts', [
            'title' => 'Test Post',
            'content' => 'This is test content',
        ]);
        
        $response->assertUnauthorized();
    }
}
```

#### Unit Tests

Test individual components in isolation:

```php
class PostServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_can_publish_valid_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $user->id,
            'status' => 'draft',
        ]);
        
        $service = new PostService();
        $result = $service->publish($post);
        
        $this->assertTrue($result);
        $this->assertEquals('published', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
    }
}
```

#### Livewire Component Tests

Test Livewire components:

```php
class CreatePostComponentTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_can_create_post_via_livewire(): void
    {
        $user = User::factory()->create();
        
        Livewire::actingAs($user)
            ->test(CreatePost::class)
            ->set('title', 'New Post Title')
            ->set('content', 'Post content here')
            ->call('save')
            ->assertSuccessful()
            ->assertRedirect('/admin/posts');
            
        $this->assertDatabaseHas('posts', [
            'title' => 'New Post Title',
            'author_id' => $user->id,
        ]);
    }
}
```

### Content Management Best Practices

#### Content Versioning

Implement content versioning for important content:

```php
class Post extends Model
{
    use HasVersions;
    
    protected $versionable = [
        'title', 'content', 'excerpt', 'meta_title', 'meta_description'
    ];
    
    public function createVersion(): void
    {
        $this->versions()->create([
            'data' => $this->only($this->versionable),
            'created_by' => auth()->id(),
        ]);
    }
}
```

#### SEO Optimization

Implement SEO best practices:

```php
class SEOService
{
    public function generateMetaTags(Post $post): array
    {
        return [
            'title' => $post->meta_title ?: $post->title,
            'description' => $post->meta_description ?: Str::limit(strip_tags($post->content), 160),
            'keywords' => $post->tags->pluck('name')->implode(', '),
            'og:title' => $post->title,
            'og:description' => $post->excerpt,
            'og:image' => $post->featured_image_url,
            'og:url' => url("/posts/{$post->slug}"),
        ];
    }
}
```

#### Content Validation

Validate content integrity:

```php
class ContentValidator
{
    public function validate(Post $post): array
    {
        $issues = [];
        
        if (empty($post->meta_description)) {
            $issues[] = 'Missing meta description';
        }
        
        if (empty($post->featured_image)) {
            $issues[] = 'Missing featured image';
        }
        
        if (str_word_count($post->content) < 300) {
            $issues[] = 'Content too short (minimum 300 words)';
        }
        
        return $issues;
    }
}
```

---

## Performance & Security

### Performance Optimization

#### Database Performance

**Query Optimization:**
```php
// Use database indexes for frequent queries
Schema::table('posts', function (Blueprint $table) {
    $table->index(['status', 'published_at']);
    $table->index(['author_id', 'status']);
    $table->index('slug');
    $table->fullText(['title', 'content']);
});

// Use pagination for large datasets
public function index(Request $request)
{
    return Post::published()
        ->with(['author', 'categories'])
        ->orderBy('published_at', 'desc')
        ->simplePaginate(15); // More efficient than paginate()
}

// Use database aggregation instead of PHP calculations
$stats = [
    'total_posts' => Post::count(),
    'published_posts' => Post::where('status', 'published')->count(),
    'draft_posts' => Post::where('status', 'draft')->count(),
    'posts_this_month' => Post::whereMonth('created_at', now()->month)->count(),
];
```

**Connection Pooling and Read Replicas:**
```php
// config/database.php
'connections' => [
    'mysql' => [
        'read' => [
            'host' => ['192.168.1.1', '192.168.1.2'],
        ],
        'write' => [
            'host' => ['192.168.1.3'],
        ],
        'sticky' => true,
        // ... other config
    ],
],
```

#### Caching Strategies

**Multi-Level Caching:**
```php
class PostService
{
    public function getPost(string $slug): ?Post
    {
        // Level 1: Application cache
        return Cache::remember("post:{$slug}", 3600, function () use ($slug) {
            return Post::with(['author', 'categories', 'tags'])
                ->where('slug', $slug)
                ->published()
                ->first();
        });
    }
    
    public function getPostsForCategory(string $categorySlug, int $page = 1): LengthAwarePaginator
    {
        $cacheKey = "category:{$categorySlug}:page:{$page}";
        
        return Cache::tags(['posts', 'categories'])
            ->remember($cacheKey, 1800, function () use ($categorySlug, $page) {
                return Post::whereHas('categories', function ($query) use ($categorySlug) {
                    $query->where('slug', $categorySlug);
                })
                ->published()
                ->with(['author', 'categories'])
                ->paginate(10, ['*'], 'page', $page);
            });
    }
    
    public function invalidatePostCache(Post $post): void
    {
        Cache::forget("post:{$post->slug}");
        Cache::tags(['posts'])->flush();
        
        // Clear category-specific caches
        foreach ($post->categories as $category) {
            Cache::tags(['categories'])->flush();
        }
    }
}
```

**Redis Configuration:**
```php
// config/cache.php
'redis' => [
    'client' => 'phpredis',
    'options' => [
        'cluster' => 'redis',
        'prefix' => env('REDIS_PREFIX', 'cms_'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
        'persistent' => true,
    ],
];
```

#### Asset Optimization

**Frontend Build Optimization:**
```javascript
// vite.config.js
export default defineConfig({
    plugins: [laravel({
        input: ['resources/css/app.css', 'resources/js/app.js'],
        refresh: true,
    })],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['lodash', 'axios'],
                    admin: ['admin.js'],
                }
            }
        },
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            }
        }
    },
});
```

**Image Optimization:**
```php
class MediaService
{
    public function processImage(UploadedFile $file): Media
    {
        $media = Media::create([
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        
        // Create multiple sizes
        $this->createImageVariants($file, $media);
        
        return $media;
    }
    
    private function createImageVariants(UploadedFile $file, Media $media): void
    {
        $sizes = [
            'thumb' => [150, 150],
            'medium' => [300, 300],
            'large' => [800, 600],
        ];
        
        foreach ($sizes as $name => $dimensions) {
            Image::make($file)
                ->fit($dimensions[0], $dimensions[1])
                ->encode('webp', 85)
                ->save(storage_path("app/media/{$media->id}_{$name}.webp"));
        }
    }
}
```

### Security Hardening

#### Authentication Security

**Rate Limiting:**
```php
// config/sanctum.php
'expiration' => 525600, // 1 year in minutes

// Custom rate limiting
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $this->validateLogin($request);
        
        // Rate limit failed login attempts
        if (RateLimiter::tooManyAttempts(
            $this->throttleKey($request), 5
        )) {
            return response()->json([
                'message' => 'Too many login attempts. Please try again later.'
            ], 429);
        }
        
        if ($this->attemptLogin($request)) {
            RateLimiter::clear($this->throttleKey($request));
            return $this->sendLoginResponse($request);
        }
        
        RateLimiter::hit($this->throttleKey($request), 3600);
        return $this->sendFailedLoginResponse($request);
    }
    
    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }
}
```

**Two-Factor Authentication:**
```php
class TwoFactorService
{
    public function enable(User $user): array
    {
        $user->two_factor_secret = encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey());
        $user->save();
        
        return [
            'qr_code' => app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
                config('app.name'),
                $user->email,
                decrypt($user->two_factor_secret)
            ),
            'recovery_codes' => $this->generateRecoveryCodes($user),
        ];
    }
    
    public function verify(User $user, string $code): bool
    {
        return app(TwoFactorAuthenticationProvider::class)->verify(
            decrypt($user->two_factor_secret),
            $code
        );
    }
}
```

#### Input Sanitization

**XSS Prevention:**
```php
class ContentSanitizer
{
    private array $allowedTags = [
        'p', 'br', 'strong', 'em', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'img', 'blockquote', 'code', 'pre'
    ];
    
    private array $allowedAttributes = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
        'blockquote' => ['cite'],
    ];
    
    public function sanitize(string $content): string
    {
        return strip_tags(
            $content,
            '<' . implode('><', $this->allowedTags) . '>'
        );
    }
    
    public function sanitizeForAdmin(string $content): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', $this->buildAllowedString());
        
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($content);
    }
}
```

#### CSRF and CORS Protection

**CSRF Configuration:**
```php
// config/sanctum.php
'middleware' => [
    'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
],

// In forms
<form method="POST" action="/admin/posts">
    @csrf
    <!-- form fields -->
</form>
```

**CORS Configuration:**
```php
// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => [
    'https://yourdomain.com',
    'https://admin.yourdomain.com',
],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

#### File Upload Security

**Secure File Upload:**
```php
class SecureFileUpload
{
    private array $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'text/plain',
    ];
    
    private int $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    public function validate(UploadedFile $file): array
    {
        $errors = [];
        
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            $errors[] = 'File size exceeds maximum allowed size';
        }
        
        // Check MIME type
        if (!in_array($file->getMimeType(), $this->allowedMimes)) {
            $errors[] = 'File type not allowed';
        }
        
        // Check for malicious content
        if ($this->containsMaliciousContent($file)) {
            $errors[] = 'File contains malicious content';
        }
        
        return $errors;
    }
    
    private function containsMaliciousContent(UploadedFile $file): bool
    {
        $content = file_get_contents($file->getRealPath());
        
        // Check for PHP tags in uploaded files
        if (strpos($content, '<?php') !== false) {
            return true;
        }
        
        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
}
```

### Monitoring and Logging

#### Application Performance Monitoring

**Custom Metrics Collection:**
```php
class PerformanceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $response = $next($request);
        
        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage() - $startMemory;
        
        // Log slow queries
        if ($executionTime > 0.5) {
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed,
                'user_id' => auth()->id(),
            ]);
        }
        
        // Store metrics
        Cache::increment('requests_total');
        Cache::increment('requests_by_route:' . $request->route()->getName());
        
        return $response;
    }
}
```

**Health Check Endpoint:**
```php
class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];
        
        $healthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');
        
        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }
    
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
```

#### Security Monitoring

**Audit Logging:**
```php
class AuditLogger
{
    public function logUserActivity(string $action, Model $model = null, array $changes = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
    
    public function logSecurityEvent(string $event, array $context = []): void
    {
        Log::channel('security')->warning($event, array_merge($context, [
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]));
    }
}
```

---

## Deployment

### Production Server Setup

#### Server Requirements

**Minimum Server Specifications:**
- CPU: 2 cores (4 cores recommended)
- RAM: 2GB (4GB+ recommended)
- Storage: 20GB SSD
- PHP: 8.2+ with required extensions
- Web Server: Nginx or Apache
- Database: MySQL 8.0+ or PostgreSQL 13+
- Redis: 6.0+ for caching and sessions

#### Server Configuration

**Nginx Configuration:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/html/public;

    # SSL Configuration
    ssl_certificate /etc/ssl/certs/yourdomain.com.pem;
    ssl_certificate_key /etc/ssl/private/yourdomain.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Basic Configuration
    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Handle Laravel Routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Security: Block access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Static Asset Caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

**Docker Deployment:**
```dockerfile
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    redis \
    mysql-client \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        zip \
        gd \
        intl \
        mbstring \
        bcmath \
        opcache \
        pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

EXPOSE 80

CMD ["php-fpm"]
```

---

## Troubleshooting

### Common Installation Issues

#### Database Connection Problems

**Error:** `SQLSTATE[HY000] [2002] Connection refused`

**Solutions:**
```bash
# Check database service status
sudo systemctl status mysql
sudo systemctl start mysql

# Verify database credentials
mysql -u username -p database_name

# Check Laravel database configuration
php artisan config:clear
php artisan config:cache
```

**Error:** `Access denied for user`

**Solutions:**
```sql
-- Create database user with proper permissions
CREATE USER 'cms_user'@'localhost' IDENTIFIED BY 'strong_password';
CREATE DATABASE cms_database;
GRANT ALL PRIVILEGES ON cms_database.* TO 'cms_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Permission Issues

**Error:** `The stream or file could not be opened in append mode`

**Solutions:**
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data bootstrap/cache/
sudo chmod -R 775 storage/
sudo chmod -R 775 bootstrap/cache/

# Create storage symlink
php artisan storage:link

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

#### Memory Limit Issues

**Error:** `Fatal error: Allowed memory size exhausted`

**Solutions:**
```ini
; In php.ini
memory_limit = 512M
max_execution_time = 300
max_input_vars = 3000

; For specific operations
php -d memory_limit=512M artisan migrate
```

### Performance Issues

#### Slow Page Load Times

**Diagnostic Steps:**
```php
// Enable query logging temporarily
DB::enableQueryLog();
// Your code here
dd(DB::getQueryLog());

// Check for N+1 queries
php artisan telescope:install
```

**Common Solutions:**
```php
// Use eager loading
$posts = Post::with(['author', 'categories', 'tags'])->get();

// Implement caching
Cache::remember('popular_posts', 3600, function () {
    return Post::popular()->limit(10)->get();
});

// Use pagination
$posts = Post::paginate(15);
```

#### High Memory Usage

**Solutions:**
```php
// Use chunking for large datasets
Post::chunk(1000, function ($posts) {
    foreach ($posts as $post) {
        // Process post
    }
});

// Use cursors for memory-efficient iteration
foreach (Post::cursor() as $post) {
    // Process post
}

// Clear Eloquent model cache
Model::clearBootedModels();
```

### Security Issues

#### CSRF Token Mismatch

**Solutions:**
```blade
{{-- Ensure CSRF token is included --}}
<form method="POST">
    @csrf
    {{-- form fields --}}
</form>

{{-- For AJAX requests --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
</script>
```

#### Session Issues

**Solutions:**
```php
// config/session.php
'same_site' => 'lax',
'secure' => env('SESSION_SECURE_COOKIE', true),
'http_only' => true,

// Clear sessions
php artisan session:table
php artisan migrate
```

### API Issues

#### Authentication Problems

**Solutions:**
```php
// Ensure Sanctum is properly configured
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

// Check API routes
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
```

#### CORS Issues

**Solutions:**
```php
// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => ['http://localhost:3000'],
'supports_credentials' => true,
```

### Development Tools

#### Debugging Commands

```bash
# Check application status
php artisan cms:status

# Clear all caches
php artisan optimize:clear

# Run diagnostics
php artisan config:show database
php artisan route:list
php artisan view:cache

# Check logs
tail -f storage/logs/laravel.log

# Run tests with coverage
php artisan test --coverage
```

#### Performance Profiling

```php
// Add to AppServiceProvider for development
if (app()->environment('local')) {
    app(Illuminate\Contracts\Http\Kernel::class)
        ->pushMiddleware(Barryvdh\Debugbar\Middleware\DebugbarEnabled::class);
}
```

---

## Conclusion

This comprehensive guide has walked you through creating a production-ready content management system using the ArtisanPack UI CMS Framework. You've learned:

- **Core Architecture**: Understanding the modular framework structure
- **Step-by-Step Implementation**: Building a complete CMS from scratch
- **Advanced Features**: Implementing themes, plugins, APIs, and PWA capabilities
- **Best Practices**: Following Laravel conventions and security guidelines
- **Performance Optimization**: Scaling your CMS for production use
- **Deployment Strategies**: Getting your CMS live with confidence

The ArtisanPack UI CMS Framework provides a solid foundation for building sophisticated content management systems that can grow with your needs. By following the patterns and practices outlined in this guide, you'll be able to create maintainable, secure, and performant CMS applications.

For additional support and community discussions, visit:
- [GitHub Repository](https://github.com/artisanpack-ui/cms-framework)
- [Documentation](https://cms-framework.artisanpack-ui.com)
- [Community Discussions](https://github.com/artisanpack-ui/cms-framework/discussions)

Happy coding! 🚀

---
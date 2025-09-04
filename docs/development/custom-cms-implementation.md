---
title: Custom CMS Implementation
---

# Custom CMS Implementation

This document explains how to implement admin pages and dashboard widgets in a custom CMS application that uses the ArtisanPackUI\CMSFramework package.

## Overview

The ArtisanPackUI\CMSFramework package provides the AdminPagesManager and the necessary underlying routing logic. However, your custom CMS application is responsible for defining which pages exist and what Livewire components or Blade views render them.

This guide explains the relationship between the framework and your application, and provides step-by-step instructions for implementing admin pages and dashboard widgets in your custom CMS.

## Framework vs. Application Responsibilities

### Framework Responsibilities

The ArtisanPackUI\CMSFramework package provides:

- The `AdminPagesManager` class for registering and routing admin pages
- The `DashboardWidget` abstract class for creating widget types
- The `DashboardWidgetsManager` class for managing widget instances
- Internal routing logic through the framework's `admin.php` routes file

### Application Responsibilities

Your custom CMS application is responsible for:

- Creating a custom admin routes file to register specific admin pages
- Registering this routes file with Laravel
- Creating Livewire components or Blade views for admin pages
- Creating concrete implementations of dashboard widgets
- Registering widget types with the DashboardWidgetsManager

## Implementing Admin Pages and Menus

### 1. Understanding the Framework's Internal admin.php

The ArtisanPackUI/CMSFramework/Features/AdminPages/routes/admin.php file (within the package itself) is minimal. Its primary purpose is to ensure the AdminPagesManager::registerRoutes() method is called during the framework's boot process.

**Important**: You should NOT edit this file. It's part of the framework.

### 2. Creating Your Application's Custom Admin Routes File

Create a new route file within your application's routes/ directory:

```php
// routes/cms_admin.php
<?php

use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesManager;

/*
|--------------------------------------------------------------------------
| CMS Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin pages and menu items for your
| CMS application. These are loaded by your App\Providers\RouteServiceProvider.
|
*/

// Registering the main dashboard page
app(AdminPagesManager::class)->registerPage(
    'Dashboard',
    'dashboard', // This is the URL slug (e.g., /admin/dashboard)
    'home', // Example icon name (e.g., from ArtisanPack UI Icons package)
    component: \App\Http\Livewire\Admin\Dashboard\MainDashboard::class // Your application's Livewire component
);

// Registering a 'Content' section with subpages
app(AdminPagesManager::class)->registerPage(
    'Content',
    'content',
    'document',
    view: 'ap-cms-admin::content.index' // Your application's Blade view
);

app(AdminPagesManager::class)->registerSubPage(
    'content', // Parent slug
    'Posts',
    'posts', // Subpage slug (e.g., /admin/content/posts)
    component: \App\Http\Livewire\Admin\Content\Posts\Index::class
);

app(AdminPagesManager::class)->registerSubPage(
    'content',
    'Pages',
    'pages',
    component: \App\Http\Livewire\Admin\Content\Pages\Index::class
);

// A dedicated 'Marketing' dashboard page
app(AdminPagesManager::class)->registerPage(
    'Marketing Hub',
    'marketing', // Distinct dashboard slug
    'chart-pie',
    component: \App\Http\Livewire\Admin\Dashboard\MarketingDashboard::class
);

// Add any other admin pages specific to your CMS here...
```

### 3. Registering Your Custom Admin Routes File

Open `app/Providers/RouteServiceProvider.php` and add a require statement within its boot() method:

```php
// app/Providers/RouteServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // --- ADD THIS BLOCK ---
            // Load your custom CMS admin routes
            require base_path('routes/cms_admin.php');
            // --- END ADDITION ---
        });
    }
}
```

Alternatively, if you prefer to map a specific group for your admin routes (e.g., protected by an 'auth' middleware for admin users), you can do it like this:

```php
// Option 2: More structured route mapping for admin routes in RouteServiceProvider
public function boot(): void
{
    // ... rate limiter, etc.

    $this->routes(function () {
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api.php'));

        // Default web routes
        Route::middleware('web')
            ->group(base_path('routes/web.php'));

        // --- ADD THIS BLOCK FOR CMS ADMIN ROUTES ---
        Route::middleware(['web', 'auth']) // Apply web and auth middleware to your admin routes
            ->prefix(config('cms.admin_path', 'admin')) // Use your admin base path from config
            ->name('admin.') // Prefix route names with 'admin.'
            ->group(base_path('routes/cms_admin.php'));
        // --- END ADDITION ---
    });
}
```

If you choose the second option, ensure your AdminPagesManager routes are registered without the prefix and name in its registerRoutes() method, as they will be applied by RouteServiceProvider.

### 4. Creating Your Application's Admin Components and Views

For every component or view you registered in routes/cms_admin.php, you need to create the corresponding file within your application's app/Http/Livewire/ or resources/views/ directories.

Example Livewire component:

```php
// app/Http/Livewire/Admin/Dashboard/MainDashboard.php
<?php

namespace App\Http\Livewire\Admin\Dashboard;

use Livewire\Component;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;

class MainDashboard extends Component
{
    public function render()
    {
        // Get all widgets for the main dashboard
        $widgetsManager = app(DashboardWidgetsManager::class);
        $widgets = $widgetsManager->getDashboardWidgetInstances('main');
        
        return view('livewire.admin.dashboard.main-dashboard', [
            'widgets' => $widgets,
            'widgetsManager' => $widgetsManager,
        ]);
    }
}
```

Example Blade view for the Livewire component:

```blade
{{-- resources/views/livewire/admin/dashboard/main-dashboard.blade.php --}}
<div>
    <h1>Dashboard</h1>
    
    <div class="dashboard-widgets grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($widgets as $widget)
            <div class="widget-container p-4 bg-white rounded shadow">
                {!! $widgetsManager->renderWidgetInstance($widget['id'], 'main') !!}
            </div>
        @endforeach
    </div>
</div>
```

Example Blade view for a content page:

```blade
{{-- resources/views/ap-cms-admin/content/index.blade.php --}}
@extends('layouts.admin')

@section('content')
    <h1>Content Management</h1>
    <p>Select a content type from the submenu to manage your content.</p>
@endsection
```

### 5. Creating Your Application's Admin Layout

This will be your main layout file for all administrative pages:

```blade
{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} Admin</title>

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        <!-- Admin Navigation -->
        <nav class="bg-white border-b border-gray-100">
            <!-- Navigation content here -->
        </nav>

        <!-- Page Content -->
        <main>
            @yield('content')
        </main>
    </div>
    
    @livewireScripts
</body>
</html>
```

## Implementing Dashboard Widgets

### 1. Creating Your Application's Custom Dashboard Widget Types

Create a new directory, e.g., app/Widgets/, for your custom widget classes. Each widget class will extend the framework's DashboardWidget abstract class.

Example welcome widget:

```php
// app/Widgets/WelcomeDashboardWidget.php
<?php

namespace App\Widgets;

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;

class WelcomeDashboardWidget extends DashboardWidget
{
    protected function define(): void
    {
        $this->type = 'welcome-widget';
        $this->name = 'Welcome Widget';
        $this->slug = 'welcome';
        $this->description = 'A welcome message for the dashboard.';
        
        // Use either a Blade view or a Livewire component, not both
        $this->component = 'App\\Http\\Livewire\\Widgets\\WelcomeWidget';
        // $this->view = 'widgets.welcome-dashboard-widget';
    }
}
```

Example recent posts widget:

```php
// app/Widgets/RecentPostsDashboardWidget.php
<?php

namespace App\Widgets;

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;

class RecentPostsDashboardWidget extends DashboardWidget
{
    protected function define(): void
    {
        $this->type = 'recent-posts-widget';
        $this->name = 'Recent Posts';
        $this->slug = 'recent-posts';
        $this->description = 'Displays a list of recent posts.';
        
        // Using a Blade view for this widget
        $this->view = 'widgets.recent-posts-dashboard-widget';
    }
}
```

### 2. Registering Your Custom Dashboard Widget Types

Register these concrete widget type classes with the framework's DashboardWidgetsManager. A good place for this is your App\Providers\AppServiceProvider.php:

```php
// app/Providers/AppServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;
use App\Widgets\WelcomeDashboardWidget;
use App\Widgets\RecentPostsDashboardWidget;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register your custom dashboard widget types with the framework's manager
        app(DashboardWidgetsManager::class)->registerWidgetType(new WelcomeDashboardWidget());
        app(DashboardWidgetsManager::class)->registerWidgetType(new RecentPostsDashboardWidget());
    }
}
```

### 3. Creating Your Application's Widget Content

These are the actual UI components or Blade files that define what each widget displays:

Example Livewire component for the welcome widget:

```php
// app/Http/Livewire/Widgets/WelcomeWidget.php
<?php

namespace App\Http\Livewire\Widgets;

use Livewire\Component;

class WelcomeWidget extends Component
{
    // The widget instance ID is passed automatically
    public $widgetInstanceId;
    
    public function render()
    {
        // You can get widget-specific settings
        $settings = app(\ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager::class)
            ->getUserWidgetInstanceSettings($this->widgetInstanceId);
            
        return view('livewire.widgets.welcome-widget', [
            'settings' => $settings,
        ]);
    }
}
```

Example Blade view for the welcome widget:

```blade
{{-- resources/views/livewire/widgets/welcome-widget.blade.php --}}
<div>
    <h3 class="text-lg font-semibold mb-2">Welcome to Your Dashboard</h3>
    <p>This is a customizable welcome widget. You can edit its settings to change what appears here.</p>
    
    @if(isset($settings['show_quick_links']) && $settings['show_quick_links'])
        <div class="mt-4">
            <h4 class="font-medium">Quick Links</h4>
            <ul class="mt-2 space-y-1">
                <li><a href="{{ route('admin.content.posts') }}" class="text-blue-600 hover:underline">Manage Posts</a></li>
                <li><a href="{{ route('admin.content.pages') }}" class="text-blue-600 hover:underline">Manage Pages</a></li>
            </ul>
        </div>
    @endif
</div>
```

Example Blade view for the recent posts widget:

```blade
{{-- resources/views/widgets/recent-posts-dashboard-widget.blade.php --}}
@php
    // Get the most recent posts
    $posts = \App\Models\Post::latest()->limit(5)->get();
@endphp

<div class="recent-posts-widget">
    <h3 class="text-lg font-semibold mb-2">Recent Posts</h3>
    
    @if($posts->count() > 0)
        <ul class="space-y-2">
            @foreach($posts as $post)
                <li class="border-b pb-2">
                    <a href="{{ route('admin.content.posts.edit', $post->id) }}" class="text-blue-600 hover:underline">
                        {{ $post->title }}
                    </a>
                    <div class="text-sm text-gray-500">
                        {{ $post->created_at->diffForHumans() }} by {{ $post->author->name }}
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p>No posts found.</p>
    @endif
    
    <div class="mt-4">
        <a href="{{ route('admin.content.posts.create') }}" class="text-blue-600 hover:underline">
            Create New Post
        </a>
    </div>
</div>
```

## Adding Widgets to a Dashboard

To add widget instances to a user's dashboard, you can create a seeder or an admin interface:

```php
// Example seeder or admin controller method
public function addDefaultWidgets()
{
    $manager = app(DashboardWidgetsManager::class);
    
    // Add a welcome widget to the main dashboard
    $manager->addWidgetInstance(
        'welcome-widget',  // Widget type
        'main',            // Dashboard slug
        [
            'order' => 1,  // Display order (lower numbers appear first)
            'show_quick_links' => true,
        ]
    );
    
    // Add a recent posts widget to the main dashboard
    $manager->addWidgetInstance(
        'recent-posts-widget',
        'main',
        [
            'order' => 2,
        ]
    );
}
```

## Conclusion

By following this guide, you've learned how to implement admin pages and dashboard widgets in your custom CMS application using the ArtisanPackUI\CMSFramework package. The key points to remember are:

1. The framework provides the underlying infrastructure (AdminPagesManager, DashboardWidget, DashboardWidgetsManager)
2. Your application defines the specific admin pages, routes, and widget types
3. You need to register your custom admin routes file with Laravel
4. You need to create concrete implementations of dashboard widgets and register them with the framework

For more detailed information about specific features, refer to the following documentation:

- [Admin Menus](admin-menus.md): Detailed documentation on the AdminPagesManager
- [Dashboard Widgets](dashboard-widgets.md): Detailed documentation on the DashboardWidget and DashboardWidgetsManager
- [Overview](overview.md): General overview of the CMS Framework
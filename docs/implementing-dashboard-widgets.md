---
title: Implementing Dashboard Widgets
---

# Implementing Dashboard Widgets

This document provides detailed information about implementing dashboard widgets in a custom CMS application that uses the ArtisanPackUI\CMSFramework package.

## Overview

You'll create concrete implementations of DashboardWidget for each widget type you want to offer in your CMS.

## Creating Your Application's Custom Dashboard Widget Types

Create a new directory, e.g., app/Widgets/, for your custom widget classes. Each widget class will extend the framework's DashboardWidget abstract class.

### Example: WelcomeDashboardWidget.php

```php
// app/Widgets/WelcomeDashboardWidget.php
<?php
/**
 * Welcome Dashboard Widget
 *
 * Displays a welcome message on the dashboard.
 *
 * @package    YourApp
 * @subpackage YourApp\Widgets
 * @since      1.1.0
 */

namespace App\Widgets;

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;
use App\Http\Livewire\Widgets\WelcomeWidget as WelcomeLivewireWidget; // Alias to avoid conflict

/**
 * Welcome widget for the CMS Dashboard.
 *
 * @since 1.1.0
 */
class WelcomeDashboardWidget extends DashboardWidget
{
    /**
     * Defines the properties of the widget.
     *
     * @since 1.1.0
     * @return void
     */
    protected function define(): void
    {
        $this->type        = 'welcome_widget_type'; // Unique identifier for this widget CLASS.
        $this->name        = __( 'Welcome Widget', 'your-text-domain' );
        $this->slug        = 'welcome-widget';
        $this->description = __( 'A simple customizable welcome message for the dashboard.', 'your-text-domain' );
        $this->component   = WelcomeLivewireWidget::class; // Your app's Livewire component for its content.
    }
}
```

### Example: RecentPostsDashboardWidget.php

```php
// app/Widgets/RecentPostsDashboardWidget.php
<?php
/**
 * Recent Posts Dashboard Widget
 *
 * Displays a list of recently published posts.
 *
 * @package    YourApp
 * @subpackage YourApp\Widgets
 * @since      1.1.0
 */

namespace App\Widgets;

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;

/**
 * Recent Posts widget for the CMS Dashboard.
 *
 * @since 1.1.0
 */
class RecentPostsDashboardWidget extends DashboardWidget
{
    /**
     * Defines the properties of the widget.
     *
     * @since 1.1.0
     * @return void
     */
    protected function define(): void
    {
        $this->type        = 'recent_posts_widget_type';
        $this->name        = __( 'Recent Posts', 'your-text-domain' );
        $this->slug        = 'recent-posts-widget';
        $this->description = __( 'Displays a configurable number of most recent posts.', 'your-text-domain' );
        $this->view        = 'widgets.recent-posts-dashboard-widget'; // Your app's Blade view for its content.
    }
}
```

## Registering Your Custom Dashboard Widget Types

Register these concrete widget type classes with the framework's DashboardWidgetsManager. A good place for this is your App\Providers\AppServiceProvider.php (or a custom service provider you create for app-specific dashboard features).

```php
// app/Providers/AppServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;
use App\Widgets\WelcomeDashboardWidget; // Your widget type
use App\Widgets\RecentPostsDashboardWidget; // Your widget type

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register your custom dashboard widget types with the framework's manager
        app( DashboardWidgetsManager::class )->registerWidgetType( new WelcomeDashboardWidget() );
        app( DashboardWidgetsManager::class )->registerWidgetType( new RecentPostsDashboardWidget() );
    }
}
```

## Creating Your Application's Widget Content (Livewire Components or Blade Views)

These are the actual UI components or Blade files that define what each widget displays. They are nested within the dashboard page.

### Example: WelcomeWidget.php (for WelcomeDashboardWidget)

```php
// app/Http/Livewire/Widgets/WelcomeWidget.php
<?php

namespace App\Http\Livewire\Widgets;

use Livewire\Component;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;
use Illuminate\Support\Str; // For Str::limit

class WelcomeWidget extends Component
{
    public string $widgetInstanceId;
    public string $dashboardSlug; // Passed from the parent dashboard component
    public array $settings = []; // Current settings for this specific instance
    public string $welcomeMessage;

    protected $rules = [
        'welcomeMessage' => 'required|string|max:255',
    ];

    public function mount( string $widgetInstanceId, string $dashboardSlug = 'main' ): void
    {
        $this->widgetInstanceId = $widgetInstanceId;
        $this->dashboardSlug    = $dashboardSlug;

        // Load initial settings for this specific instance from the framework's manager
        $this->settings = app( DashboardWidgetsManager::class )->getUserWidgetInstanceSettings(
            $this->widgetInstanceId,
            $this->dashboardSlug,
            [ 'title' => 'Welcome', 'welcome_message' => 'Hello, User!' ] // Default values if settings not found
        );

        $this->welcomeMessage = $this->settings['welcome_message'] ?? 'Hello, User!';
    }

    public function saveSettings(): void
    {
        $this->validate();

        $this->settings['welcome_message'] = $this->welcomeMessage;
        // You might update the display title based on a setting
        $this->settings['title'] = 'Welcome Widget (' . Str::limit( $this->welcomeMessage, 20 ) . ')';

        // Save updated settings for this specific instance via the framework's manager
        app( DashboardWidgetsManager::class )->saveUserWidgetInstanceSettings(
            $this->widgetInstanceId,
            $this->settings,
            $this->dashboardSlug
        );

        session()->flash( 'message', 'Widget settings saved.' );
        // Emit an event to notify the parent dashboard component to refresh its widget list
        $this->dispatch( 'widgetSettingsUpdated' );
    }

    public function render()
    {
        return view( 'livewire.widgets.welcome-widget' );
    }
}
```

### Example: welcome-widget.blade.php

```blade
{{-- resources/views/livewire/widgets/welcome-widget.blade.php --}}
<div>
    <p>{{ $welcomeMessage }}</p>

    <form wire:submit.prevent="saveSettings" class="mt-4">
        <label for="welcomeMessage_{{ $widgetInstanceId }}" class="block text-sm font-medium text-gray-700">Custom Message:</label>
        <input type="text" id="welcomeMessage_{{ $widgetInstanceId }}" wire:model.lazy="welcomeMessage" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
        @error('welcomeMessage') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

        <button type="submit" class="mt-2 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Save Message
        </button>
    </form>
</div>
```

### Example: recent-posts-dashboard-widget.blade.php (for RecentPostsDashboardWidget)

This example assumes you pass required data directly to the Blade view (e.g., from MainDashboard's renderWidgetInstance call), including widgetInstanceId and other settings.

```blade
{{-- resources/views/widgets/recent-posts-dashboard-widget.blade.php --}}
@php
    // Access settings passed from the DashboardWidgetsManager::renderWidgetInstance() call
    $num_posts = $num_posts ?? 5; // Default if not provided in instance settings
    // Example: Fetch posts from your App\Models\Post
    $posts = \App\Models\Post::orderBy('created_at', 'desc')->take($num_posts)->get();
@endphp

<h4 class="text-lg font-medium mb-2">{{ __('Recent Posts', 'your-text-domain') }} (Showing {{ $num_posts }})</h4>
<ul>
    @forelse ($posts as $post)
        <li><a href="/admin/content/posts/{{ $post->id }}/edit">{{ $post->title }}</a></li>
    @empty
        <li>{{ __('No recent posts found.', 'your-text-domain') }}</li>
    @endforelse
</ul>

{{-- Example of a simple settings form for a Blade-only widget (requires parent Livewire component to handle updates) --}}
<form x-data="{ numPosts: {{ $num_posts }} }"
      @submit.prevent="$wire.updateWidgetInstanceSettings('{{ $widgetInstanceId }}', { num_posts: numPosts, title: 'Recent Posts', order: 1 })"
      class="mt-4">
      {{-- The @submit.prevent calls a method on the parent Livewire dashboard component --}}
      {{-- updateWidgetInstanceSettings would be a method you add to MainDashboard.php --}}
      {{-- which then calls DashboardWidgetsManager::saveUserWidgetInstanceSettings --}}
    <label for="num_posts_{{ $widgetInstanceId }}" class="block text-sm font-medium text-gray-700">Posts to show:</label>
    <input type="number" id="num_posts_{{ $widgetInstanceId }}" x-model="numPosts" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
    <button type="submit" class="mt-2 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700">Save</button>
</form>
```

## Related Documentation

- [Dashboard Widgets](dashboard-widgets.md): Core documentation on the Dashboard Widgets feature
- [Admin Menus](admin-menus.md): Documentation on admin menus, which can host dashboards with widgets
- [Custom CMS Implementation](custom-cms-implementation.md): Complete guide on implementing a custom CMS application
---
title: Dashboard Widgets
---

# Dashboard Widgets

This document provides detailed information about the Dashboard Widgets feature in the ArtisanPack UI CMS Framework.

## Overview

The Dashboard Widgets feature allows you to create and manage customizable widgets that can be displayed on dashboard pages within your CMS. It provides a flexible system for creating different types of widgets, managing their instances, and handling user-specific settings and preferences.

Dashboard widgets can display various types of content, from simple statistics to complex interactive components, and can be rendered using either Blade views or Livewire components.

## Core Components

### DashboardWidget

The `DashboardWidget` abstract class serves as the base for all dashboard widget types.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets;
```

#### Properties

- `$type`: The unique type/identifier for the widget class.
- `$name`: The display name of the widget.
- `$slug`: The slug for the widget type (e.g., 'welcome-widget').
- `$description`: Optional description for the widget.
- `$view`: The Blade view path for the widget's content.
- `$component`: The Livewire component class for the widget's content.

#### Key Methods

- `getType()`: Retrieves the widget's type identifier.
- `getName()`: Retrieves the widget's display name.
- `getSlug()`: Retrieves the widget's slug.
- `getDescription()`: Retrieves the widget's description.
- `getSettings($instanceId, $dashboardSlug, $default)`: Gets user-specific settings for a widget instance.
- `saveSettings($instanceId, $settings, $dashboardSlug)`: Saves user-specific settings for a widget instance.
- `render($instanceId, $data)`: Renders the widget content using either a Livewire component or a Blade view.
- `init()`: Initializes the widget by calling the define method.
- `define()`: Abstract method that child classes must implement to set widget properties.

### DashboardWidgetsManager

The `DashboardWidgetsManager` class manages the registration and rendering of dashboard widgets.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\DashboardWidgets;
```

#### Properties

- `$widgetTypes`: An array of registered dashboard widget types, keyed by widget type.

#### Key Methods

- `registerWidgetType($widgetType)`: Registers a new dashboard widget type.
- `getWidgetType($type)`: Gets a specific registered widget type.
- `getRegisteredWidgetTypes()`: Gets all registered dashboard widget types.
- `addWidgetInstance($widgetType, $dashboardSlug, $initialSettings)`: Adds a new instance of a widget type to a dashboard.
- `getDashboardWidgetInstances($dashboardSlug)`: Gets all widget instances for a specific dashboard and user.
- `saveDashboardWidgetInstances($dashboardSlug, $instances)`: Saves all widget instances for a specific dashboard and user.
- `removeWidgetInstance($instanceId, $dashboardSlug)`: Removes a widget instance from a dashboard.
- `getUserWidgetInstanceSettings($instanceId, $dashboardSlug, $default)`: Gets user-specific settings for a widget instance.
- `saveUserWidgetInstanceSettings($instanceId, $settings, $dashboardSlug)`: Saves user-specific settings for a widget instance.
- `renderWidgetInstance($instanceId, $dashboardSlug, $data)`: Renders a specific widget instance.

## Using Dashboard Widgets

### Creating a Widget Type

To create a new widget type, extend the `DashboardWidget` abstract class and implement the `define()` method:

```php
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;

class WelcomeWidget extends DashboardWidget
{
    protected function define(): void
    {
        $this->type = 'welcome-widget';
        $this->name = 'Welcome Widget';
        $this->slug = 'welcome';
        $this->description = 'A welcome message for the dashboard.';
        $this->view = 'widgets.welcome'; // Blade view path
        // Or use a Livewire component instead:
        // $this->component = 'App\\Http\\Livewire\\Widgets\\Welcome';
    }
}
```

### Registering a Widget Type

Register your widget type with the `DashboardWidgetsManager`:

```php
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;
use Illuminate\Support\Facades\App;

// Get the DashboardWidgetsManager instance
$manager = App::make(DashboardWidgetsManager::class);

// Create and register the widget type
$welcomeWidget = new WelcomeWidget();
$manager->registerWidgetType($welcomeWidget);
```

This is typically done in a service provider's `boot` method:

```php
// In a service provider
public function boot(): void
{
    $manager = $this->app->make(DashboardWidgetsManager::class);
    $manager->registerWidgetType(new WelcomeWidget());
    $manager->registerWidgetType(new StatsWidget());
    // Register more widget types...
}
```

### Adding Widget Instances to a Dashboard

To add a widget instance to a user's dashboard:

```php
// Add a welcome widget to the main dashboard
$instanceId = $manager->addWidgetInstance(
    'welcome-widget',  // Widget type
    'main',            // Dashboard slug
    [
        'order' => 5,  // Display order (lower numbers appear first)
        // Additional widget-specific settings
        'title' => 'Welcome to Your Dashboard',
    ]
);
```

### Getting Widget Instances for a Dashboard

To retrieve all widget instances for a specific dashboard:

```php
// Get all widgets for the main dashboard
$widgets = $manager->getDashboardWidgetInstances('main');

// Process each widget
foreach ($widgets as $widget) {
    $instanceId = $widget['id'];
    $widgetType = $widget['type'];
    $settings = $widget['settings'];

    // Render the widget
    $html = $manager->renderWidgetInstance($instanceId, 'main');

    // Output the widget HTML
    echo $html;
}
```

### Managing Widget Settings

Widget instances can have user-specific settings:

```php
// Get settings for a specific widget instance
$settings = $manager->getUserWidgetInstanceSettings($instanceId, 'main');

// Update settings for a specific widget instance
$manager->saveUserWidgetInstanceSettings(
    $instanceId,
    [
        'order' => 10,
        'title' => 'Updated Title',
        'show_stats' => true,
    ],
    'main'
);
```

### Removing Widget Instances

To remove a widget instance from a dashboard:

```php
$removed = $manager->removeWidgetInstance($instanceId, 'main');
```

### Rendering Widget Instances

To render a specific widget instance:

```php
// Render a widget with additional data
$html = $manager->renderWidgetInstance(
    $instanceId,
    'main',
    [
        'additional_data' => 'Some value',
    ]
);
```

## Customizing Dashboard Widgets

### Using the Eventy Filter

You can use the Eventy filter system to modify the registered widget types:

```php
use TorMorten\Eventy\Facades\Eventy;

// Add or modify widget types
Eventy::addFilter('ap.cms.dashboard.widget_types', function($widgetTypes) {
    // Add a new widget type programmatically
    $customWidget = new CustomWidget();
    $customWidget->init();
    $widgetTypes[$customWidget->getType()] = $customWidget;

    return $widgetTypes;
});
```

## Best Practices

1. **Keep widgets focused**: Each widget should serve a specific purpose and display focused information.
2. **Use appropriate rendering method**: Use Blade views for simple content and Livewire components for interactive widgets.
3. **Handle empty states**: Always provide meaningful content when a widget has no data to display.
4. **Respect user settings**: Honor user preferences for widget placement and configuration.
5. **Optimize performance**: Widgets should load quickly and not slow down the dashboard.
6. **Make widgets responsive**: Ensure widgets display properly on different screen sizes.
7. **Provide clear descriptions**: Widget descriptions should clearly explain the widget's purpose.

## Related Documentation

- [Overview](overview.md): General overview of the CMS Framework
- [Admin Menus](admin-menus.md): Documentation on admin menus, which can host dashboards with widgets
- [Users](users.md): Documentation on user management, which relates to user-specific widget settings
- [Custom CMS Implementation](custom-cms-implementation.md): Guide on implementing dashboard widgets in a custom CMS application
- [Implementing Dashboard Widgets](implementing-dashboard-widgets.md): Detailed examples of implementing dashboard widgets in a custom CMS application

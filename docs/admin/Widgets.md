---
title: Admin Widgets
---

# Admin Widgets

The Admin Widgets system lets you register dashboard widgets that users can add to a customizable admin dashboard.

## Components

- AdminWidgetManager — Central registry for available widget types
- AdminWidgetInterface — Contract that all widget classes must implement

## Creating a Widget Type

Implement the AdminWidgetInterface on a component class in your app or package:

```php
use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Contracts\AdminWidgetInterface;

class StatsWidget implements AdminWidgetInterface
{
    public static function getWidgetInfo(): array
    {
        return [
            'title' => 'Site Statistics',
            'description' => 'Key performance metrics for your site.',
            'default_options' => [
                'range' => '7d',
                'metrics' => ['visits', 'signups'],
            ],
        ];
    }
}
```

## Registering a Widget Type

```php
use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Services\AdminWidgetManager;

app(AdminWidgetManager::class)->register('stats', StatsWidget::class);
```

The manager validates that your class implements AdminWidgetInterface before registering.

## Listing Available Widgets

```php
$available = app(AdminWidgetManager::class)->getAvailableWidgets();
// [
//   'stats' => [
//     'title' => 'Site Statistics',
//     'description' => 'Key performance metrics for your site.',
//     'default_options' => [ ... ]
//   ],
// ]
```

## Creating a Widget Instance

```php
$widget = app(AdminWidgetManager::class)->createWidget('stats');
// Example structure:
// [
//   'id' => 'uuid-string',
//   'type' => 'stats',
//   'component_class' => StatsWidget::class,
//   'title' => 'Site Statistics',
//   'order' => 0,
//   'color_scheme' => 'base-100',
//   'grid_config' => [ 'sm' => ['rows' => 2, 'cols' => 12], ... ],
//   'options' => [ 'range' => '7d', 'metrics' => ['visits', 'signups'] ],
//   'created_at' => 'ISO8601',
//   'updated_at' => 'ISO8601',
// ]
```

Note the component_class key can be used by your front‑end to render the appropriate component.

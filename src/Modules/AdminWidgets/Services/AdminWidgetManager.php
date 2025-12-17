<?php

/**
 * Manages the registration and creation of admin dashboard widgets.
 *
 * This class acts as a central registry for all available widget types, allowing the CMS
 * and any installed plugins to add their own widgets to the system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Services;

use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Contracts\AdminWidgetInterface;
use Illuminate\Support\Str;

/**
 * Manages admin dashboard widgets.
 *
 * @since 1.0.0
 */
class AdminWidgetManager
{
    /**
     * A registry of available widget types.
     *
     * @since 1.0.0
     */
    protected array $widgets = [];

    /**
     * Registers a new admin widget type.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The unique identifier for the widget type.
     * @param  string  $class  The fully qualified class name of the widget component.
     */
    public function register(string $type, string $class): void
    {
        if (in_array(AdminWidgetInterface::class, class_implements($class), true)) {
            $this->widgets[$type] = $class;
        }
    }

    /**
     * Creates a new widget instance with default data.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The type of widget to create.
     * @return array|null The default widget data array, or null if type is not registered.
     */
    public function createWidget(string $type): ?array
    {
        if (! isset($this->widgets[$type])) {
            return null;
        }

        $class = $this->widgets[$type];
        $info = $class::getWidgetInfo();
        $defaults = $info['default_options'] ?? [];

        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'component_class' => $class, // ADDED THIS LINE
            'title' => $info['title'] ?? 'New Widget',
            'capability' => $info['capability'] ?? null,
            'order' => 0,
            'color_scheme' => 'base-100',
            'grid_config' => [
                'sm' => [
                    'rows' => 2,
                    'cols' => 12,
                ],
                'md' => [
                    'rows' => 1,
                    'cols' => 6,
                ],
                'lg' => [
                    'rows' => 1,
                    'cols' => 4,
                ],
                'xl' => [
                    'rows' => 1,
                    'cols' => 3,
                ],
            ],
            'options' => $defaults,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get available widgets filtered by user capabilities.
     *
     * @since 1.0.0
     *
     * @param  \Modules\Users\Models\User|null  $user  The user to check capabilities for.
     * @return array Available widgets for the user.
     */
    public function getAvailableWidgetsForUser($user = null): array
    {
        $available = $this->getAvailableWidgets();

        if (! $user) {
            return $available;
        }

        // Get admin-configured capability overrides
        $widgetCapabilities = apGetSetting('admin.dashboardWidgets', []);

        return array_filter($available, function ($widgetInfo, $type) use ($user, $widgetCapabilities) {
            // Check if admin has overridden the capability
            $capability = $widgetCapabilities[$type] ?? $widgetInfo['capability'] ?? null;

            if (! $capability) {
                return true; // No capability required
            }

            return $user->hasPermissionTo($capability);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Retrieves information about all registered admin widgets.
     *
     * @since 1.0.0
     *
     * @return array An associative array of widget information.
     */
    public function getAvailableWidgets(): array
    {
        $available = [];
        foreach ($this->widgets as $type => $class) {
            $available[$type] = $class::getWidgetInfo();
        }

        return $available;
    }
}

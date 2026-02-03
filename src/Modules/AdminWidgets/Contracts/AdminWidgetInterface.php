<?php

declare( strict_types = 1 );

/**
 * Defines the contract for an admin dashboard widget.
 *
 * This interface ensures that any class intended to be used as a dashboard widget
 * implements the necessary methods for the AdminWidgetManager to retrieve
 * essential metadata.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Contracts;

/**
 * Interface for admin dashboard widgets.
 *
 * @since 1.0.0
 */
interface AdminWidgetInterface
{
    /**
     * Returns information about the widget for registration.
     *
     * This static method provides metadata used in the 'Add Widget' panel
     * and for setting default values when a new widget is created.
     *
     * @since 1.0.0
     *
     * @return array{
     * title: string,
     * description: string,
     * capability?: string,
     * default_options?: array<string, mixed>
     * } The widget information.
     */
    public static function getWidgetInfo(): array;
}

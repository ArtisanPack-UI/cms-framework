<?php

/**
 * Notification Type Enum
 *
 * Defines the available notification types for the system.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Enums;

/**
 * Enum for notification types.
 *
 * @since 2.0.0
 */
enum NotificationType: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Success = 'success';
    case Info = 'info';

    /**
     * Get the label for the notification type.
     *
     * @since 2.0.0
     *
     * @return string The human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Error => __('Error'),
            self::Warning => __('Warning'),
            self::Success => __('Success'),
            self::Info => __('Info'),
        };
    }

    /**
     * Get the icon for the notification type.
     *
     * @since 2.0.0
     *
     * @return string The icon identifier.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Error => 'fas.circle-exclamation',
            self::Warning => 'fas.triangle-exclamation',
            self::Success => 'fas.circle-check',
            self::Info => 'fas.circle-info',
        };
    }

    /**
     * Get the color class for the notification type.
     *
     * @since 2.0.0
     *
     * @return string The color class.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Error => 'text-red-600 dark:text-red-400',
            self::Warning => 'text-yellow-600 dark:text-yellow-400',
            self::Success => 'text-green-600 dark:text-green-400',
            self::Info => 'text-blue-600 dark:text-blue-400',
        };
    }
}

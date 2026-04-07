<?php

/**
 * Update Type Enum
 *
 * Defines the valid update types for the update checker system.
 *
 *
 * @since 1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums;

/**
 * Enum for update types.
 *
 * @since 1.1.0
 */
enum UpdateType: string
{
    case Application = 'application';
    case Plugin      = 'plugin';
    case Theme       = 'theme';
}

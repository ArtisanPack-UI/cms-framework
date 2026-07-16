<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums;

/**
 * Source of a dynamic content type registration.
 *
 * @since 2.4.0
 */
enum Source: string
{
    case Db   = 'db';
    case Code = 'code';
}

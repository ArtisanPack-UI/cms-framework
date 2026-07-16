<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums;

/**
 * Cardinality of a dynamic content type.
 *
 * @since 2.4.0
 */
enum Cardinality: string
{
    case Singleton  = 'singleton';
    case Collection = 'collection';
}

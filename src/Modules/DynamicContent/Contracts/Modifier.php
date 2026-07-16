<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts;

/**
 * Contract for a token modifier (e.g. `|upper`, `|truncate:100`).
 *
 * @since 2.4.0
 */
interface Modifier
{
    public function slug(): string;

    /**
     * Apply the modifier to a value.
     *
     * @param  array<int, string>  $args
     */
    public function apply( mixed $value, array $args ): mixed;
}

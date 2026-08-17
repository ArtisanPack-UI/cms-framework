<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support\DependencyOrder;

/**
 * Records the order in which plugin service providers register, so tests can
 * assert dependency-first boot ordering.
 */
class BootOrderRecorder
{
    /**
     * @var array<int,string>
     */
    public static array $order = [];

    public static function record( string $slug ): void
    {
        self::$order[] = $slug;
    }

    public static function reset(): void
    {
        self::$order = [];
    }
}

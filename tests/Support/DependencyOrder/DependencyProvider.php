<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support\DependencyOrder;

use Illuminate\Support\ServiceProvider;

/**
 * Stub provider for the dependency plugin in the boot-ordering test.
 */
class DependencyProvider extends ServiceProvider
{
    public function register(): void
    {
        BootOrderRecorder::record( 'dependency-plugin' );
    }
}

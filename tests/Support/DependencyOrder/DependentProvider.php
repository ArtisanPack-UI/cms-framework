<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support\DependencyOrder;

use Illuminate\Support\ServiceProvider;

/**
 * Stub provider for the dependent plugin in the boot-ordering test.
 */
class DependentProvider extends ServiceProvider
{
    public function register(): void
    {
        BootOrderRecorder::record( 'dependent-plugin' );
    }
}

<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns\ResetsWebOpcache;
use ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns\RestartsQueueWorkers;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Coverage for the `queue:restart` step the self-updater (#452) and the plugin
 * and theme managers (#450, #453) share.
 */
beforeEach( function (): void {
    $this->restarter = new class {
        use ResetsWebOpcache;
        use RestartsQueueWorkers;

        public function restart( string $after, array $context = [] ): void
        {
            $this->restartQueueWorkers( $after, $context );
        }
    };
} );

it( 'signals the queue workers to restart', function (): void {
    Cache::forget( 'illuminate:queue:restart' );

    $this->restarter->restart( 'the update' );

    expect( Cache::get( 'illuminate:queue:restart' ) )->toBeInt();
} );

it( 'logs a warning instead of throwing when the restart fails', function (): void {
    Artisan::swap( new class {
        public function call( $command, array $parameters = [], $outputBuffer = null ): int
        {
            if ( 'queue:restart' === $command ) {
                throw new RuntimeException( "Cache store\nunreachable." );
            }

            return 0;
        }
    } );

    Log::shouldReceive( 'warning' )
        ->once()
        ->withArgs( fn ( string $message, array $context ): bool => str_contains( $message, 'after the update' )
            && str_contains( $message, 'php artisan queue:restart' )
            && 'core' === $context['scope']
            && ! str_contains( $context['error'], "\n" ) );

    $this->restarter->restart( 'the update', ['scope' => 'core'] );
} );

it( 'strips control characters from the log context, which can carry a raw route slug', function (): void {
    Artisan::swap( new class {
        public function call( $command, array $parameters = [], $outputBuffer = null ): int
        {
            if ( 'queue:restart' === $command ) {
                throw new RuntimeException( 'Cache store unreachable.' );
            }

            return 0;
        }
    } );

    Log::shouldReceive( 'warning' )
        ->once()
        ->withArgs( fn ( string $message, array $context ): bool => "evil\e[31mslug" !== $context['plugin']
            && ! str_contains( $context['plugin'], "\n" )
            && ! str_contains( $context['plugins'][0], "\n" ) );

    $this->restarter->restart( 'activating a plugin', [
        'plugin'  => "evil\n\e[31mslug",
        'plugins' => ["other\nslug"],
    ] );
} );

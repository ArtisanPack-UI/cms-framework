<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Providers\CoreServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\FastCgiClient;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;
use ArtisanPackUI\CMSFramework\Tests\Support\FakeFastCgiClient;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\URL;
use Orchestra\Testbench\TestCase;

/**
 * Web OPcache Resetter Tests
 *
 * Coverage for #429: a CLI or queued update resets PHP-FPM's OPcache over
 * FastCGI, then the signed loopback route, then the operator's reload
 * command, and only reports a required reload when all three fail.
 *
 * @since 2.16.0
 */
class WebOpcacheResetterTest extends TestCase
{
    /**
     * Turn every mechanism off, so each test enables only what it covers.
     *
     * @since 2.16.0
     */
    protected function setUp(): void
    {
        parent::setUp();

        config( [
            'cms.updates.opcache.fastcgi'      => false,
            'cms.updates.opcache.fpm_socket'   => null,
            'cms.updates.opcache.loopback'     => false,
            'cms.updates.opcache.loopback_url' => null,
            'cms.updates.php_reload_command'   => null,
        ] );
    }

    /**
     * Test that FastCGI runs a standalone script that prints the marker, and
     * that the script is removed afterwards.
     *
     * @since 2.16.0
     */
    public function test_fastcgi_runs_a_reset_script_through_the_configured_socket(): void
    {
        config( [
            'cms.updates.opcache.fastcgi'    => true,
            'cms.updates.opcache.fpm_socket' => '/run/php/php8.4-fpm.sock',
        ] );

        $client   = new FakeFastCgiClient( "Content-type: text/html; charset=UTF-8\r\n\r\n" . WebOpcacheResetter::FASTCGI_MARKER . 'reset' );
        $resetter = new WebOpcacheResetter( $client );

        $this->assertSame( 'fastcgi', $resetter->reset() );

        $this->assertSame( '/run/php/php8.4-fpm.sock', $client->address );
        $this->assertSame( 'GET', $client->params['REQUEST_METHOD'] );
        $this->assertStringStartsWith( storage_path( 'framework/cms-opcache-reset-' ), $client->params['SCRIPT_FILENAME'] );
        $this->assertFileDoesNotExist( $client->params['SCRIPT_FILENAME'], 'The script is removed once FPM has run it.' );

        // The script FPM was handed is valid, standalone PHP that reports
        // through the marker.
        $this->assertMatchesRegularExpression(
            '/^' . preg_quote( WebOpcacheResetter::FASTCGI_MARKER, '/' ) . '(reset|failed|disabled)$/',
            $client->scriptOutput,
        );
    }

    /**
     * Test that FPM with OPcache disabled counts as reached: nothing is stale.
     *
     * @since 2.16.0
     */
    public function test_fastcgi_with_opcache_disabled_needs_no_reload(): void
    {
        config( [
            'cms.updates.opcache.fastcgi'    => true,
            'cms.updates.opcache.fpm_socket' => '127.0.0.1:9000',
        ] );

        $resetter = new WebOpcacheResetter( new FakeFastCgiClient( WebOpcacheResetter::FASTCGI_MARKER . 'disabled' ) );

        $this->assertSame( 'fastcgi', $resetter->reset() );
    }

    /**
     * Test that a socket is discovered when none is configured.
     *
     * @since 2.16.0
     */
    public function test_fastcgi_discovers_a_socket_when_none_is_configured(): void
    {
        config( ['cms.updates.opcache.fastcgi' => true] );

        $socket = tempnam( sys_get_temp_dir(), 'cmsfw-fpm-' );
        $client = new FakeFastCgiClient( WebOpcacheResetter::FASTCGI_MARKER . 'reset' );

        $resetter = new class( $client, $socket ) extends WebOpcacheResetter {
            public function __construct( FastCgiClient $fastCgi, protected string $socket )
            {
                parent::__construct( $fastCgi );
            }

            protected function candidateSockets(): array
            {
                return ['/nonexistent/fpm.sock', $this->socket];
            }
        };

        try {
            $this->assertSame( 'fastcgi', $resetter->reset() );
            $this->assertSame( $socket, $client->address );
        } finally {
            @unlink( $socket );
        }
    }

    /**
     * Test that the candidate sockets match the running PHP version.
     *
     * @since 2.16.0
     */
    public function test_candidate_sockets_target_the_running_php_version(): void
    {
        $resetter = new class extends WebOpcacheResetter {
            public function callCandidateSockets(): array
            {
                return $this->candidateSockets();
            }
        };

        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        $this->assertContains( "/run/php/php{$version}-fpm.sock", $resetter->callCandidateSockets() );
        $this->assertContains( '/run/php-fpm/www.sock', $resetter->callCandidateSockets() );
    }

    /**
     * Test that reset scripts left by a killed run are removed.
     *
     * @since 2.16.0
     */
    public function test_fastcgi_removes_scripts_left_by_a_killed_run(): void
    {
        config( [
            'cms.updates.opcache.fastcgi'    => true,
            'cms.updates.opcache.fpm_socket' => '/run/php/php8.4-fpm.sock',
        ] );

        $stale = storage_path( 'framework/cms-opcache-reset-' . str_repeat( 'a', 32 ) . '.php' );
        file_put_contents( $stale, '<?php' );

        $resetter = new WebOpcacheResetter( new FakeFastCgiClient( WebOpcacheResetter::FASTCGI_MARKER . 'reset' ) );

        $this->assertSame( 'fastcgi', $resetter->reset() );
        $this->assertFileDoesNotExist( $stale );
    }

    /**
     * Test that a refused reset (`opcache.restrict_api`) falls through and
     * is explained.
     *
     * @since 2.16.0
     */
    public function test_fastcgi_refusal_falls_through_with_a_reason(): void
    {
        config( [
            'cms.updates.opcache.fastcgi'    => true,
            'cms.updates.opcache.fpm_socket' => '/run/php/php8.4-fpm.sock',
        ] );

        $resetter = new WebOpcacheResetter( new FakeFastCgiClient( WebOpcacheResetter::FASTCGI_MARKER . 'failed' ) );

        $this->assertNull( $resetter->reset() );
        $this->assertStringContainsString( 'opcache.restrict_api', $resetter->failures()['fastcgi'] );
    }

    /**
     * Test that a connection failure is caught, recorded and falls through.
     *
     * @since 2.16.0
     */
    public function test_fastcgi_connection_failure_falls_through(): void
    {
        config( [
            'cms.updates.opcache.fastcgi'    => true,
            'cms.updates.opcache.fpm_socket' => '/nonexistent/fpm.sock',
        ] );

        $resetter = new WebOpcacheResetter;

        $this->assertNull( $resetter->reset() );
        $this->assertStringContainsString( 'could not connect', $resetter->failures()['fastcgi'] );
        $this->assertSame( [], glob( storage_path( 'framework/cms-opcache-reset-*.php' ) ), 'The script is removed when FPM cannot be reached.' );
    }

    /**
     * Test that output without the marker (another script answered) is not
     * mistaken for a reset.
     *
     * @since 2.16.0
     */
    public function test_fastcgi_output_without_the_marker_is_not_a_reset(): void
    {
        config( [
            'cms.updates.opcache.fastcgi'    => true,
            'cms.updates.opcache.fpm_socket' => '/run/php/php8.4-fpm.sock',
        ] );

        $resetter = new WebOpcacheResetter( new FakeFastCgiClient( "Status: 404 Not Found\r\n\r\nFile not found." ) );

        $this->assertNull( $resetter->reset() );
        $this->assertStringContainsString( 'did not run the reset script', $resetter->failures()['fastcgi'] );
    }

    /**
     * Test that the loopback mechanism requests a relative-signed URL on the
     * configured base URL.
     *
     * @since 2.16.0
     */
    public function test_loopback_requests_a_signed_url_on_the_configured_base(): void
    {
        config( [
            'cms.updates.opcache.loopback'     => true,
            'cms.updates.opcache.loopback_url' => 'https://keystone.test/',
        ] );

        Http::fake( ['*' => Http::response( ['opcache' => 'reset', 'php_sapi' => 'fpm-fcgi'] )] );

        $this->assertSame( 'loopback', ( new WebOpcacheResetter )->reset() );

        Http::assertSent( fn ( Request $request ): bool => str_starts_with( $request->url(), 'https://keystone.test/cms-framework/updates/opcache-reset?' )
            && str_contains( $request->url(), 'signature=' )
            && str_contains( $request->url(), 'expires=' ) );
    }

    /**
     * Test that the loopback falls back to `app.url`.
     *
     * @since 2.16.0
     */
    public function test_loopback_defaults_to_the_app_url(): void
    {
        config( [
            'cms.updates.opcache.loopback' => true,
            'app.url'                      => 'https://app.example.com',
        ] );

        Http::fake( ['*' => Http::response( ['opcache' => 'disabled'] )] );

        $this->assertSame( 'loopback', ( new WebOpcacheResetter )->reset() );

        Http::assertSent( fn ( Request $request ): bool => str_starts_with( $request->url(), 'https://app.example.com/cms-framework/updates/opcache-reset?' ) );
    }

    /**
     * Test that a loopback error response falls through to the command.
     *
     * @since 2.16.0
     */
    public function test_loopback_error_falls_through_to_the_reload_command(): void
    {
        config( [
            'cms.updates.opcache.loopback'   => true,
            'cms.updates.php_reload_command' => 'sudo -S service php8.4-fpm reload',
        ] );

        Http::fake( ['*' => Http::response( 'Service Unavailable', 503 )] );
        Process::fake();

        $resetter = new WebOpcacheResetter;

        $this->assertSame( 'command', $resetter->reset() );
        $this->assertStringContainsString( 'HTTP 503', $resetter->failures()['loopback'] );

        Process::assertRan( fn ( PendingProcess $process ): bool => 'sudo -S service php8.4-fpm reload' === $process->command
            && base_path() === $process->path );
    }

    /**
     * Test that a failing reload command is reported with its exit code and
     * output, but never the command itself, which may carry a password.
     *
     * @since 2.16.0
     */
    public function test_failing_reload_command_is_reported_without_the_command(): void
    {
        config( ['cms.updates.php_reload_command' => "echo 's3cret' | sudo -S service php8.4-fpm reload"] );

        Process::fake( ['*' => Process::result( errorOutput: 'Sorry, try again.', exitCode: 1 )] );

        $resetter = new WebOpcacheResetter;

        $this->assertNull( $resetter->reset() );
        $this->assertSame( 'cms.updates.php_reload_command exited 1: Sorry, try again.', $resetter->failures()['command'] );
        $this->assertStringNotContainsString( 's3cret', implode( ' ', $resetter->failures() ) );
    }

    /**
     * Test that with nothing enabled or configured, every mechanism says why
     * it was skipped.
     *
     * @since 2.16.0
     */
    public function test_nothing_configured_reports_every_mechanism(): void
    {
        Process::fake();

        $resetter = new WebOpcacheResetter;

        $this->assertNull( $resetter->reset() );
        $this->assertSame( ['fastcgi', 'loopback', 'command'], array_keys( $resetter->failures() ) );

        Process::assertNothingRan();
    }

    /**
     * Test that the loopback route rejects an unsigned request.
     *
     * @since 2.16.0
     */
    public function test_loopback_route_rejects_an_unsigned_request(): void
    {
        $this->get( '/cms-framework/updates/opcache-reset' )->assertForbidden();
    }

    /**
     * Test that the loopback route resets OPcache for a signed request.
     *
     * @since 2.16.0
     */
    public function test_loopback_route_reports_the_reset_for_a_signed_request(): void
    {
        $response = $this->getJson( URL::temporarySignedRoute( WebOpcacheResetter::LOOPBACK_ROUTE, now()->addMinute(), [], false ) );

        $response->assertOk()
            ->assertJsonPath( 'php_sapi', PHP_SAPI )
            ->assertHeader( 'Cache-Control', 'no-store, private' );

        $this->assertContains( $response->json( 'opcache' ), ['reset', 'failed', 'disabled'] );
    }

    /**
     * Test that an expired signature is rejected.
     *
     * @since 2.16.0
     */
    public function test_loopback_route_rejects_an_expired_signature(): void
    {
        $url = URL::temporarySignedRoute( WebOpcacheResetter::LOOPBACK_ROUTE, now()->addMinute(), [], false );

        $this->travel( 2 )->minutes();

        $this->get( $url )->assertForbidden();
    }

    /**
     * Test that the loopback route answers while the site is in maintenance
     * mode, since the updater requests it before taking the site back up.
     *
     * @since 2.16.0
     */
    public function test_loopback_route_answers_during_maintenance_mode(): void
    {
        $this->app->maintenanceMode()->activate( [] );

        try {
            $this->get( '/' )->assertServiceUnavailable();

            $this->getJson( URL::temporarySignedRoute( WebOpcacheResetter::LOOPBACK_ROUTE, now()->addMinute(), [], false ) )
                ->assertOk();
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }

    /**
     * Register the core provider so the loopback route is available. The
     * route is registered at boot, so loopback is enabled here even though
     * `setUp()` turns the mechanism off afterwards.
     *
     * @since 2.16.0
     *
     * @param  Application  $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders( $app ): array
    {
        return [CoreServiceProvider::class];
    }
}

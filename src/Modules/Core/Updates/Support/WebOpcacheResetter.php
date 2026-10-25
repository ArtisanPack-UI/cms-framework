<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * Web OPcache Resetter
 *
 * Resets the web server's OPcache from a process that does not share it: the
 * `update:perform` / `update:rollback` commands, or the queue worker behind
 * `dispatchUpdate()` (#429). Calling `opcache_reset()` there only clears the
 * CLI's own cache, which on a host with `opcache.validate_timestamps=0`
 * leaves PHP-FPM running a mix of pre- and post-update bytecode.
 *
 * Three mechanisms are tried in order, and the first that works wins:
 *
 * 1. `fastcgi`: run a one-off script through the FPM socket, the cachetool
 *    approach. Needs no web route and no working application boot.
 * 2. `loopback`: request a short-lived signed URL on the application's own
 *    base URL, which resets OPcache inside FPM.
 * 3. `command`: run the operator's `cms.updates.php_reload_command`, such as
 *    `sudo -S service php8.4-fpm reload`.
 *
 * OPcache's shared memory belongs to the FPM master, so reaching any pool it
 * runs resets the cache for all of them.
 *
 * @since 2.16.0
 */
class WebOpcacheResetter
{
    /**
     * Marker prefix the FastCGI script prints, so its output can be told
     * apart from a page some other script served.
     *
     * @since 2.16.0
     */
    public const FASTCGI_MARKER = 'cms-framework-opcache:';

    /**
     * Name of the signed loopback route.
     *
     * @since 2.16.0
     */
    public const LOOPBACK_ROUTE = 'cms.updates.opcache-reset';

    /**
     * Why each mechanism that was tried did not work, keyed by mechanism.
     *
     * @since 2.16.0
     *
     * @var array<string, string>
     */
    protected array $failures = [];

    /**
     * Build the resetter.
     *
     * @since 2.16.0
     *
     * @param  FastCgiClient  $fastCgi  Client used for the FastCGI mechanism.
     */
    public function __construct( protected FastCgiClient $fastCgi = new FastCgiClient )
    {
    }

    /**
     * Reset this process's OPcache.
     *
     * `opcache_reset()` raises a warning, which Laravel turns into an
     * exception, when `opcache.restrict_api` excludes the calling script. That
     * case is reported as a failed reset rather than allowed to throw.
     *
     * @since 2.16.0
     *
     * @param  bool  $underCli  Whether this process runs under a CLI SAPI.
     *
     * @return bool|null True when reset, false when the reset failed, null
     *                   when OPcache is not enabled for this SAPI.
     */
    public static function resetThisProcess( bool $underCli ): ?bool
    {
        if ( ! function_exists( 'opcache_reset' ) ) {
            return null;
        }

        $directive = $underCli ? 'opcache.enable_cli' : 'opcache.enable';

        if ( ! in_array( strtolower( trim( (string) ini_get( $directive ) ) ), ['1', 'on', 'true', 'yes'], true ) ) {
            return null;
        }

        try {
            return opcache_reset();
        } catch ( Throwable $e ) {
            return false;
        }
    }

    /**
     * Try each mechanism in turn.
     *
     * @since 2.16.0
     *
     * @return string|null The mechanism that reset the web server's OPcache
     *                     (`fastcgi`, `loopback` or `command`), or null when
     *                     none did. {@see failures()} says why.
     */
    public function reset(): ?string
    {
        $this->failures = [];

        $mechanisms = [
            'fastcgi'  => fn (): bool => $this->resetViaFastCgi(),
            'loopback' => fn (): bool => $this->resetViaLoopback(),
            'command'  => fn (): bool => $this->resetViaCommand(),
        ];

        foreach ( $mechanisms as $name => $attempt ) {
            try {
                if ( $attempt() ) {
                    return $name;
                }
            } catch ( Throwable $e ) {
                $this->failures[ $name ] = $e->getMessage();
            }
        }

        return null;
    }

    /**
     * Why each mechanism tried by the last `reset()` did not work.
     *
     * A mechanism that was skipped because it is disabled or not configured
     * is listed too, so the log shows the whole chain.
     *
     * @since 2.16.0
     *
     * @return array<string, string> Reasons keyed by mechanism.
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * Reset OPcache by running a one-off script through the FPM socket.
     *
     * The script is written under `storage/framework/` with an unguessable
     * name, outside the document root, and removed straight after. It is
     * standalone, so it runs even when the stale bytecode would stop the
     * application from booting. `opcache.restrict_api`, when set, must allow
     * its path.
     *
     * @since 2.16.0
     *
     * @return bool True when FPM reported the cache reset or not enabled.
     */
    protected function resetViaFastCgi(): bool
    {
        if ( false === config( 'cms.updates.opcache.fastcgi', true ) ) {
            $this->failures['fastcgi'] = 'disabled by cms.updates.opcache.fastcgi';

            return false;
        }

        $address = $this->fpmAddress();

        if ( null === $address ) {
            $this->failures['fastcgi'] = 'no PHP-FPM socket configured (cms.updates.opcache.fpm_socket) or found';

            return false;
        }

        // A run killed between writing and removing the script leaves it
        // behind. It is harmless, but should not accumulate.
        foreach ( glob( storage_path( 'framework/cms-opcache-reset-*.php' ) ) ?: [] as $stale ) {
            @unlink( $stale );
        }

        $script = storage_path( 'framework/cms-opcache-reset-' . bin2hex( random_bytes( 16 ) ) . '.php' );

        if ( false === @file_put_contents( $script, $this->fastCgiScript() ) ) {
            $this->failures['fastcgi'] = "could not write {$script}";

            return false;
        }

        try {
            $output = $this->fastCgi->run( $address, $this->fastCgiParams( $script ), $this->timeout() );
        } finally {
            @unlink( $script );
        }

        $result = $this->fastCgiResult( $output );

        if ( 'reset' === $result || 'disabled' === $result ) {
            return true;
        }

        $this->failures['fastcgi'] = 'failed' === $result
            ? "PHP-FPM at {$address} refused the reset (check opcache.restrict_api)"
            : "PHP-FPM at {$address} did not run the reset script";

        return false;
    }

    /**
     * Reset OPcache by requesting the signed loopback route.
     *
     * The URL is signed relative to the application, so a proxy that rewrites
     * the host or scheme, or an `http` to `https` redirect, does not
     * invalidate it. It expires after a minute. Redirects are followed only
     * on the same host, so a misconfigured base URL cannot hand the signed
     * URL to another site.
     *
     * @since 2.16.0
     *
     * @return bool True when the route reported the cache reset or not enabled.
     */
    protected function resetViaLoopback(): bool
    {
        if ( false === config( 'cms.updates.opcache.loopback', true ) ) {
            $this->failures['loopback'] = 'disabled by cms.updates.opcache.loopback';

            return false;
        }

        if ( ! Route::has( self::LOOPBACK_ROUTE ) ) {
            $this->failures['loopback'] = 'the ' . self::LOOPBACK_ROUTE . ' route is not registered';

            return false;
        }

        $baseUrl = rtrim( (string) ( config( 'cms.updates.opcache.loopback_url' ) ?: config( 'app.url' ) ), '/' );

        if ( '' === $baseUrl ) {
            $this->failures['loopback'] = 'no base URL (cms.updates.opcache.loopback_url or app.url)';

            return false;
        }

        $host     = strtolower( (string) parse_url( $baseUrl, PHP_URL_HOST ) );
        $path     = URL::temporarySignedRoute( self::LOOPBACK_ROUTE, now()->addMinute(), [], false );
        $response = Http::timeout( (int) ceil( $this->timeout() ) )
            ->acceptJson()
            ->withOptions( [
                'allow_redirects' => [
                    'max'         => 3,
                    'protocols'   => ['http', 'https'],
                    'on_redirect' => function ( RequestInterface $request, ResponseInterface $redirect, UriInterface $uri ) use ( $host ): void {
                        if ( strtolower( $uri->getHost() ) !== $host ) {
                            throw new RuntimeException( "refused to follow a redirect to another host ({$uri->getHost()})" );
                        }
                    },
                ],
            ] )
            ->get( $baseUrl . $path );

        $result = $response->successful() ? $response->json( 'opcache' ) : null;

        if ( 'reset' === $result || 'disabled' === $result ) {
            return true;
        }

        $this->failures['loopback'] = 'failed' === $result
            ? 'the web server refused the reset (check opcache.restrict_api)'
            : "{$baseUrl} answered HTTP {$response->status()}";

        return false;
    }

    /**
     * Run the operator's PHP reload command.
     *
     * The command itself is never put in the failure reason, which is
     * logged: with `sudo -S` it may carry a password on its standard input.
     *
     * @since 2.16.0
     *
     * @return bool True when the command exited zero.
     */
    protected function resetViaCommand(): bool
    {
        $command = trim( (string) config( 'cms.updates.php_reload_command', '' ) );

        if ( '' === $command ) {
            $this->failures['command'] = 'no cms.updates.php_reload_command configured';

            return false;
        }

        $result = Process::path( base_path() )->timeout( 60 )->run( $command );

        if ( $result->successful() ) {
            return true;
        }

        $error = trim( '' !== trim( $result->errorOutput() ) ? $result->errorOutput() : $result->output() );

        $this->failures['command'] = "cms.updates.php_reload_command exited {$result->exitCode()}" . ( '' !== $error ? ": {$error}" : '' );

        return false;
    }

    /**
     * The FPM address to use: the configured one, else the first candidate
     * socket that exists.
     *
     * @since 2.16.0
     *
     * @return string|null The address, or null when none is known.
     */
    protected function fpmAddress(): ?string
    {
        $configured = trim( (string) config( 'cms.updates.opcache.fpm_socket', '' ) );

        if ( '' !== $configured ) {
            return $configured;
        }

        foreach ( $this->candidateSockets() as $socket ) {
            if ( file_exists( $socket ) ) {
                return $socket;
            }
        }

        return null;
    }

    /**
     * Common FPM socket paths for this PHP version: Forge's per-user pool for
     * an isolated site, the Debian/Ubuntu default, and the RHEL default.
     *
     * @since 2.16.0
     *
     * @return array<int, string> Paths to try, in order.
     */
    protected function candidateSockets(): array
    {
        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $paths   = [];
        $user    = $this->processUser();

        if ( null !== $user ) {
            $paths[] = "/run/php/php{$version}-fpm-{$user}.sock";
            $paths[] = "/var/run/php/php{$version}-fpm-{$user}.sock";
        }

        return array_merge( $paths, [
            "/run/php/php{$version}-fpm.sock",
            "/var/run/php/php{$version}-fpm.sock",
            '/run/php-fpm/www.sock',
            '/var/run/php-fpm/www.sock',
        ] );
    }

    /**
     * The name of the user this process runs as.
     *
     * @since 2.16.0
     *
     * @return string|null The user name, or null when it cannot be read.
     */
    protected function processUser(): ?string
    {
        if ( ! function_exists( 'posix_geteuid' ) || ! function_exists( 'posix_getpwuid' ) ) {
            return null;
        }

        $user = posix_getpwuid( posix_geteuid() );

        return is_array( $user ) && is_string( $user['name'] ?? null ) && '' !== $user['name'] ? $user['name'] : null;
    }

    /**
     * Connect and read timeout for the FastCGI and loopback requests.
     *
     * @since 2.16.0
     *
     * @return float Seconds.
     */
    protected function timeout(): float
    {
        $timeout = (float) config( 'cms.updates.opcache.timeout', 5 );

        return $timeout > 0 ? $timeout : 5.0;
    }

    /**
     * The standalone script FPM runs. It prints the marker followed by
     * `reset`, `failed` or `disabled`.
     *
     * @since 2.16.0
     *
     * @return string PHP source.
     */
    protected function fastCgiScript(): string
    {
        $marker = var_export( self::FASTCGI_MARKER, true );

        return <<<PHP
            <?php
            \$enabled = function_exists( 'opcache_reset' )
                && in_array( strtolower( trim( (string) ini_get( 'opcache.enable' ) ) ), ['1', 'on', 'true', 'yes'], true );
            echo {$marker} . ( \$enabled ? ( @opcache_reset() ? 'reset' : 'failed' ) : 'disabled' );

            PHP;
    }

    /**
     * FastCGI params for running the script.
     *
     * @since 2.16.0
     *
     * @param  string  $script  Absolute path to the script.
     *
     * @return array<string, string> The params.
     */
    protected function fastCgiParams( string $script ): array
    {
        return [
            'GATEWAY_INTERFACE' => 'FastCGI/1.0',
            'REQUEST_METHOD'    => 'GET',
            'SCRIPT_FILENAME'   => $script,
            'SCRIPT_NAME'       => '/' . basename( $script ),
            'REQUEST_URI'       => '/' . basename( $script ),
            'DOCUMENT_ROOT'     => dirname( $script ),
            'QUERY_STRING'      => '',
            'CONTENT_LENGTH'    => '0',
            'SERVER_SOFTWARE'   => 'cms-framework',
            'SERVER_PROTOCOL'   => 'HTTP/1.1',
            'SERVER_NAME'       => 'localhost',
            'SERVER_PORT'       => '80',
            'REMOTE_ADDR'       => '127.0.0.1',
        ];
    }

    /**
     * Pull the script's result out of FPM's stdout.
     *
     * @since 2.16.0
     *
     * @param  string  $output  Stdout, CGI headers included.
     *
     * @return string|null `reset`, `failed` or `disabled`, or null when the
     *                     marker is missing.
     */
    protected function fastCgiResult( string $output ): ?string
    {
        if ( 1 !== preg_match( '/' . preg_quote( self::FASTCGI_MARKER, '/' ) . '(reset|failed|disabled)/', $output, $matches ) ) {
            return null;
        }

        return $matches[1];
    }
}

<?php

declare( strict_types=1 );

/**
 * ResetsWebOpcache Trait
 *
 * Makes files rewritten on disk visible to the web server, from any SAPI.
 *
 * @since      2.18.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;

/**
 * Trait for resetting the OPcache the web server runs from.
 *
 * Extracted from ApplicationUpdateManager so PluginManager can reset PHP-FPM's
 * OPcache after a plugin's Composer install the same way the self-updater does
 * after an update (#438).
 *
 * @since 2.18.0
 */
trait ResetsWebOpcache
{
    /**
     * Why each web OPcache reset mechanism failed on the last attempt, keyed
     * by mechanism, for the warning logged when none worked.
     *
     * @since 2.16.0
     *
     * @var array<string, string>
     */
    protected array $webOpcacheResetFailures = [];

    /**
     * Whether the last attempt left the web server's OPcache alone because
     * `cms.updates.opcache.web_reset` turned the reset off (#456).
     *
     * @since 2.18.0
     */
    protected bool $webOpcacheResetSkipped = false;

    /**
     * Reset the OPcache the web server runs from.
     *
     * With `opcache.validate_timestamps=0` (Laravel Forge's OPcache
     * optimisation, and common in production) PHP-FPM never re-reads a file
     * it has already compiled. Called from inside a PHP-FPM request,
     * `opcache_reset()` clears the pool's shared cache. From the CLI or a
     * queue worker it only clears this process's own cache, so
     * {@see resetWebOpcache()} reaches the web server another way.
     *
     * Under the CLI the web server is left alone when
     * {@see shouldResetWebOpcache()} says so, such as in a test suite. Null
     * is returned then too, and {@see webOpcacheResetWasSkipped()} tells the
     * two apart, so a skip is not reported as PHP-FPM needing a reload.
     *
     * @since 2.18.0
     *
     * @return string|null `web-request`, or under the CLI the mechanism that
     *                     worked, or null when no reset reached the web server.
     */
    protected function resetOpcacheForWebServer(): ?string
    {
        $this->webOpcacheResetSkipped  = false;
        $this->webOpcacheResetFailures = [];

        $reset = $this->attemptOpcacheReset();

        if ( $this->runningUnderCli() ) {
            if ( ! $this->shouldResetWebOpcache() ) {
                $this->webOpcacheResetSkipped = true;

                return null;
            }

            return $this->resetWebOpcache();
        }

        return false === $reset ? null : 'web-request';
    }

    /**
     * Whether the last {@see resetOpcacheForWebServer()} left the web
     * server's OPcache alone on purpose rather than failing to reset it.
     *
     * @since 2.18.0
     *
     * @return bool True when `cms.updates.opcache.web_reset` skipped it.
     */
    protected function webOpcacheResetWasSkipped(): bool
    {
        return $this->webOpcacheResetSkipped;
    }

    /**
     * Whether a CLI run should reach for the web server's OPcache at all.
     *
     * Reaching it means a FastCGI request to a real PHP-FPM socket, an HTTP
     * request to the loopback route, or the operator's reload command. A host
     * app's or plugin's test suite activating a plugin must do none of those
     * (#456), so `cms.updates.opcache.web_reset` left unset turns the reset
     * off while the application runs its unit tests, and on otherwise.
     * Setting it to true or false overrides that either way.
     *
     * @since 2.18.0
     *
     * @return bool True when the web server's OPcache should be reset.
     */
    protected function shouldResetWebOpcache(): bool
    {
        $setting = config( 'cms.updates.opcache.web_reset' );

        if ( null === $setting || '' === $setting ) {
            return ! app()->runningUnitTests();
        }

        if ( is_bool( $setting ) ) {
            return $setting;
        }

        return ! in_array( strtolower( trim( (string) $setting ) ), ['0', 'false', 'off', 'no'], true );
    }

    /**
     * Reset this process's OPcache.
     *
     * @since 2.15.6
     *
     * @return bool|null True when reset, false when the reset failed, null
     *                   when OPcache is not enabled for this SAPI.
     */
    protected function attemptOpcacheReset(): ?bool
    {
        return WebOpcacheResetter::resetThisProcess( $this->runningUnderCli() );
    }

    /**
     * Reset the web server's OPcache from a CLI or queued run (#429).
     *
     * Tries FastCGI to the FPM socket, then the signed loopback route, then
     * the operator's `cms.updates.php_reload_command`. Why each one failed is
     * kept in {@see $webOpcacheResetFailures} for the warning.
     *
     * @since 2.16.0
     *
     * @return string|null The mechanism that worked, or null when none did.
     */
    protected function resetWebOpcache(): ?string
    {
        $resetter = app( WebOpcacheResetter::class );
        $method   = $resetter->reset();

        $this->webOpcacheResetFailures = array_map(
            fn ( string $reason ): string => $this->sanitizeForLog( $reason ),
            $resetter->failures(),
        );

        return $method;
    }

    /**
     * Whether this process runs under a CLI SAPI, whose OPcache is not shared
     * with the web server.
     *
     * @since 2.15.6
     *
     * @return bool True for the CLI and phpdbg SAPIs.
     */
    protected function runningUnderCli(): bool
    {
        return in_array( $this->phpSapi(), ['cli', 'phpdbg'], true );
    }

    /**
     * The SAPI this process runs under.
     *
     * @since 2.15.6
     *
     * @return string The PHP SAPI name.
     */
    protected function phpSapi(): string
    {
        return PHP_SAPI;
    }

    /**
     * Strip control characters from an attacker-controlled value before it
     * reaches a log record.
     *
     * Entry names come from the archive. They already go into the log
     * *context* array rather than being interpolated into the message, which
     * is the important half — but a name carrying newlines or terminal escape
     * sequences can still corrupt a plain-text log or a console tailing it.
     *
     * @since 2.7.1
     *
     * @param  string  $value  Untrusted value.
     *
     * @return string Value with control characters removed and length bounded.
     */
    protected function sanitizeForLog( string $value ): string
    {
        $clean = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );

        if ( ! is_string( $clean ) ) {
            $clean = preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) ?? '';
        }

        return mb_strimwidth( $clean, 0, 512, '…' );
    }
}

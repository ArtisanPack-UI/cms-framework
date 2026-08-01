<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support;

/**
 * A stream wrapper whose `fwrite()` always reports fewer bytes written than it
 * was given.
 *
 * A genuinely full disk is not reproducible in a test suite, but the condition
 * the updater has to survive — `fwrite()` returning a short count with no
 * exception raised — is exactly what this reproduces. Registered and
 * unregistered per test so it never leaks into unrelated code.
 *
 * @since 2.7.1
 */
class ShortWriteStreamWrapper
{
    /**
     * The protocol this wrapper is registered under.
     *
     * @since 2.7.1
     */
    public const PROTOCOL = 'cmsfw-shortwrite';

    /**
     * Stream context, assigned by PHP.
     *
     * @since 2.7.1
     *
     * @var resource|null
     */
    public $context;

    /**
     * Register the wrapper, replacing any previous registration.
     *
     * @since 2.7.1
     */
    public static function register(): void
    {
        if ( in_array( self::PROTOCOL, stream_get_wrappers(), true ) ) {
            stream_wrapper_unregister( self::PROTOCOL );
        }

        stream_wrapper_register( self::PROTOCOL, self::class );
    }

    /**
     * Unregister the wrapper.
     *
     * @since 2.7.1
     */
    public static function unregister(): void
    {
        if ( in_array( self::PROTOCOL, stream_get_wrappers(), true ) ) {
            stream_wrapper_unregister( self::PROTOCOL );
        }
    }

    /**
     * Open the stream. Always succeeds.
     *
     * @since 2.7.1
     *
     * @param  string  $path  Requested path.
     * @param  string  $mode  Requested mode.
     * @param  int  $options  Stream options.
     * @param  string|null  $openedPath  Out-param for the opened path.
     */
    public function stream_open( string $path, string $mode, int $options, ?string &$openedPath ): bool
    {
        return true;
    }

    /**
     * Accept the write but report one byte fewer than was supplied, which is
     * how a filesystem reports running out of space mid-write.
     *
     * @since 2.7.1
     *
     * @param  string  $data  Data offered for writing.
     *
     * @return int Bytes "written".
     */
    public function stream_write( string $data ): int
    {
        return max( 0, strlen( $data ) - 1 );
    }

    /**
     * Never at end of file.
     *
     * @since 2.7.1
     */
    public function stream_eof(): bool
    {
        return false;
    }

    /**
     * Close the stream.
     *
     * @since 2.7.1
     */
    public function stream_close(): void
    {
    }

    /**
     * Report a minimal stat block so `fclose()` and friends behave.
     *
     * @since 2.7.1
     *
     * @return array<string,int>
     */
    public function stream_stat(): array
    {
        return [];
    }
}

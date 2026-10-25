<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

use RuntimeException;

/**
 * FastCGI Client
 *
 * The smallest FastCGI responder client that can ask PHP-FPM to run one
 * script and return its output, the same approach cachetool takes (#429). It
 * sends a single `GET` request on a fresh connection and reads until FPM ends
 * the request. Nothing else of the protocol (multiplexing, keep-alive, request
 * bodies) is needed to run a one-off script.
 *
 * @since 2.16.0
 */
class FastCgiClient
{
    /**
     * FastCGI protocol version.
     *
     * @since 2.16.0
     */
    protected const VERSION = 1;

    /**
     * Record types used by a responder request.
     *
     * @since 2.16.0
     */
    protected const TYPE_BEGIN_REQUEST = 1;

    protected const TYPE_END_REQUEST = 3;

    protected const TYPE_PARAMS = 4;

    protected const TYPE_STDIN = 5;

    protected const TYPE_STDOUT = 6;

    protected const TYPE_STDERR = 7;

    /**
     * The responder role.
     *
     * @since 2.16.0
     */
    protected const ROLE_RESPONDER = 1;

    /**
     * Request id used for the single request on each connection.
     *
     * @since 2.16.0
     */
    protected const REQUEST_ID = 1;

    /**
     * Most output accepted before giving up, so a socket that is not FPM
     * cannot make the client buffer without bound. The reset script prints a
     * few dozen bytes.
     *
     * @since 2.16.0
     */
    protected const MAX_OUTPUT_BYTES = 1048576;

    /**
     * Normalise an FPM address to a stream socket address.
     *
     * Accepts `unix:///path.sock`, a bare `/path.sock`, `tcp://host:port`, or a
     * bare `host:port`.
     *
     * @since 2.16.0
     *
     * @param  string  $address  Configured or discovered FPM address.
     *
     * @return string Address for `stream_socket_client()`.
     */
    public static function normalizeAddress( string $address ): string
    {
        $address = trim( $address );

        if ( str_starts_with( $address, 'unix://' ) || str_starts_with( $address, 'tcp://' ) ) {
            return $address;
        }

        if ( str_starts_with( $address, '/' ) ) {
            return 'unix://' . $address;
        }

        return 'tcp://' . $address;
    }

    /**
     * Run a script through PHP-FPM and return its stdout.
     *
     * @since 2.16.0
     *
     * @param  string  $address  FPM address, in any form `normalizeAddress()` accepts.
     * @param  array<string, string>  $params  FastCGI params (`SCRIPT_FILENAME` and friends).
     * @param  float  $timeout  Connect and read timeout, in seconds.
     *
     * @throws RuntimeException When FPM cannot be reached or the response is malformed.
     *
     * @return string The script's stdout, CGI headers included.
     */
    public function run( string $address, array $params, float $timeout ): string
    {
        $stream = $this->connect( self::normalizeAddress( $address ), $timeout );

        try {
            if ( false === @fwrite( $stream, $this->buildRequest( $params ) ) ) {
                throw new RuntimeException( 'could not write the FastCGI request' );
            }

            return $this->readResponse( $stream );
        } finally {
            @fclose( $stream );
        }
    }

    /**
     * Encode a complete responder request: begin, params, empty stdin.
     *
     * @since 2.16.0
     *
     * @param  array<string, string>  $params  FastCGI params.
     *
     * @return string The bytes to send.
     */
    public function buildRequest( array $params ): string
    {
        $pairs = '';

        foreach ( $params as $name => $value ) {
            $pairs .= $this->encodeLength( strlen( (string) $name ) )
                . $this->encodeLength( strlen( $value ) )
                . $name
                . $value;
        }

        $request = $this->record( self::TYPE_BEGIN_REQUEST, pack( 'nCx5', self::ROLE_RESPONDER, 0 ) );

        // A record carries at most 65535 bytes, so long params are split.
        foreach ( str_split( $pairs, 65535 ) as $chunk ) {
            if ( '' !== $chunk ) {
                $request .= $this->record( self::TYPE_PARAMS, $chunk );
            }
        }

        $request .= $this->record( self::TYPE_PARAMS, '' );
        $request .= $this->record( self::TYPE_STDIN, '' );

        return $request;
    }

    /**
     * Read records until FPM ends the request, collecting stdout.
     *
     * @since 2.16.0
     *
     * @param  resource  $stream  Connected stream.
     *
     * @throws RuntimeException When the connection closes or times out first.
     *
     * @return string The collected stdout.
     */
    public function readResponse( $stream ): string
    {
        $stdout = '';
        $stderr = '';

        while ( true ) {
            $header = $this->readExactly( $stream, 8 );

            /** @var array{version: int, type: int, requestId: int, length: int, padding: int} $record */
            $record  = unpack( 'Cversion/Ctype/nrequestId/nlength/Cpadding/x', $header );
            $content = $record['length'] > 0 ? $this->readExactly( $stream, $record['length'] ) : '';

            if ( $record['padding'] > 0 ) {
                $this->readExactly( $stream, $record['padding'] );
            }

            if ( strlen( $stdout ) + strlen( $stderr ) + strlen( $content ) > self::MAX_OUTPUT_BYTES ) {
                throw new RuntimeException( 'PHP-FPM sent more output than expected' );
            }

            if ( self::TYPE_STDOUT === $record['type'] ) {
                $stdout .= $content;
            } elseif ( self::TYPE_STDERR === $record['type'] ) {
                $stderr .= $content;
            } elseif ( self::TYPE_END_REQUEST === $record['type'] ) {
                $this->assertRequestCompleted( $content );

                break;
            }
        }

        if ( '' === $stdout && '' !== $stderr ) {
            throw new RuntimeException( 'PHP-FPM returned an error: ' . trim( $stderr ) );
        }

        return $stdout;
    }

    /**
     * Reject an end-request record that reports failure: a nonzero exit
     * status, or a protocol status other than `FCGI_REQUEST_COMPLETE`.
     *
     * @since 2.16.0
     *
     * @param  string  $content  The end-request record's content.
     *
     * @throws RuntimeException When the record is malformed or reports failure.
     */
    protected function assertRequestCompleted( string $content ): void
    {
        if ( 8 !== strlen( $content ) ) {
            throw new RuntimeException( 'PHP-FPM sent a malformed end-request record' );
        }

        /** @var array{appStatus: int, protocolStatus: int} $status */
        $status = unpack( 'NappStatus/CprotocolStatus', $content );

        if ( 0 !== $status['appStatus'] || 0 !== $status['protocolStatus'] ) {
            throw new RuntimeException( "PHP-FPM ended the request unsuccessfully (app status {$status['appStatus']}, protocol status {$status['protocolStatus']})" );
        }
    }

    /**
     * Open a connection to FPM.
     *
     * @since 2.16.0
     *
     * @param  string  $address  Normalised stream socket address.
     * @param  float  $timeout  Connect and read timeout, in seconds.
     *
     * @throws RuntimeException When the connection fails.
     *
     * @return resource The connected stream.
     */
    protected function connect( string $address, float $timeout )
    {
        if ( str_starts_with( $address, 'unix://' ) && ! file_exists( substr( $address, 7 ) ) ) {
            throw new RuntimeException( "could not connect to {$address}: no such socket" );
        }

        $errorCode    = 0;
        $errorMessage = '';
        $stream       = @stream_socket_client( $address, $errorCode, $errorMessage, $timeout );

        if ( false === $stream ) {
            throw new RuntimeException( "could not connect to {$address}: " . ( '' !== $errorMessage ? $errorMessage : "error {$errorCode}" ) );
        }

        $seconds = (int) floor( $timeout );
        stream_set_timeout( $stream, $seconds, (int) ( ( $timeout - $seconds ) * 1000000 ) );

        return $stream;
    }

    /**
     * Encode one FastCGI record.
     *
     * @since 2.16.0
     *
     * @param  int  $type  Record type.
     * @param  string  $content  Record content, at most 65535 bytes.
     *
     * @return string The encoded record.
     */
    protected function record( int $type, string $content ): string
    {
        $length  = strlen( $content );
        $padding = ( 8 - ( $length % 8 ) ) % 8;

        return pack( 'CCnnCx', self::VERSION, $type, self::REQUEST_ID, $length, $padding )
            . $content
            . str_repeat( "\0", $padding );
    }

    /**
     * Encode a name-value pair length: one byte below 128, else four with the
     * high bit set.
     *
     * @since 2.16.0
     *
     * @param  int  $length  Length to encode.
     *
     * @return string The encoded length.
     */
    protected function encodeLength( int $length ): string
    {
        return $length < 128 ? chr( $length ) : pack( 'N', $length | 0x80000000 );
    }

    /**
     * Read exactly the given number of bytes.
     *
     * @since 2.16.0
     *
     * @param  resource  $stream  Connected stream.
     * @param  int  $length  Bytes to read.
     *
     * @throws RuntimeException When the stream ends or times out first.
     *
     * @return string The bytes read.
     */
    protected function readExactly( $stream, int $length ): string
    {
        $buffer = '';

        while ( strlen( $buffer ) < $length ) {
            $chunk = @fread( $stream, $length - strlen( $buffer ) );

            if ( false === $chunk || '' === $chunk ) {
                $timedOut = (bool) ( stream_get_meta_data( $stream )['timed_out'] ?? false );

                throw new RuntimeException( $timedOut ? 'timed out waiting for PHP-FPM' : 'PHP-FPM closed the connection early' );
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}

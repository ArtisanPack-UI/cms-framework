<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\FastCgiClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * FastCGI Client Tests
 *
 * Covers the request encoding and response decoding the self-updater uses to
 * reset PHP-FPM's OPcache from the CLI (#429).
 *
 * @since 2.16.0
 */
class FastCgiClientTest extends TestCase
{
    /**
     * Test that FPM addresses are normalised to stream socket addresses.
     *
     * @since 2.16.0
     *
     * @param  string  $address  Configured address.
     * @param  string  $expected  Expected stream address.
     */
    #[DataProvider( 'addresses' )]
    public function test_addresses_are_normalised( string $address, string $expected ): void
    {
        $this->assertSame( $expected, FastCgiClient::normalizeAddress( $address ) );
    }

    /**
     * Addresses in every accepted form.
     *
     * @since 2.16.0
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function addresses(): array
    {
        return [
            'bare socket path'  => ['/run/php/php8.4-fpm.sock', 'unix:///run/php/php8.4-fpm.sock'],
            'unix scheme'       => ['unix:///run/php/php8.4-fpm.sock', 'unix:///run/php/php8.4-fpm.sock'],
            'bare host:port'    => ['127.0.0.1:9000', 'tcp://127.0.0.1:9000'],
            'tcp scheme'        => ['tcp://127.0.0.1:9000', 'tcp://127.0.0.1:9000'],
            'surrounding space' => [' /tmp/fpm.sock ', 'unix:///tmp/fpm.sock'],
        ];
    }

    /**
     * Test that a request is a begin record, params, an empty params record
     * and an empty stdin record, each padded to eight bytes.
     *
     * @since 2.16.0
     */
    public function test_request_is_encoded_as_responder_records(): void
    {
        $longValue = str_repeat( 'a', 200 );
        $records   = $this->decodeRecords( ( new FastCgiClient )->buildRequest( [
            'SCRIPT_FILENAME' => '/tmp/x.php',
            'LONG'            => $longValue,
        ] ) );

        $this->assertSame( [1, 4, 4, 5], array_column( $records, 'type' ) );
        $this->assertSame( pack( 'nCx5', 1, 0 ), $records[0]['content'], 'Responder role, no keep-alive.' );
        $this->assertSame( '', $records[2]['content'] );
        $this->assertSame( '', $records[3]['content'] );

        $params = $records[1]['content'];
        $this->assertSame(
            chr( 15 ) . chr( 10 ) . 'SCRIPT_FILENAME/tmp/x.php'
                . chr( 4 ) . pack( 'N', 200 | 0x80000000 ) . 'LONG' . $longValue,
            $params,
            'Lengths of 128 or more use the four-byte form.',
        );
    }

    /**
     * Test that stdout records are collected up to the end-request record,
     * skipping padding and stderr.
     *
     * @since 2.16.0
     */
    public function test_response_collects_stdout_until_the_request_ends(): void
    {
        $stream = $this->streamWith(
            $this->record( 6, "Content-type: text/html\r\n\r\n" )
                . $this->record( 7, 'PHP Notice: something' )
                . $this->record( 6, 'cms-framework-opcache:reset' )
                . $this->record( 6, '' )
                . $this->record( 3, pack( 'NCx3', 0, 0 ) )
                . $this->record( 6, 'after the end' ),
        );

        $this->assertSame(
            "Content-type: text/html\r\n\r\ncms-framework-opcache:reset",
            ( new FastCgiClient )->readResponse( $stream ),
        );
    }

    /**
     * Test that a response with only stderr, such as FPM's "Primary script
     * unknown", is an error.
     *
     * @since 2.16.0
     */
    public function test_stderr_only_response_throws(): void
    {
        $stream = $this->streamWith( $this->record( 7, 'Primary script unknown' ) . $this->record( 3, pack( 'NCx3', 0, 0 ) ) );

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'Primary script unknown' );

        ( new FastCgiClient )->readResponse( $stream );
    }

    /**
     * Test that a listener that keeps sending output is cut off.
     *
     * @since 2.16.0
     */
    public function test_oversized_response_throws(): void
    {
        $stream = $this->streamWith( str_repeat( $this->record( 6, str_repeat( 'x', 65535 ) ), 17 ) );

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'more output than expected' );

        ( new FastCgiClient )->readResponse( $stream );
    }

    /**
     * Test that a connection closed before the request ends is an error.
     *
     * @since 2.16.0
     */
    public function test_truncated_response_throws(): void
    {
        $stream = $this->streamWith( $this->record( 6, 'partial' ) );

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'closed the connection early' );

        ( new FastCgiClient )->readResponse( $stream );
    }

    /**
     * Test that an unreachable socket is an error naming the address.
     *
     * @since 2.16.0
     */
    public function test_unreachable_socket_throws(): void
    {
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'could not connect to unix:///nonexistent/fpm.sock' );

        ( new FastCgiClient )->run( '/nonexistent/fpm.sock', [], 1.0 );
    }

    /**
     * Encode one record the way FPM would.
     *
     * @since 2.16.0
     *
     * @param  int  $type  Record type.
     * @param  string  $content  Record content.
     *
     * @return string The encoded record.
     */
    protected function record( int $type, string $content ): string
    {
        $padding = ( 8 - ( strlen( $content ) % 8 ) ) % 8;

        return pack( 'CCnnCx', 1, $type, 1, strlen( $content ), $padding ) . $content . str_repeat( "\0", $padding );
    }

    /**
     * A readable stream holding the given bytes.
     *
     * @since 2.16.0
     *
     * @param  string  $bytes  Stream contents.
     *
     * @return resource The stream, rewound.
     */
    protected function streamWith( string $bytes )
    {
        $stream = fopen( 'php://memory', 'r+' );
        fwrite( $stream, $bytes );
        rewind( $stream );

        return $stream;
    }

    /**
     * Split encoded bytes back into records.
     *
     * @since 2.16.0
     *
     * @param  string  $bytes  Encoded records.
     *
     * @return array<int, array{type: int, content: string}> The records.
     */
    protected function decodeRecords( string $bytes ): array
    {
        $records = [];
        $offset  = 0;

        while ( $offset < strlen( $bytes ) ) {
            $header = unpack( 'Cversion/Ctype/nrequestId/nlength/Cpadding/x', substr( $bytes, $offset, 8 ) );

            $this->assertSame( 1, $header['version'] );
            $this->assertSame( 1, $header['requestId'] );
            $this->assertSame( 0, ( $header['length'] + $header['padding'] ) % 8 );

            $records[] = [
                'type'    => $header['type'],
                'content' => substr( $bytes, $offset + 8, $header['length'] ),
            ];

            $offset += 8 + $header['length'] + $header['padding'];
        }

        return $records;
    }
}

<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use Orchestra\Testbench\TestCase;

/**
 * UpdateException factory tests.
 *
 * @since 2.5.4
 */
class UpdateExceptionTest extends TestCase
{
    /**
     * Regression for #233: `composerBinaryNotFound()` renders per-candidate
     * `is_file()` / `is_executable()` results when supplied with diagnostic
     * entries, so operators can see whether a path was missing, unstat-able,
     * or present-but-not-executable without cross-referencing the log.
     *
     * @since 2.5.4
     */
    public function test_composer_binary_not_found_renders_diagnostic_entries(): void
    {
        $exception = UpdateException::composerBinaryNotFound( [
            ['path' => '/usr/local/bin/composer', 'is_file' => false, 'is_executable' => false],
            ['path' => '/opt/homebrew/bin/composer', 'is_file' => true, 'is_executable' => false],
            ['path' => '/home/x/.composer/vendor/bin/composer', 'is_file' => true, 'is_executable' => true],
        ] );

        $message = $exception->getMessage();

        $this->assertStringContainsString( '/usr/local/bin/composer (is_file=false, is_executable=n/a)', $message );
        $this->assertStringContainsString( '/opt/homebrew/bin/composer (is_file=true, is_executable=false)', $message );
        $this->assertStringContainsString( '/home/x/.composer/vendor/bin/composer (is_file=true, is_executable=true)', $message );
        $this->assertStringContainsString( 'PHP process', $message );
        $this->assertStringContainsString( 'COMPOSER_BINARY', $message );
    }

    /**
     * Regression for #233: the legacy 2.5.3 signature — a flat list of
     * absolute paths — must continue to render as before so downstream
     * consumers that predate the diagnostic upgrade keep working.
     *
     * @since 2.5.4
     */
    public function test_composer_binary_not_found_still_supports_legacy_string_paths(): void
    {
        $exception = UpdateException::composerBinaryNotFound( [
            '/usr/local/bin/composer',
            '/opt/homebrew/bin/composer',
        ] );

        $message = $exception->getMessage();

        $this->assertStringContainsString( 'Searched: /usr/local/bin/composer, /opt/homebrew/bin/composer.', $message );
        $this->assertStringContainsString( 'COMPOSER_BINARY', $message );
    }

    /**
     * Empty candidate list still produces a sensible message rather than
     * "Searched: ." — guards against a caller passing `[]` after all
     * candidates were filtered out.
     *
     * @since 2.5.4
     */
    public function test_composer_binary_not_found_handles_empty_candidate_list(): void
    {
        $exception = UpdateException::composerBinaryNotFound( [] );

        $this->assertStringContainsString( 'Searched: (none).', $exception->getMessage() );
    }

    /**
     * A mixed array (diagnostic entry at index 0, plain string later) must
     * not raise a `TypeError` from the diagnostic-branch closure signature.
     * Guards against a caller half-migrating to the new shape.
     *
     * @since 2.5.4
     */
    public function test_composer_binary_not_found_tolerates_mixed_input_shapes(): void
    {
        $exception = UpdateException::composerBinaryNotFound( [
            ['path' => '/opt/homebrew/bin/composer', 'is_file' => true, 'is_executable' => false],
            '/usr/local/bin/composer',
        ] );

        $message = $exception->getMessage();

        $this->assertStringContainsString( '/opt/homebrew/bin/composer', $message );
        $this->assertStringContainsString( '/usr/local/bin/composer', $message );
    }
}

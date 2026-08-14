<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\GitHubUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\MetadataClient;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Release Workflow Tests
 *
 * `GitHubUpdateSource` can only advertise a `sha256` if the release it reads
 * carries an archive asset and a sidecar named for that archive. Those two
 * halves live in different files and nothing but this test holds them
 * together: rename the archive in `release.yml` without renaming the sidecar
 * and every GitHub-sourced self-update starts failing closed, silently, at the
 * next tag.
 *
 * @since 2.8.0
 */
class ReleaseWorkflowTest extends TestCase
{
    /**
     * Version substituted for the workflow's `${VERSION}` placeholder.
     *
     * @since 2.8.0
     */
    protected const VERSION = '2.8.0';

    /**
     * Parsed workflow, memoized across the several lookups each test makes.
     *
     * @since 2.8.0
     *
     * @var array<string, mixed>|null
     */
    protected ?array $parsedWorkflow = null;

    /**
     * @since 2.8.0
     */
    protected function setUp(): void
    {
        parent::setUp();

        MetadataClient::useHttpFacadeBridge();
    }

    /**
     * @since 2.8.0
     */
    protected function tearDown(): void
    {
        MetadataClient::reset();

        parent::tearDown();
    }

    /**
     * Test the release job builds an archive and a sidecar named for it.
     *
     * @since 2.8.0
     */
    public function test_release_job_builds_an_archive_and_a_matching_sidecar(): void
    {
        $archive = $this->archiveName();
        $sidecar = $this->sidecarName();

        $this->assertSame( 'cms-framework-' . self::VERSION . '.zip', $archive );
        $this->assertSame( $archive . '.sha256', $sidecar );

        // The digest is only worth publishing if the bytes it describes stay
        // put. `git archive` output is stable for a given tree; GitHub's
        // on-demand zipball is not, which is why the archive is built here
        // rather than a sidecar being attached to the generated one.
        $this->assertStringContainsString( 'git archive', $this->archiveStepScript() );
    }

    /**
     * Test both the archive and its sidecar are attached to the release.
     *
     * Without `files:` the release carries only GitHub's auto-generated
     * zipball, which has no asset name for a sidecar to be correlated against.
     *
     * @since 2.8.0
     */
    public function test_release_job_attaches_both_files_to_the_release(): void
    {
        $step = $this->releaseStep();

        $this->assertArrayHasKey( 'files', $step['with'] ?? [], 'The release action must be given a files: input.' );

        $files = collect( preg_split( '/\R/', (string) $step['with']['files'] ) )
            ->map( fn ( string $line ): string => $this->resolveArchivePlaceholders( trim( $line ) ) )
            ->filter()
            ->values()
            ->all();

        $this->assertSame( [ $this->archiveName(), $this->sidecarName() ], $files );
    }

    /**
     * Test the release job refuses to publish an archive without composer.lock.
     *
     * The updater installs from the release's lock rather than re-resolving,
     * so an archive missing it degrades to a resolution the release never
     * tested.
     *
     * @since 2.8.0
     */
    public function test_release_job_guards_that_the_archive_carries_composer_lock(): void
    {
        $run = $this->archiveStepScript();

        $this->assertStringContainsString( 'composer.lock', $run );
        $this->assertStringContainsString( 'exit 1', $run );
    }

    /**
     * Test a release published by this workflow yields a usable checksum.
     *
     * This is the end the issue cares about: with the shipped defaults
     * ( `verify_checksum = true`, `allow_unverified_updates = false` ) an
     * update whose `sha256` is null is refused outright.
     *
     * @since 2.8.0
     */
    public function test_a_release_shaped_like_this_workflow_advertises_a_checksum(): void
    {
        $archive = $this->archiveName();
        $sidecar = $this->sidecarName();
        $digest  = str_repeat( 'ab', 32 );
        $baseUrl = 'https://github.com/user/repo/releases/download/v' . self::VERSION;

        Http::fake( [
            'api.github.com/repos/user/repo/releases' => Http::response( [
                [
                    'tag_name'     => 'v' . self::VERSION,
                    'prerelease'   => false,
                    'body'         => 'Release notes without any checksum marker.',
                    'published_at' => '2026-01-15T10:00:00Z',
                    'html_url'     => 'https://github.com/user/repo/releases/tag/v' . self::VERSION,
                    'id'           => 271,
                    'assets'       => [
                        [
                            'name'                 => $archive,
                            'browser_download_url' => $baseUrl . '/' . $archive,
                        ],
                        [
                            'name'                 => $sidecar,
                            'browser_download_url' => $baseUrl . '/' . $sidecar,
                        ],
                    ],
                    'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v' . self::VERSION,
                ],
            ], 200 ),
            // Mirrors `sha256sum` output: `<digest>  <filename>`.
            $baseUrl . '/' . $sidecar => Http::response( $digest . '  ' . $archive, 200 ),
        ] );

        $source     = new GitHubUpdateSource( 'https://github.com/user/repo', '2.7.2' );
        $updateInfo = $source->checkForUpdate();

        $this->assertSame( $baseUrl . '/' . $archive, $updateInfo->downloadUrl );
        $this->assertSame( $digest, $updateInfo->sha256 );
    }

    /**
     * Load and parse the release workflow.
     *
     * @since 2.8.0
     *
     * @return array<string, mixed> Parsed workflow definition.
     */
    protected function workflow(): array
    {
        if ( null !== $this->parsedWorkflow ) {
            return $this->parsedWorkflow;
        }

        $path = dirname( __DIR__, 3 ) . '/.github/workflows/release.yml';

        $this->assertFileExists( $path );

        $parsed = Yaml::parseFile( $path );

        $this->assertIsArray( $parsed );

        return $this->parsedWorkflow = $parsed;
    }

    /**
     * Locate a step of the `release` job by one of its keys.
     *
     * @since 2.8.0
     *
     * @param  string  $key  Step key to match on.
     * @param  string  $needle  Value the key must start with.
     *
     * @return array<string, mixed> The matching step.
     */
    protected function releaseJobStep( string $key, string $needle ): array
    {
        $steps = $this->workflow()['jobs']['release']['steps'] ?? null;

        $this->assertIsArray( $steps, 'The release workflow must define a release job with steps.' );

        foreach ( $steps as $step ) {
            if ( is_array( $step ) && isset( $step[ $key ] ) && str_starts_with( (string) $step[ $key ], $needle ) ) {
                return $step;
            }
        }

        $this->fail( "No step in the release job has a {$key} starting with \"{$needle}\"." );
    }

    /**
     * Get the step that publishes the release.
     *
     * @since 2.8.0
     *
     * @return array<string, mixed> The release-creating step.
     */
    protected function releaseStep(): array
    {
        return $this->releaseJobStep( 'uses', 'softprops/action-gh-release@' );
    }

    /**
     * Get the shell script that builds the archive and its sidecar.
     *
     * @since 2.8.0
     *
     * @return string The step's `run` script.
     */
    protected function archiveStepScript(): string
    {
        return (string) ( $this->releaseJobStep( 'id', 'archive' )['run'] ?? '' );
    }

    /**
     * Resolve the archive filename the workflow builds.
     *
     * @since 2.8.0
     *
     * @return string Archive filename with the version substituted.
     */
    protected function archiveName(): string
    {
        $matched = preg_match( '/^\s*ARCHIVE="([^"]+)"/m', $this->archiveStepScript(), $matches );

        $this->assertSame( 1, $matched, 'The archive step must assign the archive filename to ARCHIVE.' );

        return $this->substituteVersion( $matches[1] );
    }

    /**
     * Resolve the sidecar filename the workflow writes the digest to.
     *
     * @since 2.8.0
     *
     * @return string Sidecar filename with the archive name substituted.
     */
    protected function sidecarName(): string
    {
        $matched = preg_match(
            '/sha256sum\s+"\$\{?ARCHIVE\}?"\s*>\s*"([^"]+)"/',
            $this->archiveStepScript(),
            $matches,
        );

        $this->assertSame( 1, $matched, 'The archive step must write a sha256sum digest to a sidecar file.' );

        return $this->resolveShellPlaceholders( $matches[1] );
    }

    /**
     * Expand `${ARCHIVE}` / `$ARCHIVE` and `${VERSION}` in a shell fragment.
     *
     * @since 2.8.0
     *
     * @param  string  $value  Shell fragment.
     *
     * @return string Fragment with the placeholders resolved.
     */
    protected function resolveShellPlaceholders( string $value ): string
    {
        return $this->substituteVersion(
            str_replace( [ '${ARCHIVE}', '$ARCHIVE' ], $this->archiveName(), $value ),
        );
    }

    /**
     * Expand the `steps.archive.outputs.archive` expression in a `files:` line.
     *
     * @since 2.8.0
     *
     * @param  string  $value  Workflow expression.
     *
     * @return string Line with the archive name resolved.
     */
    protected function resolveArchivePlaceholders( string $value ): string
    {
        return trim( preg_replace(
            '/\$\{\{\s*steps\.archive\.outputs\.archive\s*\}\}/',
            $this->archiveName(),
            $value,
        ) ?? '' );
    }

    /**
     * Substitute the workflow's `${VERSION}` placeholder.
     *
     * @since 2.8.0
     *
     * @param  string  $value  Shell fragment.
     *
     * @return string Fragment with the version substituted.
     */
    protected function substituteVersion( string $value ): string
    {
        return str_replace( [ '${VERSION}', '$VERSION' ], self::VERSION, $value );
    }
}

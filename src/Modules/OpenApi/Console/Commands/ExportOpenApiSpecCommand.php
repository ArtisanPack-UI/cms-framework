<?php

declare( strict_types=1 );

/**
 * Artisan command to export the CMS Framework OpenAPI specification.
 *
 * Generates the OpenAPI 3.x specification as a static JSON file that can
 * be published, committed to version control, or used for SDK generation.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\OpenApi\Console\Commands;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;

/**
 * Exports the CMS Framework OpenAPI specification to a static file.
 *
 * @since 1.1.0
 */
class ExportOpenApiSpecCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 1.1.0
     *
     * @var string
     */
    protected $signature = 'cms:openapi:export
		{path? : The output file path (default: cms-openapi.json in project root)}
		{--pretty : Pretty-print the JSON output}';

    /**
     * The console command description.
     *
     * @since 1.1.0
     *
     * @var string
     */
    protected $description = 'Export the CMS Framework OpenAPI specification to a static JSON file';

    /**
     * Execute the console command.
     *
     * @since 1.1.0
     *
     * @return int The exit code.
     */
    public function handle(): int
    {
        $path  = $this->argument( 'path' ) ?? base_path( 'cms-openapi.json' );
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ( $this->option( 'pretty' ) ) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->info( 'Generating CMS Framework OpenAPI specification...' );

        $config    = Scramble::getGeneratorConfig( 'cms' );
        $generator = app( Generator::class );
        $spec      = $generator( $config );
        $json      = json_encode( $spec, $flags );

        if ( false === $json ) {
            $this->error( 'Failed to encode OpenAPI specification to JSON.' );

            return self::FAILURE;
        }

        $directory = dirname( $path );
        if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) ) {
            $error = error_get_last();
            $this->error( "Failed to create directory: {$directory} — " . ( $error['message'] ?? 'unknown error' ) );

            return self::FAILURE;
        }

        if ( false === file_put_contents( $path, $json . "\n" ) ) {
            $error = error_get_last();
            $this->error( "Failed to write file: {$path} — " . ( $error['message'] ?? 'unknown error' ) );

            return self::FAILURE;
        }

        $this->info( "OpenAPI specification exported to: {$path}");

        return self::SUCCESS;
    }
}

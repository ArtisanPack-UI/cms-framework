<?php

declare( strict_types=1 );

/**
 * Artisan command to register disk-scaffolded plugins into the database.
 *
 * @since 2.9.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Console\Commands;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use Illuminate\Console\Command;

/**
 * Promotes every plugin discovered on disk into a `plugins` row so it can be
 * activated (#298).
 *
 * @since 2.9.0
 */
class SyncPluginsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 2.9.0
     *
     * @var string
     */
    protected $signature = 'cms:plugins:sync';

    /**
     * The console command description.
     *
     * @since 2.9.0
     *
     * @var string
     */
    protected $description = 'Register plugins discovered on disk into the database so they can be activated, preserving activation state for plugins already registered';

    /**
     * Execute the console command.
     *
     * @since 2.9.0
     *
     * @param  PluginManager  $manager  The plugin manager.
     *
     * @return int The exit code.
     */
    public function handle( PluginManager $manager ): int
    {
        $results = $manager->syncFromDisk();

        if ( empty( $results ) ) {
            $this->info( __( 'No plugins found on disk to sync.' ) );

            return self::SUCCESS;
        }

        $this->table(
            [ __( 'Plugin' ), __( 'Status' ), __( 'Detail' ) ],
            array_map(
                static fn ( array $result ): array => [
                    $result['slug'],
                    $result['status'],
                    $result['message'],
                ],
                $results,
            ),
        );

        $counts = array_count_values( array_column( $results, 'status' ) );

        $this->info( __(
            ':installed installed, :updated updated, :unchanged unchanged, :failed failed.',
            [
                'installed' => $counts['installed'] ?? 0,
                'updated'   => $counts['updated'] ?? 0,
                'unchanged' => $counts['unchanged'] ?? 0,
                'failed'    => $counts['failed'] ?? 0,
            ],
        ) );

        return empty( $counts['failed'] ) ? self::SUCCESS : self::FAILURE;
    }
}

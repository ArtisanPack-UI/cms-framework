<?php

declare( strict_types=1 );

/**
 * Artisan command to check every theme's update source on a schedule.
 *
 * @since 2.12.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Console\Commands;

use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\UpdateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Re-checks every installed theme against its update source and caches the
 * answers (#347).
 *
 * The theme counterpart to `update:check-scheduled`. The framework registers
 * the command but does not schedule it; the host wires it into its own
 * scheduler, e.g. `Schedule::command( 'cms:themes:check-updates' )->daily()`.
 *
 * @since 2.12.0
 */
class CheckThemeUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 2.12.0
     *
     * @var string
     */
    protected $signature = 'cms:themes:check-updates';

    /**
     * The console command description.
     *
     * @since 2.12.0
     *
     * @var string
     */
    protected $description = 'Check every theme for an available update and cache the results (schedulable)';

    /**
     * Execute the console command.
     *
     * @since 2.12.0
     *
     * @param  UpdateManager  $manager  The theme update manager.
     *
     * @return int The exit code.
     */
    public function handle( UpdateManager $manager ): int
    {
        $results  = $manager->refreshUpdateChecks();
        $updates  = $results['updates'];
        $failures = $results['failures'];

        if ( [] !== $updates ) {
            $this->table(
                [ __( 'Theme' ), __( 'Installed' ), __( 'Available' ) ],
                array_map(
                    static fn ( string $slug, array $update ): array => [
                        $slug,
                        (string) ( $update['current'] ?? '' ),
                        (string) ( $update['version'] ?? '' ),
                    ],
                    array_keys( $updates ),
                    $updates,
                ),
            );

            Log::info( 'Theme updates available', [
                'themes' => array_map(
                    static fn ( array $update ): string => (string) ( $update['version'] ?? '' ),
                    $updates,
                ),
            ] );
        }

        foreach ( $failures as $slug => $message ) {
            $this->error( __( 'Failed to check :slug: :message', [
                'slug'    => $slug,
                'message' => $message,
            ] ) );
        }

        $this->info( trans_choice(
            ':count theme update available.|:count theme updates available.',
            count( $updates ),
            [ 'count' => count( $updates ) ],
        ) );

        return [] === $failures ? self::SUCCESS : self::FAILURE;
    }
}

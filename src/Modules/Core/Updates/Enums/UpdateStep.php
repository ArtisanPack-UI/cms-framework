<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums;

/**
 * Update Step
 *
 * The ordered steps `ApplicationUpdateManager::performUpdate()` walks through.
 * Persisted to the update state file so an update that dies mid-flight (PHP
 * fatal, OOM, `kill -9`, FPM `request_terminate_timeout`) is diagnosable after
 * the fact rather than requiring the operator to read framework source.
 *
 * @since 2.7.1
 */
enum UpdateStep: string
{
    /**
     * The step's 1-based position in the update sequence.
     *
     * @since 2.7.1
     *
     * @return int Position in the update sequence.
     */
    public function number(): int
    {
        return match ( $this ) {
            self::EnableMaintenanceMode  => 1,
            self::Backup                 => 2,
            self::Download               => 3,
            self::VerifyChecksum         => 4,
            self::Extract                => 5,
            self::ComposerInstall        => 6,
            self::Migrations             => 7,
            self::ClearCaches            => 8,
            self::Cleanup                => 9,
            self::DisableMaintenanceMode => 10,
        };
    }

    /**
     * Human-readable description of the step.
     *
     * @since 2.7.1
     *
     * @return string Step label.
     */
    public function label(): string
    {
        return match ( $this ) {
            self::EnableMaintenanceMode  => __( 'Enable maintenance mode' ),
            self::Backup                 => __( 'Create pre-update backup' ),
            self::Download               => __( 'Download release archive' ),
            self::VerifyChecksum         => __( 'Verify release checksum' ),
            self::Extract                => __( 'Extract release archive' ),
            self::ComposerInstall        => __( 'Rebuild composer dependencies' ),
            self::Migrations             => __( 'Run database migrations' ),
            self::ClearCaches            => __( 'Clear application caches' ),
            self::Cleanup                => __( 'Clean up temporary files' ),
            self::DisableMaintenanceMode => __( 'Disable maintenance mode' ),
        };
    }

    /**
     * The command an operator can run by hand to complete this step after an
     * interrupted update, or `null` when the step has no manual equivalent
     * (downloading and extracting a half-applied release cannot be resumed —
     * restore the pre-update snapshot instead).
     *
     * @since 2.7.1
     *
     * @return string|null Shell command, or null when the step is not resumable.
     */
    public function recoveryCommand(): ?string
    {
        return match ( $this ) {
            self::ComposerInstall        => 'composer install --no-dev --no-interaction --optimize-autoloader',
            self::Migrations             => 'php artisan migrate --force',
            self::ClearCaches            => 'php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear',
            self::DisableMaintenanceMode => 'php artisan up',
            default                      => null,
        };
    }

    /**
     * Steps that had not been reached when the update stopped at this step.
     *
     * The step itself is included: an interrupted update was *in* this step
     * when it died, so there is no guarantee it finished.
     *
     * @since 2.7.1
     *
     * @return array<int, self> Steps still outstanding.
     */
    public function outstandingSteps(): array
    {
        return array_values( array_filter(
            self::cases(),
            fn ( self $step ): bool => $step->number() >= $this->number(),
        ) );
    }
    case EnableMaintenanceMode = 'enable_maintenance_mode';

    case Backup = 'backup';

    case Download = 'download';

    case VerifyChecksum = 'verify_checksum';

    case Extract = 'extract';

    case ComposerInstall = 'composer_install';

    case Migrations = 'migrations';

    case ClearCaches = 'clear_caches';

    case Cleanup = 'cleanup';

    case DisableMaintenanceMode = 'disable_maintenance_mode';
}

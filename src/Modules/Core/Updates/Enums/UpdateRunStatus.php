<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums;

/**
 * Update Run Status
 *
 * Lifecycle status of the most recent `performUpdate()` run, persisted to the
 * update state file. The distinction that matters operationally is
 * `Failed` (the updater caught the error and rolled back) versus
 * `Interrupted` (the process died before the catch block could run).
 *
 * @since 2.7.1
 */
enum UpdateRunStatus: string
{
    /**
     * Human-readable description of the status.
     *
     * @since 2.7.1
     *
     * @return string Status label.
     */
    public function label(): string
    {
        return match ( $this ) {
            self::InProgress  => __( 'In progress' ),
            self::Completed   => __( 'Completed' ),
            self::Failed      => __( 'Failed (rolled back)' ),
            self::Interrupted => __( 'Interrupted (process died mid-update)' ),
        };
    }

    /**
     * Whether this status means the update left the installation in a state
     * the operator needs to inspect.
     *
     * @since 2.7.1
     *
     * @return bool True when manual attention is warranted.
     */
    public function needsAttention(): bool
    {
        return self::Interrupted === $this || self::Failed === $this;
    }
    case InProgress = 'in_progress';

    case Completed = 'completed';

    case Failed = 'failed';

    case Interrupted = 'interrupted';
}

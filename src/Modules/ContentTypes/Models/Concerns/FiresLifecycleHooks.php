<?php

declare( strict_types=1 );

/**
 * FiresLifecycleHooks Trait
 *
 * Wires content-type model events to the framework's action bus so host apps and
 * plugins can subscribe to `saving`, `saved`, `published`, `trashed`, and
 * `restored` transitions on Post / Page (and any other model that adopts the
 * trait) without needing to observe Eloquent events directly.
 *
 * @since 2.5.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fires namespaced lifecycle actions from content-type model events.
 *
 * Adopting models must implement {@see lifecycleHookPrefix()} returning the
 * dotted prefix under which the trait dispatches (e.g. `ap.cmsFramework.post`).
 * The trait then emits, in order:
 *
 * - `{prefix}.saving`   — before the record is written (any create or update).
 * - `{prefix}.saved`    — after the record is written.
 * - `{prefix}.published` — after a save that transitions the record's
 *   `status` to {@see ContentStatus::Published} from a non-published value
 *   (covers first-save-as-published, draft→published, scheduled→published).
 * - `{prefix}.trashed`  — after a soft delete (skipped on force-delete).
 * - `{prefix}.restored` — after a soft-deleted record is restored.
 *
 * The published transition is detected by capturing the pre-save status on the
 * `saving` event and comparing against the post-save status on `saved` — this
 * survives Eloquent's `syncOriginal()` call at save time, which would otherwise
 * make `getOriginal( 'status' )` reflect the already-persisted new value.
 *
 * @since 2.5.0
 */
trait FiresLifecycleHooks
{
    /**
     * Snapshot of the record's `status` value captured on `saving`, consumed on
     * `saved` to detect a transition into {@see ContentStatus::Published}.
     * Stored per-instance rather than on the DB row so it survives Eloquent's
     * `syncOriginal()` step during the save cycle.
     *
     * @since 2.5.0
     */
    protected ?ContentStatus $preSaveLifecycleStatus = null;

    /**
     * Whether {@see $preSaveLifecycleStatus} was populated for the current save
     * cycle. `null` is a legitimate pre-save value (new record with no status
     * yet), so a bare null check on the snapshot alone can't distinguish
     * "unset" from "was null".
     *
     * @since 2.5.0
     */
    protected bool $preSaveLifecycleStatusCaptured = false;

    /**
     * The dotted hook prefix under which lifecycle actions fire for the adopting
     * model — e.g. `ap.cmsFramework.post` or `ap.cmsFramework.page`. Must be
     * implemented by every model that uses this trait.
     *
     * @since 2.5.0
     */
    abstract public static function lifecycleHookPrefix(): string;

    /**
     * Wire the trait's event listeners. Called automatically by Eloquent's
     * `bootTraits()` on the first model instantiation.
     *
     * @since 2.5.0
     */
    protected static function bootFiresLifecycleHooks(): void
    {
        static::saving( function ( $model ): void {
            $model->preSaveLifecycleStatus         = static::normalizeLifecycleStatus(
                $model->getOriginal( 'status' ),
            );
            $model->preSaveLifecycleStatusCaptured = true;

            doAction( static::lifecycleHookPrefix() . '.saving', $model );
        } );

        static::saved( function ( $model ): void {
            doAction( static::lifecycleHookPrefix() . '.saved', $model );

            $currentStatus  = static::normalizeLifecycleStatus( $model->status );
            $previousStatus = $model->preSaveLifecycleStatusCaptured
                ? $model->preSaveLifecycleStatus
                : null;

            $isPublishTransition = ContentStatus::Published === $currentStatus
                && ContentStatus::Published !== $previousStatus;

            if ( $isPublishTransition ) {
                doAction( static::lifecycleHookPrefix() . '.published', $model );
            }

            $model->preSaveLifecycleStatus         = null;
            $model->preSaveLifecycleStatusCaptured = false;
        } );

        static::deleted( function ( $model ): void {
            // Trashed = soft delete only. Skip when the model doesn't use
            // SoftDeletes at all, and when the current delete is a force-delete
            // (Eloquent still fires `deleted` in that case).
            if ( ! in_array( SoftDeletes::class, class_uses_recursive( $model ), true ) ) {
                return;
            }

            if ( method_exists( $model, 'isForceDeleting' ) && $model->isForceDeleting() ) {
                return;
            }

            doAction( static::lifecycleHookPrefix() . '.trashed', $model );
        } );

        static::registerRestoredLifecycleListener();
    }

    /**
     * Register the `restored` listener. Guarded so hosts that adopt this trait
     * on a model that doesn't use {@see SoftDeletes} don't blow up trying to
     * hook a non-existent event.
     *
     * @since 2.5.0
     */
    protected static function registerRestoredLifecycleListener(): void
    {
        if ( ! in_array( SoftDeletes::class, class_uses_recursive( static::class ), true ) ) {
            return;
        }

        static::restored( function ( $model ): void {
            doAction( static::lifecycleHookPrefix() . '.restored', $model );
        } );
    }

    /**
     * Normalize a raw status attribute (as returned by
     * {@see \Illuminate\Database\Eloquent\Model::getOriginal()} or a live
     * attribute accessor) into a {@see ContentStatus} enum. Accepts either a
     * ContentStatus instance (already cast) or the raw string value stored in
     * the column. Returns `null` for unrecognized / missing values so callers
     * can distinguish "no prior status" from a specific enum case.
     *
     * @since 2.5.0
     */
    protected static function normalizeLifecycleStatus( mixed $value ): ?ContentStatus
    {
        if ( $value instanceof ContentStatus ) {
            return $value;
        }

        if ( is_string( $value ) && '' !== $value ) {
            return ContentStatus::tryFrom( $value );
        }

        return null;
    }
}

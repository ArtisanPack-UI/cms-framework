<?php

declare( strict_types=1 );

/**
 * HasContentStatus Trait
 *
 * Provides shared content status scopes and helper methods for content type models.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for adding content status scopes and helpers to models.
 *
 * Provides scopePublished, scopeDraft, and isPublished methods
 * that are shared across Post, Page, and other content type models.
 *
 * @since 1.1.0
 */
trait HasContentStatus
{
    /**
     * Scope a query to only include published content.
     *
     * @since 1.1.0
     *
     * @return Builder
     */
    public function scopePublished( Builder $query )
    {
        return $query->where( 'status', ContentStatus::Published )
            ->where( function ( $q ): void {
                $q->whereNull( 'published_at' )
                    ->orWhere( 'published_at', '<=', now() );
            } );
    }

    /**
     * Scope a query to only include draft content.
     *
     * @since 1.1.0
     *
     * @return Builder
     */
    public function scopeDraft( Builder $query )
    {
        return $query->where( 'status', ContentStatus::Draft );
    }

    /**
     * Check if the content is published.
     *
     * @since 1.1.0
     */
    public function isPublished(): bool
    {
        return ContentStatus::Published === $this->status &&
            ( null === $this->published_at || $this->published_at->isPast());
    }
}

<?php

declare(strict_types=1);

/**
 * HasFeaturedImage Trait
 *
 * Provides featured image functionality for any model via a polymorphic pivot.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

use ArtisanPackUI\MediaLibrary\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Trait for attaching a single featured image to a host model via the
 * `featureables` polymorphic pivot table shipped with the cms-framework.
 *
 * The trait does not add a column to the host model's table; the
 * association is stored entirely in `featureables`. This lets the trait
 * be applied to models the package does not own (for example, an
 * application's `User` model) without requiring a schema change on the
 * host table.
 *
 * @since 1.0.0
 */
trait HasFeaturedImage
{
    /**
     * Get the featured image relation for the model.
     *
     * Returns a `MorphToMany` relation against the `featureables` pivot.
     * `setFeaturedImage()` keeps this constrained to a single row, so
     * `$model->featuredImage->first()` (or `getFeaturedImageUrl()`) is
     * the typical way to read the attached media.
     *
     * @since 1.0.0
     */
    public function featuredImage(): MorphToMany
    {
        return $this->morphToMany(
            $this->featuredImageMediaModel(),
            'featurable',
            'featureables',
            null,
            'media_id'
        )
            ->withTimestamps();
    }

    /**
     * Set the featured image for the model.
     *
     * Replaces any existing featured image with the given media row.
     *
     * @since 1.0.0
     *
     * @param  int  $mediaId  The ID of the media row to attach.
     */
    public function setFeaturedImage(int $mediaId): void
    {
        $this->featuredImage()->sync([sanitizeInt($mediaId)]);
    }

    /**
     * Remove the featured image from the model.
     *
     * Detaches every featured image row for this model from the pivot.
     *
     * @since 1.0.0
     */
    public function removeFeaturedImage(): void
    {
        $this->featuredImage()->detach();
    }

    /**
     * Get the URL for the currently attached featured image.
     *
     * @since 1.0.0
     *
     * @param  string  $size  Reserved for size-aware Media implementations.
     */
    public function getFeaturedImageUrl(string $size = 'full'): ?string
    {
        // Reuse the eager-loaded relation when available so callers that
        // already loaded `featuredImage` (e.g. via API resources) don't
        // get an N+1 query per row.
        $media = $this->relationLoaded('featuredImage')
            ? $this->featuredImage->first()
            : $this->featuredImage()->first();

        return $media?->url;
    }

    /**
     * The FQCN of the Media model the trait should attach to.
     *
     * Override on the host model (or in test fixtures) to substitute a
     * different Media implementation.
     *
     * @since 2.0.0
     *
     * @return class-string
     */
    protected function featuredImageMediaModel(): string
    {
        return Media::class;
    }
}

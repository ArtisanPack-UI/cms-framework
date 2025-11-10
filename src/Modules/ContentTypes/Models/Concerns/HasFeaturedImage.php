<?php
/**
 * HasFeaturedImage Trait
 *
 * Provides featured image functionality for content types.
 *
 * @since 2.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

use ArtisanPackUI\MediaLibrary\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Trait for adding featured image support to models.
 *
 * @since 2.0.0
 */
trait HasFeaturedImage
{
    /**
     * Get the featured image for the model.
     *
     * @since 2.0.0
     *
     * @return MorphOne
     */
    public function featuredImage(): MorphOne
    {
        return $this->morphOne(Media::class, 'featurable', 'featurable_type', 'featurable_id')
            ->withTimestamps();
    }

    /**
     * Set the featured image for the model.
     *
     * @since 2.0.0
     *
     * @param int $mediaId The ID of the media to set as featured image.
     *
     * @return void
     */
    public function setFeaturedImage(int $mediaId): void
    {
        // Remove existing featured image
        $this->removeFeaturedImage();

        // Create new featured image relationship
        Media::where('id', $mediaId)->update([
            'featurable_type' => get_class($this),
            'featurable_id' => $this->id,
        ]);
    }

    /**
     * Remove the featured image from the model.
     *
     * @since 2.0.0
     *
     * @return void
     */
    public function removeFeaturedImage(): void
    {
        Media::where('featurable_type', get_class($this))
            ->where('featurable_id', $this->id)
            ->update([
                'featurable_type' => null,
                'featurable_id' => null,
            ]);
    }

    /**
     * Get the featured image URL.
     *
     * @since 2.0.0
     *
     * @param string $size The size of the image (full, thumbnail, medium, large).
     *
     * @return string|null
     */
    public function getFeaturedImageUrl(string $size = 'full'): ?string
    {
        $featuredImage = $this->featuredImage;

        if (! $featuredImage) {
            return null;
        }

        // Return the appropriate URL based on size
        // This will depend on how the Media model handles sizes
        return $featuredImage->url ?? null;
    }
}

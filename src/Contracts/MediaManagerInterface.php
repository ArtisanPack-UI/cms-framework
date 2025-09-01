<?php

declare(strict_types=1);

/**
 * Media Manager Interface
 *
 * Defines the contract for media management operations in the CMS framework.
 * This interface provides methods for uploading, managing, and retrieving media files.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Contracts;

use ArtisanPackUI\CMSFramework\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

/**
 * Media Manager Interface
 *
 * Defines the contract for media management operations including media upload,
 * retrieval, updating, deletion, and URL generation.
 *
 * @since 1.0.0
 */
interface MediaManagerInterface
{
    /**
     * Upload a new media file to the system.
     *
     * @param  UploadedFile  $file  The uploaded file.
     * @param  string|null  $altText  The alternative text for the media.
     * @param  string|null  $caption  The caption for the media.
     * @param  bool  $isDecorative  Whether the media is decorative.
     * @param  array  $metadata  Additional metadata for the media.
     * @return Media|null The created media instance if successful, null otherwise.
     */
    public function upload(
        UploadedFile $file,
        ?string $altText,
        ?string $caption,
        bool $isDecorative,
        array $metadata
    ): ?Media;

    /**
     * Get all media files with pagination.
     *
     * @param  int  $perPage  Number of media items per page.
     * @return LengthAwarePaginator Paginated media results.
     */
    public function all(int $perPage): LengthAwarePaginator;

    /**
     * Get media files by a specific user with pagination.
     *
     * @param  int  $userId  The ID of the user.
     * @param  int  $perPage  Number of media items per page.
     * @return LengthAwarePaginator Paginated media results for the user.
     */
    public function getMediaByUser(int $userId, int $perPage): LengthAwarePaginator;

    /**
     * Update an existing media file with new data.
     *
     * @param  int  $mediaId  The ID of the media to update.
     * @param  array  $data  The new data for the media.
     * @return Media|null The updated media instance if successful, null otherwise.
     */
    public function update(int $mediaId, array $data): ?Media;

    /**
     * Get a specific media file by its ID.
     *
     * @param  int  $mediaId  The ID of the media to retrieve.
     * @return Media|null The media instance if found, null otherwise.
     */
    public function get(int $mediaId): ?Media;

    /**
     * Delete a media file from the system.
     *
     * @param  int  $mediaId  The ID of the media to delete.
     * @return bool True if deletion was successful, false otherwise.
     */
    public function delete(int $mediaId): bool;

    /**
     * Get the URL for a media file.
     *
     * @param  Media  $media  The media instance.
     * @return string The URL to access the media file.
     */
    public function getUrl(Media $media): string;
}

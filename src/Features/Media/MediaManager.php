<?php
/**
 * Media Manager
 *
 * Manages CRUD operations and associated logic for media items, including
 * handling uploads, retrieving media, and ensuring accessibility attributes
 * like alt text.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Media
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Media;

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\Security\Security;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

/**
 * Class for managing media items
 *
 * Provides functionality to manage media items within the application, including
 * uploading, retrieving, updating, and deleting media, with a focus on accessibility.
 *
 * @since 1.0.0
 */
class MediaManager
{
    /**
     * The disk to use for media storage.
     *
     * @since 1.0.0
     * @var string
     */
    protected string $disk;

    /**
     * The logger instance.
     *
     * @since 1.0.0
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Constructor
     *
     * Initializes the MediaManager with the default storage disk and a logger instance.
     *
     * @since 1.0.0
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct( LoggerInterface $logger )
    {
        $this->disk   = config( 'cms.media.disk', 'public' );
        $this->logger = $logger;
    }

    /**
     * Uploads a new media file and creates a database entry.
     *
     * This method handles the file upload to the configured storage disk and
     * creates a corresponding record in the database, including accessibility attributes
     * and associating it with the current authenticated user.
     * The file is stored in a year/month directory structure.
     *
     * @since 1.0.0
     *
     * @param UploadedFile $file         The uploaded file instance.
     * @param string|null  $altText      Optional. The alternative text for the media. Default null.
     * @param string|null  $caption      Optional. The caption for the media item. Default null.
     * @param bool         $isDecorative Optional. Whether the image is purely decorative. Default false.
     * @param array        $metadata     Optional. Additional metadata for the media. Default empty array.
     * @return Media|null The created Media model instance on success, or null on failure.
     */
    public function upload( UploadedFile $file, ?string $altText = null, ?string $caption = null, bool $isDecorative = false, array $metadata = [] ): ?Media
    {
        try {
            $security = new Security();

            $userId = Auth::id();
            if ( ! $userId ) {
                $this->logger->error( 'No authenticated user found for media upload.' );
                return null;
            }

            $year            = date( 'Y' );
            $month           = date( 'm' );
            $baseDirectory   = config( 'cms.media.directory', 'media' );
            $uploadDirectory = "{$baseDirectory}/{$year}/{$month}";

            $originalFilename = $security->sanitizeFilename( $file->getClientOriginalName() );
            $extension        = $file->getClientOriginalExtension();
            $filename         = pathinfo( $originalFilename, PATHINFO_FILENAME );
            $filename         = uniqid( $filename . '-' ) . '.' . $extension;

            $path = Storage::disk( $this->disk )->putFileAs(
                $uploadDirectory,
                $file,
                $filename
            );

            if ( ! $path ) {
                $this->logger->error( 'Failed to store media file: ' . $originalFilename );
                return null;
            }

            $media = Media::create(
                [
                    'user_id'       => $userId,
                    'file_name'     => $security->sanitizeText( $filename ),
                    'mime_type'     => $security->sanitizeText( $file->getMimeType() ),
                    'path'          => $security->sanitizeText( $path ),
                    'size'          => $security->sanitizeInt( $file->getSize() ),
                    'alt_text'      => $isDecorative ? '' : $security->sanitizeText( $altText ?? '' ),
                    'caption'       => $security->kses( $caption ?? '' ), // Sanitize caption (allows some HTML)
                    'is_decorative' => $isDecorative,
                    'metadata'      => $security->sanitizeArray( $metadata ),
                ]
            );

            $this->logger->info( 'Media uploaded successfully: ' . $media->file_name . ' by user ID: ' . $userId );

            return $media;
        } catch ( \Exception $e ) {
            $this->logger->error( 'Error uploading media: ' . $e->getMessage(), [ 'exception' => $e ] );
            return null;
        }
    }

    /**
     * Retrieves all media items with optional pagination.
     *
     * @since 1.0.0
     *
     * @param int $perPage Optional. The number of media items per page. Default 15.
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator A paginated collection of Media models.
     */
    public function all( int $perPage = 15 ): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Media::paginate( $perPage );
    }

    /**
     * Retrieves media items uploaded by a specific user with optional pagination.
     *
     * @since 1.0.0
     *
     * @param int $userId  The ID of the user whose media to retrieve.
     * @param int $perPage Optional. The number of media items per page. Default 15.
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator A paginated collection of Media models.
     */
    public function getMediaByUser( int $userId, int $perPage = 15 ): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Media::where( 'user_id', $userId )->paginate( $perPage );
    }

    /**
     * Updates an existing media item's information.
     *
     * This method allows updating the alt text, decorative status, caption, and metadata
     * of an existing media record. It does not handle file replacement.
     *
     * @since 1.0.0
     *
     * @param int   $mediaId       The ID of the media item to update.
     * @param array $data          An associative array of data to update.
     * @param string $data['alt_text']      Optional. The alternative text for the media.
     * @param string $data['caption']       Optional. The caption for the media item.
     * @param bool   $data['is_decorative'] Optional. Whether the image is purely decorative.
     * @param array  $data['metadata']      Optional. Additional metadata for the media.
     * @return Media|null The updated Media model instance on success, or null if not found.
     */
    public function update( int $mediaId, array $data ): ?Media
    {
        $media = $this->get( $mediaId );

        if ( ! $media ) {
            $this->logger->warning( 'Attempted to update non-existent media: ' . $mediaId );
            return null;
        }

        $security   = new Security();
        $updateData = [];

        // Check for 'is_decorative' first as it influences 'alt_text'.
        if ( isset( $data['is_decorative'] ) ) {
            $updateData['is_decorative'] = (bool)$data['is_decorative'];
            if ( true === $updateData['is_decorative'] ) {
                $updateData['alt_text'] = ''; // Clear alt text if becoming decorative
            }
        }

        // Only set alt_text if it's provided AND the image isn't explicitly set as decorative in this update.
        if ( isset( $data['alt_text'] ) && ( ! isset( $updateData['is_decorative'] ) || false === $updateData['is_decorative'] ) ) {
            $updateData['alt_text'] = $security->sanitizeText( $data['alt_text'] );
        } elseif ( ! isset( $data['alt_text'] ) && isset( $updateData['is_decorative'] ) && false === $updateData['is_decorative'] ) {
            $updateData['alt_text'] = $media->alt_text; // Retain existing alt_text if changing from decorative=true to false.
        }

        // Add caption to update data
        if ( isset( $data['caption'] ) ) {
            $updateData['caption'] = $security->kses( $data['caption'] ); // Sanitize caption
        }

        if ( isset( $data['metadata'] ) ) {
            $updateData['metadata'] = $security->sanitizeArray( $data['metadata'] );
        }

        try {
            if ( ! empty( $updateData ) ) {
                $media->update( $updateData );
                $this->logger->info( 'Media attributes updated successfully: ' . $media->id );
            } else {
                $this->logger->info( 'No direct media attributes to update for ID: ' . $media->id . '. Only relationships might be changing.' );
            }

            return $media;
        } catch ( \Exception $e ) {
            $this->logger->error( 'Error updating media: ' . $e->getMessage(), [ 'exception' => $e, 'media_id' => $mediaId ] );
            return null;
        }
    }

    /**
     * Retrieves a media item by its ID.
     *
     * @since 1.0.0
     *
     * @param int $mediaId The ID of the media item to retrieve.
     * @return Media|null The Media model instance if found, otherwise null.
     */
    public function get( int $mediaId ): ?Media
    {
        return Media::find( $mediaId );
    }

    /**
     * Deletes a media item from the database and its corresponding file.
     *
     * @since 1.0.0
     *
     * @param int $mediaId The ID of the media item to delete.
     * @return bool True on successful deletion, false otherwise.
     */
    public function delete( int $mediaId ): bool
    {
        $media = $this->get( $mediaId );

        if ( ! $media ) {
            $this->logger->warning( 'Attempted to delete non-existent media: ' . $mediaId );
            return false;
        }

        try {
            if ( Storage::disk( $this->disk )->exists( $media->path ) ) {
                Storage::disk( $this->disk )->delete( $media->path );
                $this->logger->info( 'Media file deleted from storage: ' . $media->path );
            } else {
                $this->logger->warning( 'Media file not found for deletion, but database record exists: ' . $media->path );
            }

            $deleted = $media->delete();

            if ( $deleted ) {
                $this->logger->info( 'Media record deleted successfully: ' . $media->id );
            } else {
                $this->logger->error( 'Failed to delete media record from database: ' . $media->id );
            }

            return $deleted;
        } catch ( \Exception $e ) {
            $this->logger->error( 'Error deleting media: ' . $e->getMessage(), [ 'exception' => $e, 'media_id' => $mediaId ] );
            return false;
        }
    }

    /**
     * Generates a public URL for a media item.
     *
     * @since 1.0.0
     *
     * @param Media $media The Media model instance.
     * @return string The public URL of the media item.
     */
    public function getUrl( Media $media ): string
    {
        return Storage::disk( $this->disk )->url( $media->path );
    }
}

<?php
/**
 * Media Controller
 *
 * Handles HTTP requests for media management, including uploading, retrieving,
 * updating, and deleting media items. It interacts with the MediaManager
 * to perform the core logic.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Features\Media\MediaManager;
use ArtisanPackUI\CMSFramework\Http\Requests\MediaRequest;

// Changed from separate requests
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Class MediaController
 *
 * Controller for managing media resources via API.
 *
 * @since 1.0.0
 */
class MediaController extends Controller
{
	/**
	 * The MediaManager instance.
	 *
	 * @since 1.0.0
	 * @var MediaManager
	 */
	protected MediaManager $mediaManager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param MediaManager $mediaManager The media manager instance.
	 */
	public function __construct( MediaManager $mediaManager )
	{
		$this->mediaManager = $mediaManager;
	}

	/**
	 * Display a listing of the media.
	 *
	 * @since 1.0.0
	 * @param Request $request The incoming request.
	 * @return JsonResponse
	 */
	public function index( Request $request ): JsonResponse
	{
		$perPage = $request->input( 'per_page', 15 );
		$media   = $this->mediaManager->all( $perPage );

		return response()->json( [ 'data' => $media ] );
	}

	/**
	 * Store a newly created media item in storage.
	 *
	 * This method utilizes the MediaManager's upload method to handle
	 * file storage, database entry, and association with the authenticated user.
	 *
	 * @since 1.0.0
	 * @param MediaRequest $request The validated form request.
	 * @return JsonResponse
	 */
	public function store( MediaRequest $request ): JsonResponse
	{
		// The 'file' field is only required/present for POST requests as per MediaRequest rules.
		$file         = $request->file( 'file' );
		$altText      = $request->input( 'alt_text' );
		$isDecorative = $request->boolean( 'is_decorative' );
		$metadata     = $request->input( 'metadata', [] );
		$categories   = $request->input( 'media_categories', [] );
		$tags         = $request->input( 'media_tags', [] );

		$media = $this->mediaManager->upload(
			$file,
			$altText,
			$isDecorative,
			$metadata
		);

		if ( ! $media ) {
			return response()->json( [ 'message' => 'Media upload failed.' ], 500 );
		}

		// Attach categories and tags after media creation.
		if ( ! empty( $categories ) ) {
			$media->mediaCategories()->sync( $categories );
		}
		if ( ! empty( $tags ) ) {
			$media->mediaTags()->sync( $tags );
		}

		return response()->json( [ 'message' => 'Media uploaded successfully.', 'data' => $media ], 201 );
	}

	/**
	 * Display the specified media item.
	 *
	 * @since 1.0.0
	 * @param int $mediaId The ID of the media item.
	 * @return JsonResponse
	 */
	public function show( int $mediaId ): JsonResponse
	{
		$media = $this->mediaManager->get( $mediaId );

		if ( ! $media ) {
			return response()->json( [ 'message' => 'Media not found.' ], 404 );
		}

		return response()->json( [ 'data' => $media ] );
	}

	/**
	 * Update the specified media item in storage.
	 *
	 * This method utilizes the MediaManager's update method to handle
	 * updating media attributes and relationships.
	 *
	 * @since 1.0.0
	 * @param int          $mediaId The ID of the media item to update.
	 * @param MediaRequest $request The validated form request.
	 * @return JsonResponse
	 */
	public function update( MediaRequest $request, int $mediaId ): JsonResponse
	{
		// The validated data will contain the fields allowed for update.
		$updateData = $request->validated();
		$categories = $request->input( 'media_categories', [] );
		$tags       = $request->input( 'media_tags', [] );

		$media = $this->mediaManager->update( $mediaId, $updateData );

		if ( ! $media ) {
			return response()->json( [ 'message' => 'Media update failed or media not found.' ], 500 );
		}

		// Sync relationships for categories and tags.
		if ( $request->has( 'media_categories' ) ) {
			$media->mediaCategories()->sync( $categories );
		}
		if ( $request->has( 'media_tags' ) ) {
			$media->mediaTags()->sync( $tags );
		}

		return response()->json( [ 'message' => 'Media updated successfully.', 'data' => $media ] );
	}

	/**
	 * Remove the specified media item from storage.
	 *
	 * @since 1.0.0
	 * @param int $mediaId The ID of the media item to delete.
	 * @return JsonResponse
	 */
	public function destroy( int $mediaId ): JsonResponse
	{
		// The authorization for deletion should be handled via a policy or similar.
		// For direct controller authorization, you could do:
		$media = $this->mediaManager->get( $mediaId );
		if ( ! $media || $media->user_id !== Auth::id() ) { // Example authorization.
			return response()->json( [ 'message' => 'Unauthorized to delete this media or media not found.' ], 403 );
		}

		if ( $this->mediaManager->delete( $mediaId ) ) {
			return response()->json( [ 'message' => 'Media deleted successfully.' ], 204 ); // 204 No Content for successful deletion.
		}

		return response()->json( [ 'message' => 'Media deletion failed.' ], 500 );
	}
}
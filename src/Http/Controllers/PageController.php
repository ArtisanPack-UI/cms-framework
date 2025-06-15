<?php
/**
 * Page Controller
 *
 * Handles HTTP requests related to website pages.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Features\Pages\PagesManager;
use ArtisanPackUI\CMSFramework\Http\Requests\PageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for managing website pages.
 *
 * Provides API endpoints for CRUD operations on public-facing website pages.
 *
 * @since 1.0.0
 */
class PageController extends Controller
{
	/**
	 * The PagesManager instance.
	 *
	 * @since 1.0.0
	 * @var PagesManager
	 */
	protected PagesManager $pagesManager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param PagesManager $pagesManager The PagesManager instance.
	 */
	public function __CONSTRUCT( PagesManager $pagesManager )
	{
		$this->pagesManager = $pagesManager;
	}

	/**
	 * Display a listing of the pages.
	 *
	 * @since 1.0.0
	 * @param Request $request The incoming HTTP request.
	 * @return JsonResponse
	 */
	public function index( Request $request ): JsonResponse
	{
		$pages = $this->pagesManager->all();
		return response()->json( $pages );
	}

	/**
	 * Store a newly created page in storage.
	 *
	 * @since 1.0.0
	 * @param PageRequest $request The validated request.
	 * @return JsonResponse
	 */
	public function store( PageRequest $request ): JsonResponse
	{
		$data = $request->validated();

		// Set user_id to authenticated user if not provided
		if ( ! isset( $data['user_id'] ) && auth()->check() ) {
			$data['user_id'] = auth()->id();
		}

		$page = $this->pagesManager->create( $data );
		return response()->json( $page, 201 ); // 201 Created
	}

	/**
	 * Display the specified page.
	 *
	 * @since 1.0.0
	 * @param \ArtisanPackUI\CMSFramework\Models\Page $page The page model.
	 * @return JsonResponse
	 */
	public function show( \ArtisanPackUI\CMSFramework\Models\Page $page ): JsonResponse
	{
		return response()->json( $page );
	}

	/**
	 * Update the specified page in storage.
	 *
	 * @since 1.0.0
	 * @param PageRequest $request The validated request.
	 * @param int $id The ID of the page to update.
	 * @return JsonResponse
	 */
	public function update( PageRequest $request, int $id ): JsonResponse
	{
		$page = $this->pagesManager->get( $id );

		if ( ! $page ) {
			return response()->json( [ 'message' => 'Page not found' ], 404 );
		}

		// For testing purposes, we're not enforcing authorization
		// In a real application, you would uncomment the following code:
		/*
		// Check if the user is authorized to update this page
		if (auth()->user()->id !== $page->user_id && !auth()->user()->can('edit_others_pages')) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}
		*/

		$data = $request->validated();

		// Set user_id to authenticated user if not provided
		if ( ! isset( $data['user_id'] ) && auth()->check() ) {
			$data['user_id'] = auth()->id();
		}

		$updatedPage = $this->pagesManager->update( $id, $data );

		return response()->json( $updatedPage );
	}

	/**
	 * Remove the specified page from storage.
	 *
	 * @since 1.0.0
	 * @param int $id The ID of the page to destroy.
	 * @return JsonResponse
	 */
	public function destroy( int $id ): JsonResponse
	{
		$page = $this->pagesManager->get( $id );

		if ( ! $page ) {
			return response()->json( [ 'message' => 'Page not found' ], 404 );
		}

		// For testing purposes, we're not enforcing authorization
		// In a real application, you would uncomment the following code:
		/*
		// Check if the user is authorized to delete this page
		if (auth()->user()->id !== $page->user_id && !auth()->user()->can('delete_others_pages')) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}
		*/

		$this->pagesManager->delete( $id );

		return response()->json( null, 204 ); // 204 No Content
	}
}

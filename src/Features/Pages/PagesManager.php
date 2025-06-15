<?php
/**
 * Pages Manager
 *
 * Manages CRUD operations and business logic for website pages.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Pages
 * @since      1.0.0
 *
 */

namespace ArtisanPackUI\CMSFramework\Features\Pages;

use ArtisanPackUI\CMSFramework\Models\Page;

// Assuming a Page Eloquent model exists.
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Class for managing website pages.
 *
 * Provides functionality to manage website pages, including
 * creating, retrieving, updating, and deleting public-facing content.
 *
 * @since 1.0.0
 */
class PagesManager
{
	/**
	 * Cache key for storing website pages.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $cacheKey = 'cms.website.pages.resolved';

	/**
	 * Cache time-to-live in minutes (60 * 24 = 1 day).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected int $cacheTtl = 60 * 24;

	/**
	 * Constructor.
	 *
	 * Initializes the Pages manager.
	 *
	 * @since 1.0.0
	 */
	public function __CONSTRUCT()
	{
		// Any initial setup for pages can go here, e.g., loading from database.
	}

	/**
	 * Get all website pages.
	 *
	 * Retrieves all public-facing pages from the database, utilizing a cache.
	 *
	 * @since 1.0.0
	 * @return Collection A collection of all Page models.
	 */
	public function all(): Collection
	{
		return Cache::remember( $this->cacheKey, $this->cacheTtl, function () {
			return Page::all();
		} );
	}

	/**
	 * Get a website page by its ID.
	 *
	 * Retrieves a single website page by its primary key from the database.
	 *
	 * @since 1.0.0
	 * @param int $id The ID of the website page to retrieve.
	 * @return Page|null The Page model instance or null if not found.
	 */
	public function get( int $id ): ?Page
	{
		return Page::find( $id );
	}

	/**
	 * Create a new website page.
	 *
	 * Creates a new public-facing page in the database with the provided data and
	 * refreshes the cached pages.
	 *
	 * @since 1.0.0
	 * @param array $data The data for the new website page.
	 * @return Page The newly created Page model instance.
	 */
	public function create( array $data ): Page
	{
		// If status is published and published_at is not set, set it to now
		if ( isset( $data['status'] ) && $data['status'] === 'published' && ! isset( $data['published_at'] ) ) {
			$data['published_at'] = now();
		}
		$page = Page::create( $data );
		$this->refreshPagesCache();
		return $page;
	}

	/**
	 * Refresh pages cache.
	 *
	 * Clears the website pages cache and forces a reload of the pages from the database.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function refreshPagesCache(): void
	{
		Cache::forget( $this->cacheKey );
	}

	/**
	 * Update an existing website page.
	 *
	 * Updates an existing public-facing page in the database with the provided data and
	 * refreshes the cached pages.
	 *
	 * @since 1.0.0
	 * @param int   $id   The ID of the website page to update.
	 * @param array $data The data to update the website page with.
	 * @return Page|null The updated Page model instance or null if not found.
	 */
	public function update( int $id, array $data ): ?Page
	{
		$page = Page::find( $id );
		if ( $page ) {
			// If status is being set to published and published_at is not set, set it to now
			if ( isset( $data['status'] ) && $data['status'] === 'published' && ! isset( $data['published_at'] ) ) {
				$data['published_at'] = now();
			}
			$page->update( $data );
			$this->refreshPagesCache();
		}
		return $page;
	}

	/**
	 * Delete a website page.
	 *
	 * Deletes a public-facing page from the database and refreshes the cached pages.
	 *
	 * @since 1.0.0
	 * @param int $id The ID of the website page to delete.
	 * @return bool True if the page was deleted, false otherwise.
	 */
	public function delete( int $id ): bool
	{
		$deleted = Page::destroy( $id );
		if ( $deleted ) {
			$this->refreshPagesCache();
		}
		return (bool) $deleted;
	}
}

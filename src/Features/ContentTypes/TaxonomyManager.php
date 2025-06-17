<?php

namespace ArtisanPackUI\CMSFramework\Features\ContentTypes;

use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use Illuminate\Support\Facades\Cache;

// Assuming Taxonomy model

/**
 * Manages the registration and retrieval of taxonomy definitions.
 *
 * This class serves as the central registry for all taxonomies
 * within the ArtisanPack UI CMS Framework, including built-in and
 * dynamically created user-defined taxonomies. It caches loaded
 * definitions for performance.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework
 * @since      1.1.0
 */
class TaxonomyManager
{
    /**
     * Array to hold merged taxonomy definitions from config and database.
     *
     * @since 1.1.0
     * @var array
     */
    protected array $taxonomies = [];

    /**
     * Cache key for storing taxonomy definitions.
     *
     * @since 1.1.0
     * @var string
     */
    protected string $cacheKey = 'cms.taxonomies.resolved';

    /**
     * Cache time-to-live in minutes (60 * 24 = 1 day).
     *
     * @since 1.1.0
     * @var int
     */
    protected int $cacheTtl = 60 * 24;

    /**
     * Constructor for the TaxonomyManager.
     *
     * Initializes the taxonomy manager by loading definitions
     * from built-in sources (e.g., config) and user-defined sources (database).
     *
     * @since 1.1.0
     */
    public function __construct()
    {
        $this->loadTaxonomies();
    }

    /**
     * Loads taxonomy definitions from various sources (config, database).
     *
     * Merges built-in taxonomy definitions (from config or hardcoded)
     * with user-defined taxonomy definitions stored in the database.
     *
     * @since 1.1.0
     *
     * @return void
     */
    protected function loadTaxonomies(): void
    {
        $builtInTaxonomies = config( 'cms.taxonomies', [] ); // Load from cms config file
        foreach ( $builtInTaxonomies as $handle => $definition ) {
            $this->registerTaxonomy( $handle, $definition );
        }

        // Load user-defined taxonomies from the database and merge/override
        $dbDefinedTaxonomies = Cache::remember( $this->cacheKey, $this->cacheTtl, function () {
            return Taxonomy::all()->keyBy( 'handle' )->map( function ( $taxonomy ) {
                // Combine direct model attributes with any 'definition' JSON if you had one for taxonomies
                return [
                    'label'         => $taxonomy->label,
                    'label_plural'  => $taxonomy->label_plural,
                    'content_types' => $taxonomy->content_types,
                    'hierarchical'  => $taxonomy->hierarchical,
                    // Add any other attributes from the taxonomy table or a definition JSON if applicable
                ];
            } )->toArray();
        } );

        // Merge user-defined taxonomies (from DB) into existing built-in taxonomies
        // DB definitions will overwrite built-in if handles conflict.
        foreach ( $dbDefinedTaxonomies as $handle => $definition ) {
            $this->taxonomies[ $handle ] = array_replace_recursive( $this->taxonomies[ $handle ] ?? [], $definition );
        }
    }

    /**
     * Registers a taxonomy definition.
     *
     * This method allows programmatic registration of taxonomies, typically used
     * for core or module-provided taxonomies like 'categories' or 'tags'.
     *
     * @since 1.1.0
     *
     * @param string $handle     A unique identifier for the taxonomy (e.g., 'category', 'tag').
     * @param array  $definition An associative array defining the taxonomy's properties.
     * @return void
     */
    public function registerTaxonomy( string $handle, array $definition ): void
    {
        $this->taxonomies[ $handle ] = $definition;
    }

    /**
     * Provides access to the TaxonomyManager instance.
     *
     * This static method allows for convenient access to the singleton
     * instance of the TaxonomyManager from anywhere in the application.
     *
     * @since 1.1.0
     *
     * @return self
     */
    public static function instance(): self
    {
        return app( static::class );
    }

    /**
     * Retrieves a taxonomy definition by its handle.
     *
     * @since 1.1.0
     *
     * @param string $handle The unique handle of the taxonomy.
     * @return array|null The taxonomy definition, or null if not found.
     */
    public function getTaxonomy( string $handle ): ?array
    {
        return $this->taxonomies[ $handle ] ?? null;
    }

    /**
     * Retrieves all registered taxonomy definitions.
     *
     * @since 1.1.0
     *
     * @return array An associative array of all registered taxonomies.
     */
    public function allTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /**
     * Adds or updates a user-defined taxonomy in the database.
     *
     * This method is intended for programmatic creation/update of taxonomies
     * that users can manage via an interface. It persists the definition to the database
     * and refreshes the cache.
     *
     * @since 1.1.0
     *
     * @param string $handle       The unique handle for the taxonomy.
     * @param string $label        The singular human-readable label.
     * @param string $labelPlural  The plural human-readable label.
     * @param array  $contentTypes Array of content type handles this taxonomy applies to.
     * @param bool   $hierarchical Whether terms in this taxonomy can have parents.
     * @return Taxonomy             The created or updated Taxonomy model instance.
     */
    public function saveUserTaxonomy( string $handle, string $label, string $labelPlural, array $contentTypes, bool $hierarchical ): Taxonomy
    {
        $taxonomy = Taxonomy::updateOrCreate(
            [ 'handle' => $handle ],
            [
                'label'         => $label,
                'label_plural'  => $labelPlural,
                'content_types' => $contentTypes,
                'hierarchical'  => $hierarchical,
            ]
        );
        $this->refreshTaxonomiesCache();
        return $taxonomy;
    }

    /**
     * Refreshes the taxonomies cache.
     *
     * Clears the cached taxonomy definitions and forces a reload
     * from the latest sources (config and database).
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function refreshTaxonomiesCache(): void
    {
        Cache::forget( $this->cacheKey );
        $this->taxonomies = [];  // Clear current in-memory taxonomies
        $this->loadTaxonomies(); // Reloads merged taxonomies from fresh sources
    }

    /**
     * Deletes a user-defined taxonomy from the database.
     *
     * This will also delete all associated terms.
     *
     * @since 1.1.0
     *
     * @param string $handle The handle of the taxonomy to delete.
     * @return bool True if deleted, false if not found or on error.
     */
    public function deleteUserTaxonomy( string $handle ): bool
    {
        $deleted = Taxonomy::where( 'handle', $handle )->delete();
        if ( $deleted ) {
            $this->refreshTaxonomiesCache();
        }
        return (bool)$deleted;
    }
}

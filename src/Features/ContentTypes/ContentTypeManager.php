<?php

namespace ArtisanPackUI\CMSFramework\Features\ContentTypes;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\ContentType;
use Illuminate\Support\Facades\Cache;

//

/**
 * Manages the registration and retrieval of content type definitions.
 *
 * This class serves as the central registry for all content types
 * within the ArtisanPack UI CMS Framework, including built-in types
 * and dynamically created user-defined content types. It caches loaded
 * definitions for performance.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework
 * @since      1.1.0
 */
class ContentTypeManager
{
    /**
     * Array to hold merged content type definitions from config and database.
     *
     * @since 1.1.0
     * @var array
     */
    protected array $contentTypes = [];

    /**
     * Cache key for storing content type definitions.
     *
     * @since 1.1.0
     * @var string
     */
    protected string $cacheKey = 'cms.content_types.resolved';

    /**
     * Cache time-to-live in minutes (60 * 24 = 1 day).
     *
     * @since 1.1.0
     * @var int
     */
    protected int $cacheTtl = 60 * 24;

    /**
     * Constructor for the ContentTypeManager.
     *
     * Initializes the content type manager by loading definitions
     * from built-in sources (e.g., config) and user-defined sources (database).
     *
     * @since 1.1.0
     */
    public function __construct()
    {
        $this->loadContentTypes();
    }

    /**
     * Loads content type definitions from various sources (config, database).
     *
     * Merges built-in content type definitions (from config or hardcoded)
     * with user-defined content type definitions stored in the database.
     *
     * @since 1.1.0
     *
     * @return void
     */
    protected function loadContentTypes(): void
    {
        $builtInTypes = config( 'cms.content_types', [] ); // Load from cms config file
        foreach ( $builtInTypes as $handle => $definition ) {
            $this->registerContentType( $handle, $definition );
        }

        // Load user-defined types from the database and merge/override
        $dbDefinedTypes = Cache::remember( $this->cacheKey, $this->cacheTtl, function () {
            return ContentType::all()->keyBy( 'handle' )->map->definition->toArray();
        } );

        // Merge user-defined types (from DB) into existing built-in types
        // DB definitions will overwrite built-in if handles conflict.
        foreach ( $dbDefinedTypes as $handle => $definition ) {
            $this->contentTypes[ $handle ] = array_replace_recursive( $this->contentTypes[ $handle ] ?? [], $definition );
        }
    }

    /**
     * Registers a content type definition.
     *
     * This method allows programmatic registration of content types, typically used
     * for core or module-provided content types that are not meant to be user-editable
     * in their core definition.
     *
     * @since 1.1.0
     *
     * @param string $handle     A unique identifier for the content type (e.g., 'post', 'page').
     * @param array  $definition An associative array defining the content type's properties and fields.
     * @return void
     */
    public function registerContentType( string $handle, array $definition ): void
    {
        $this->contentTypes[ $handle ] = $definition;
    }

    /**
     * Provides access to the ContentTypeManager instance.
     *
     * This static method allows for convenient access to the singleton
     * instance of the ContentTypeManager from anywhere in the application.
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
     * Retrieves a content type definition by its handle.
     *
     * @since 1.1.0
     *
     * @param string $handle The unique handle of the content type.
     * @return array|null The content type definition, or null if not found.
     */
    public function getContentType( string $handle ): ?array
    {
        return $this->contentTypes[ $handle ] ?? null;
    }

    /**
     * Retrieves all registered content type definitions.
     *
     * @since 1.1.0
     *
     * @return array An associative array of all registered content types.
     */
    public function allContentTypes(): array
    {
        return $this->contentTypes;
    }

    /**
     * Adds or updates a user-defined content type in the database.
     *
     * This method is intended for programmatic creation/update of content types
     * that users can manage via an interface. It persists the definition to the database
     * and refreshes the cache.
     *
     * @since 1.1.0
     *
     * @param string $handle      The unique handle for the content type.
     * @param string $label       The singular human-readable label.
     * @param string $labelPlural The plural human-readable label.
     * @param string $slug        The URL slug.
     * @param array  $definition  The full definition array (including fields, supports, etc.).
     * @return ContentType          The created or updated ContentType model instance.
     */
    public function saveUserContentType( string $handle, string $label, string $labelPlural, string $slug, array $definition ): ContentType
    {
        $contentType = ContentType::updateOrCreate(
            [ 'handle' => $handle ],
            [
                'label'        => $label,
                'label_plural' => $labelPlural,
                'slug'         => $slug,
                'definition'   => $definition,
            ]
        );
        $this->refreshContentTypesCache();
        return $contentType;
    }

    /**
     * Refreshes the content types cache.
     *
     * Clears the cached content type definitions and forces a reload
     * from the latest sources (config and database).
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function refreshContentTypesCache(): void
    {
        Cache::forget( $this->cacheKey );
        $this->contentTypes = [];  // Clear current in-memory types
        $this->loadContentTypes(); // Reloads merged content types from fresh sources
    }

    /**
     * Deletes a user-defined content type from the database.
     *
     * This also removes content items associated with this type.
     *
     * @since 1.1.0
     *
     * @param string $handle The handle of the content type to delete.
     * @return bool True if deleted, false if not found or on error.
     */
    public function deleteUserContentType( string $handle ): bool
    {
        $deleted = ContentType::where( 'handle', $handle )->delete();
        if ( $deleted ) {
            // Also delete associated content items.
            // This assumes the `Content` model is in `App\Models` and `type` column references `ContentType` handle.
            // Be very careful with this in a production system. You might want to soft delete or reassign.
            Content::where( 'type', $handle )->delete();
            $this->refreshContentTypesCache();
        }
        return (bool)$deleted;
    }
}

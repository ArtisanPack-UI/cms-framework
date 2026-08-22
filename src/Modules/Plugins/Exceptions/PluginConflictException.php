<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Raised when a plugin declares a conflict with another plugin that is
 * installed and whose version falls within the declared conflict range.
 *
 * @since 2.9.0
 */
class PluginConflictException extends CMSFrameworkException
{
    /**
     * Slug of the plugin being activated.
     *
     * @since 2.9.0
     */
    public readonly string $pluginSlug;

    /**
     * The matched conflicts, each `['slug' => ..., 'constraint' => ..., 'installed' => ...]`.
     *
     * @since 2.9.0
     *
     * @var array<int,array{slug:string,constraint:string,installed:string}>
     */
    public readonly array $conflicts;

    /**
     * Construct with the plugin slug and the conflicts it triggered.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin being activated.
     * @param  array<int,array{slug:string,constraint:string,installed:string}>  $conflicts  Matched conflicts.
     */
    public function __construct( string $slug, array $conflicts )
    {
        $names = implode( ', ', array_column( $conflicts, 'slug' ) );
        parent::__construct( "Plugin '{$slug}' conflicts with installed plugins: {$names}." );
        $this->pluginSlug = $slug;
        $this->conflicts  = $conflicts;
    }

    /**
     * Named-constructor factory matching sibling plugin exceptions.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin being activated.
     * @param  array<int,array{slug:string,constraint:string,installed:string}>  $conflicts  Matched conflicts.
     *
     * @return self
     */
    public static function forConflicts( string $slug, array $conflicts ): self
    {
        return new self( $slug, $conflicts );
    }
}

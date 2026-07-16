<?php

declare( strict_types=1 );

/**
 * Hello World Plugin Service Provider.
 *
 * Reference implementation showing how to extend the framework's base
 * `PluginServiceProvider` and register admin pages, nav entries, hook
 * subscriptions, a custom field type, and an edit-screen panel.
 *
 * @since 1.0.0
 */

namespace HelloWorld;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginServiceProvider;

final class HelloWorldServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        // Bind plugin services on the container. Nothing to bind for the
        // reference plugin; keep the method as an anchor for real plugins.
    }

    public function boot(): void
    {
        $this->loadViewsFrom( $this->pluginPath( 'resources/views' ), 'hello-world' );

        $this->registerAdminPage( 'hello-world', [
            'title'      => __( 'Hello World' ),
            'section'    => 'tools',
            'view'       => 'hello-world::admin.index',
            'capability' => 'access_admin_dashboard',
            'icon'       => 'fas.puzzle-piece',
            'order'      => 60,
        ] );

        $this->registerNavEntry( [
            'slug'       => 'hello-world',
            'label'      => __( 'Hello World' ),
            'url'        => '/admin/hello-world',
            'icon'       => 'fas.puzzle-piece',
            'permission' => 'access_admin_dashboard',
            'order'      => 60,
        ] );

        $this->registerFederatedModule(
            'helloWorldAdmin',
            $this->pluginPath( 'dist/remoteEntry.js' ),
            ['./HelloWorldPanel', './MapPickerEditor'],
        );

        $this->registerFieldTypes();
        $this->registerEditPanels();
        $this->registerHookSubscriptions();
    }

    /**
     * Add a `map_picker` field type that stores its value as a lat/lng string
     * on the record's `metadata` JSON column.
     */
    protected function registerFieldTypes(): void
    {
        apRegisterFieldType( 'map_picker', [
            'label'              => __( 'Map Picker' ),
            'column_type'        => 'string',
            'validation_rules'   => ['string', 'regex:/^-?\d+\.\d+,-?\d+\.\d+$/'],
            'editor_component'   => 'helloWorldAdmin/MapPickerEditor',
            'renderer_component' => 'helloWorldAdmin/MapPickerRenderer',
            'meta'               => ['icon' => 'fas.map', 'category' => 'location'],
        ] );
    }

    /**
     * Add a sidebar panel to every post/page edit screen.
     */
    protected function registerEditPanels(): void
    {
        addFilter( 'ap.admin.contentEdit.panels', function ( array $panels ): array {
            $panels[] = [
                'slug'         => 'hello-world.notes',
                'title'        => __( 'Hello World Notes' ),
                'component'    => 'helloWorldAdmin/HelloWorldPanel',
                'contentTypes' => ['post', 'page'],
                'order'        => 45,
                'capability'   => 'access_admin_dashboard',
            ];

            return $panels;
        } );
    }

    /**
     * Log a friendly greeting whenever a content type is created — the smallest
     * possible demonstration of a plugin subscribing to a framework hook.
     */
    protected function registerHookSubscriptions(): void
    {
        addAction( 'ap.contentTypes.created', static function ( $contentType ): void {
            logger()->info( 'Hello World says hi to a new content type: ' . $contentType->slug );
        } );
    }
}

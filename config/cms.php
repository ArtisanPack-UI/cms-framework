<?php
/**
 * CMS Framework Configuration
 *
 * This file contains the default configuration settings for the CMS Framework.
 * These settings can be overridden by the application or through the settings API.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Config
 * @since      1.0.0
 */

return [
    'site'          => [
        'name'     => 'ArtisanPack UI CMS Framework',
        'tagline'  => 'A flexible framework to build a CMS for your website.',
        'url'      => env( 'APP_URL', 'http://localhost' ),
        'timezone' => 'UTC',
        'locale'   => 'en',
    ],
    'paths'         => [
        'plugins' => base_path( 'plugins' ),  // Path name changed from 'cms-plugins'
        'themes'  => base_path( 'themes' ),   // Path name changed from 'cms-themes'
    ],
    'media'         => [
        'disk'      => env( 'MEDIA_DISK', 'public' ),     // Default to 'public' disk
        'directory' => env( 'MEDIA_DIRECTORY', 'media' ), // Default storage directory within the disk
    ],
    'content_types' => [
        // Built-in Post Type
        'post' => [
            'label'        => 'Post',
            'label_plural' => 'Posts',
            'slug'         => 'posts',
            'public'       => true,
            'hierarchical' => false,
            'supports'     => [ 'title', 'content', 'author', 'featured_image', 'status', 'categories', 'tags' ],
            'fields'       => [], // Core fields, no special meta fields here
        ],
        // Built-in Page Type
        'page' => [
            'label'        => 'Page',
            'label_plural' => 'Pages',
            'slug'         => 'pages',
            'public'       => true,
            'hierarchical' => true,
            'supports'     => [ 'title', 'content', 'author', 'status', 'parent', 'order' ],
            'fields'       => [],
        ],
    ],
    'taxonomies'    => [
        // Built-in Category Taxonomy
        'category' => [
            'label'         => 'Category',
            'label_plural'  => 'Categories',
            'hierarchical'  => true,
            'content_types' => [ 'post' ], // Applies to 'post' content type by default
        ],
        // Built-in Tag Taxonomy
        'tag'      => [
            'label'         => 'Tag',
            'label_plural'  => 'Tags',
            'hierarchical'  => false,
            'content_types' => [ 'post' ], // Applies to 'post' content type by default
        ],
    ],
    'theme'         => [
        'active' => env( 'CMS_ACTIVE_THEME', 'default-artisanpack-theme' ), // Default theme name.
    ],
];

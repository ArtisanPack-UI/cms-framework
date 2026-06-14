<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This is the model that your application uses for users. It will be used
    | by the CMS framework to establish relationships and handle user logic.
    |
    | REQUIRED: You must publish this config and set this to your User model.
    | Example: 'user_model' => \App\Models\User::class,
    |
    | To publish: php artisan vendor:publish --tag=cms-framework-config
    |
    */
    'user_model' => null,

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Documentation
    |--------------------------------------------------------------------------
    |
    | Configure the auto-generated OpenAPI specification for the CMS Framework
    | API. When enabled, interactive documentation is available at /docs/api/cms
    | and the raw spec at /docs/api/cms.json.
    |
    */
    'openapi' => [
        'enabled' => env('CMS_OPENAPI_ENABLED', false),

        'info' => [
            'title'       => 'ArtisanPack CMS Framework API',
            'version'     => '2.2.3',
            'description' => 'RESTful API for the ArtisanPack CMS Framework. Provides endpoints for managing posts, pages, content types, users, roles, permissions, settings, notifications, plugins, and themes.',
        ],

        /*
        |--------------------------------------------------------------------------
        | Documentation UI Path
        |--------------------------------------------------------------------------
        |
        | The path where the Swagger UI documentation will be served.
        |
        */
        'ui_path' => '/docs/api/cms',

        /*
        |--------------------------------------------------------------------------
        | OpenAPI Document Path
        |--------------------------------------------------------------------------
        |
        | The path where the raw OpenAPI JSON specification will be served.
        |
        */
        'document_path' => '/docs/api/cms.json',
    ],
];

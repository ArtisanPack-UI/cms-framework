# OpenAPI Specification

The CMS Framework provides auto-generated OpenAPI 3.x API documentation powered by [Scramble](https://scramble.dedoc.co). Introduced in v1.1.0, it gives you a Swagger UI for interactive exploration and a machine-readable JSON specification for SDK generation and tooling.

## Configuration

The OpenAPI module is configured in `config/artisanpack/cms-framework.php` under the `openapi` key:

```php
'openapi' => [
    'enabled' => env( 'CMS_OPENAPI_ENABLED', false ),

    'info' => [
        'title'       => 'ArtisanPack CMS Framework API',
        'version'     => '1.1.0',
        'description' => 'RESTful API for the ArtisanPack CMS Framework. Provides endpoints for managing posts, pages, content types, users, roles, permissions, settings, notifications, plugins, and themes.',
    ],

    'ui_path'       => '/docs/api/cms',
    'document_path' => '/docs/api/cms.json',
],
```

### Configuration Options

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `false` | Whether the Swagger UI and JSON spec endpoints are registered. Controlled by the `CMS_OPENAPI_ENABLED` environment variable. |
| `info.title` | `ArtisanPack CMS Framework API` | The title displayed in the Swagger UI header. |
| `info.version` | `1.1.0` | The API version shown in the specification. |
| `info.description` | *(see config)* | A description of the API included in the specification. |
| `ui_path` | `/docs/api/cms` | The URL path where Swagger UI is served. |
| `document_path` | `/docs/api/cms.json` | The URL path where the raw OpenAPI JSON document is served. |

## Enabling the Documentation

Set the environment variable in your `.env` file:

```
CMS_OPENAPI_ENABLED=true
```

Once enabled, two routes become available:

- **Swagger UI**: Visit the `ui_path` (default: `/docs/api/cms`) in your browser for an interactive API explorer.
- **JSON Spec**: Fetch the `document_path` (default: `/docs/api/cms.json`) to get the raw OpenAPI 3.x specification as JSON.

## Route Filtering

The OpenAPI specification only includes routes that belong to the CMS Framework. A route is included when:

1. Its URI starts with `api/v1`.
2. Its controller lives in the `ArtisanPackUI\CMSFramework\` namespace.

Application-specific routes and routes from other packages are excluded.

## Security Scheme

The generated specification declares an HTTP Bearer authentication scheme using JWT. All endpoints are marked as requiring this scheme, matching the Sanctum-based authentication used by the CMS Framework API.

## Artisan Command

Export the OpenAPI specification to a static JSON file using the `cms:openapi:export` command:

```bash
# Export to the default location (cms-openapi.json in the project root)
php artisan cms:openapi:export

# Export to a custom path
php artisan cms:openapi:export storage/app/api-spec.json

# Pretty-print the output
php artisan cms:openapi:export --pretty

# Combine both options
php artisan cms:openapi:export docs/openapi.json --pretty
```

This is useful for:

- Committing the specification to version control
- Publishing the spec to external documentation platforms
- Generating client SDKs with tools like [OpenAPI Generator](https://openapi-generator.tech)

## Usage Examples

**Access the interactive documentation:**

```
https://your-app.test/docs/api/cms
```

**Fetch the raw specification:**

```bash
curl https://your-app.test/docs/api/cms.json
```

**Generate a TypeScript client (using OpenAPI Generator):**

```bash
php artisan cms:openapi:export --pretty storage/app/cms-openapi.json

npx @openapitools/openapi-generator-cli generate \
  -i storage/app/cms-openapi.json \
  -g typescript-fetch \
  -o generated/cms-client
```

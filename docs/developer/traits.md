# Traits Developer Guide

## Overview

CMS Framework v1.1.0 introduces several shared traits to eliminate code duplication across models and managers. These traits extract common behavior into reusable concerns that can be applied to any model or manager that needs them.

## Table of Contents

- [HasContentStatus](#hascontentstatus)
- [HasContentFilters](#hascontentfilters)
- [HasManifestParsing](#hasmanifestparsing)
- [HasIncludableRelationships](#hasincludablerelationships)

## HasContentStatus

**Namespace:** `ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasContentStatus`

Provides shared content status scopes and helper methods for content type models. Applied to the Post and Page models.

### Methods

#### `scopePublished(Builder $query)`

Scopes a query to only include published content. A record is considered published when its `status` is `ContentStatus::Published` AND its `published_at` is either null or in the past.

#### `scopeDraft(Builder $query)`

Scopes a query to only include draft content (status is `ContentStatus::Draft`).

#### `isPublished(): bool`

Returns `true` if the model instance is published. Checks both the status value and the `published_at` timestamp.

### Usage Examples

**Applying the Trait to a Model:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasContentStatus;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasContentStatus;

    protected function casts(): array
    {
        return [
            'status'       => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
```

**Querying Published Content:**

```php
// Get all published articles
$published = Article::published()->get();

// Chain with other scopes
$recentPublished = Article::published()
    ->orderBy( 'published_at', 'desc' )
    ->limit( 10 )
    ->get();
```

**Querying Draft Content:**

```php
$drafts = Article::draft()->get();
```

**Checking a Single Instance:**

```php
$article = Article::find( 1 );

if ( $article->isPublished() ) {
    // Display to public visitors
}
```

**How Published Scope Works:**

The `scopePublished` method applies two conditions:

1. The `status` column must equal `ContentStatus::Published`.
2. The `published_at` column must be either null (published immediately) or a date in the past.

This means a post with `status = 'published'` and `published_at` set to a future date will not appear in published queries until that date has passed.

## HasContentFilters

**Namespace:** `ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\Concerns\HasContentFilters`

Provides shared content query filter logic for content managers. Applied to BlogManager and PageManager.

### Methods

#### `applyStatusFilter(Builder $query, array $filters, bool $defaultToPublished = true): void`

Applies a status filter to the query based on the `$filters['status']` value. Accepts either a `ContentStatus` enum instance or a string value. When the status is `'published'` or unrecognized, it uses the `published()` scope. When no status is provided and `$defaultToPublished` is `true`, it defaults to the published scope.

#### `applySearchFilter(Builder $query, array $filters): void`

Applies a search filter from `$filters['search']` across the `title`, `content`, and `excerpt` columns. Special characters (`\`, `%`, `_`) are escaped for safe LIKE queries.

#### `applyAuthorFilter(Builder $query, array $filters): void`

Applies an author filter from `$filters['author']` using the model's `byAuthor` scope.

### Usage Examples

**Using in a Content Manager:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\Concerns\HasContentFilters;

class ArticleManager
{
    use HasContentFilters;

    public function list( array $filters = [] ): Collection
    {
        $query = Article::query();

        $this->applyStatusFilter( $query, $filters );
        $this->applySearchFilter( $query, $filters );
        $this->applyAuthorFilter( $query, $filters );

        return $query->latest()->get();
    }
}
```

**Filtering by Status with an Enum:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;

$articles = $manager->list( [
    'status' => ContentStatus::Draft,
] );
```

**Filtering by Status with a String:**

```php
$articles = $manager->list( [
    'status' => 'draft',
] );
```

**Searching Content:**

```php
$results = $manager->list( [
    'search' => 'Laravel tutorial',
] );
```

**Combining Filters:**

```php
$results = $manager->list( [
    'status' => 'published',
    'search' => 'getting started',
    'author' => $userId,
] );
```

**Disabling Default Published Filter:**

```php
// In an admin context where you want to see all statuses by default
$this->applyStatusFilter( $query, $filters, false );
```

## HasManifestParsing

**Namespace:** `ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns\HasManifestParsing`

Provides shared manifest parsing, slug validation, and path traversal prevention for package managers. Applied to PluginManager and ThemeManager.

### Security Focus

This trait includes security measures to prevent path traversal attacks when discovering plugins and themes from the filesystem. It validates slugs against a strict pattern and resolves real filesystem paths to ensure they remain within the expected base directory.

### Methods

#### `parseManifest(string $manifestPath): ?array`

Reads and decodes a JSON manifest file (e.g., `plugin.json` or `theme.json`). Returns the parsed array on success, or `null` if the file does not exist or contains invalid JSON.

#### `validateSlug(string $slug): bool`

Validates that a slug only contains alphanumeric characters, hyphens, and underscores. Uses the pattern `/^[a-zA-Z0-9_-]+$/`. Returns `false` for slugs containing path separators, dots, or other special characters.

#### `resolveSecurePath(string $itemPath, string $basePath): ?string`

Resolves the real filesystem path and verifies it is contained within the expected base directory. Returns the resolved real path on success, or `null` if the path does not exist or is outside the base directory.

### Usage Examples

**Using in a Package Manager:**

```php
use ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns\HasManifestParsing;

class WidgetManager
{
    use HasManifestParsing;

    protected string $basePath;

    public function __construct()
    {
        $this->basePath = base_path( 'widgets' );
    }

    public function discover(): array
    {
        $widgets = [];

        foreach ( glob( $this->basePath . '/*', GLOB_ONLYDIR ) as $dir ) {
            $slug = basename( $dir );

            if ( ! $this->validateSlug( $slug ) ) {
                continue;
            }

            $securePath = $this->resolveSecurePath( $dir, $this->basePath );
            if ( null === $securePath ) {
                continue;
            }

            $manifest = $this->parseManifest( $securePath . '/widget.json' );
            if ( null === $manifest ) {
                continue;
            }

            $widgets[$slug] = $manifest;
        }

        return $widgets;
    }
}
```

**Validating Slugs:**

```php
$this->validateSlug( 'my-plugin' );    // true
$this->validateSlug( 'my_plugin' );    // true
$this->validateSlug( 'MyPlugin123' );  // true
$this->validateSlug( '../etc/passwd' ); // false
$this->validateSlug( 'plugin.bak' );   // false
```

**Preventing Path Traversal:**

```php
$basePath = '/var/www/plugins';

// Valid path within the base directory
$result = $this->resolveSecurePath( '/var/www/plugins/my-plugin', $basePath );
// Returns: '/var/www/plugins/my-plugin'

// Attempted traversal
$result = $this->resolveSecurePath( '/var/www/plugins/../../etc', $basePath );
// Returns: null
```

**Parsing a Manifest File:**

```php
$manifest = $this->parseManifest( base_path( 'plugins/my-plugin/plugin.json' ) );

if ( null === $manifest ) {
    // File missing or invalid JSON
    return;
}

$name    = $manifest['name'] ?? 'Unknown';
$version = $manifest['version'] ?? '0.0.0';
```

## HasIncludableRelationships

**Namespace:** `ArtisanPackUI\CMSFramework\Http\Controllers\Concerns\HasIncludableRelationships`

Provides support for dynamically including Eloquent relationships in API responses via query parameters. This trait is used by API controllers to allow clients to request related data using an `include` query parameter.

For full details on how includable relationships work, see the [Includable Relationships API documentation](../api/README.md).

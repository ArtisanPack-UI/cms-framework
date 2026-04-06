# Includable Relationships

The `HasIncludableRelationships` trait provides on-demand relationship loading via the `?include=` query parameter. Introduced in v1.1.0, it allows API consumers to control which relationships are eager-loaded in a response while maintaining backwards compatibility through default includes.

## How It Works

Controllers using this trait define two properties:

- **`$includableRelationships`** -- An allowlist of relationship names that the API consumer is permitted to request.
- **`$defaultIncludes`** -- An array of relationships to load when the `include` query parameter is absent, preserving backwards-compatible behavior.

The trait provides a single method, `getRequestedIncludes()`, that the controller calls when building a query to determine which relationships to eager-load.

## Behavior

| Request | Result |
|---------|--------|
| No `include` parameter | Loads `$defaultIncludes` (backwards compatible) |
| `?include=roles` | Loads only `roles` (if it is in the allowlist) |
| `?include=roles,permissions` | Loads `roles` and `permissions` (both must be in the allowlist) |
| `?include=` (empty string) | Loads **no** relationships |
| `?include=roles,invalid` | Loads only `roles`; `invalid` is silently filtered out |

Invalid relationship names are silently ignored -- they never produce an error. This prevents leaking information about internal model structure and provides a stable API surface.

## Currently Used On

The trait is currently applied to these controllers:

| Controller | Includable Relationships | Default Includes |
|------------|------------------------|------------------|
| `UserController` | `roles` | `roles` |
| `RoleController` | `permissions` | `permissions` |
| `PermissionController` | `roles` | `roles` |
| `PostController` | varies | varies |
| `PageController` | varies | varies |
| `PostCategoryController` | varies | varies |
| `PageCategoryController` | varies | varies |

## Example API Calls

**Load users with their roles (default behavior -- no parameter needed):**

```bash
curl -H "Authorization: Bearer TOKEN" \
  https://your-app.test/api/v1/users
```

**Explicitly request roles:**

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://your-app.test/api/v1/users?include=roles"
```

**Load users with no relationships (override the default):**

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://your-app.test/api/v1/users?include="
```

**Request multiple relationships (on a controller that supports them):**

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://your-app.test/api/v1/roles?include=permissions"
```

## Adding to Your Own Controllers

1. Import and use the trait in your controller.
2. Define `$includableRelationships` with the allowed relationship names.
3. Define `$defaultIncludes` with the relationships to load by default.
4. Call `$this->getRequestedIncludes( $request )` when building your query.

```php
<?php

namespace App\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Controllers\Concerns\HasIncludableRelationships;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use HasIncludableRelationships;

    /**
     * Relationships the API consumer may request.
     *
     * @var array<int, string>
     */
    protected array $includableRelationships = ['category', 'tags', 'reviews'];

    /**
     * Relationships loaded when no include parameter is provided.
     *
     * @var array<int, string>
     */
    protected array $defaultIncludes = ['category'];

    public function index( Request $request ): JsonResponse
    {
        $includes = $this->getRequestedIncludes( $request );

        $products = Product::query()
            ->with( $includes )
            ->paginate();

        return response()->json( $products );
    }
}
```

With this setup:

- `GET /products` loads products with `category` (the default).
- `GET /products?include=tags,reviews` loads products with `tags` and `reviews` only.
- `GET /products?include=` loads products with no relationships.
- `GET /products?include=category,nonexistent` loads products with `category` only; `nonexistent` is silently dropped.

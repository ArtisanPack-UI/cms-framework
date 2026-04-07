# Bulk Actions

The CMS Framework provides bulk action API endpoints for performing operations on multiple resources at once. Introduced in v1.1.0, bulk endpoints are available for posts, pages, and users.

## Overview

Bulk actions allow you to apply the same operation (such as deleting or publishing) to a set of resource IDs in a single request. Each item is individually authorized through its corresponding policy, and partial failures are tracked so you always know which items succeeded and which did not.

## Endpoints

| Endpoint | Allowed Actions |
|----------|----------------|
| `POST /api/v1/posts/bulk` | `delete`, `publish`, `draft` |
| `POST /api/v1/pages/bulk` | `delete`, `publish`, `draft` |
| `POST /api/v1/users/bulk` | `delete`, `activate`, `deactivate` |

All endpoints require authentication.

## Request Format

Send a JSON body with an `action` string and an `ids` array:

```json
{
  "action": "publish",
  "ids": [1, 2, 3]
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `action` | Required, string, must be one of the endpoint's allowed actions |
| `ids` | Required, array, must contain at least one item |
| `ids.*` | Required, integer, must exist in the corresponding database table |

If validation fails, a `422` response is returned with field-level error messages.

## Response Format

### Success

```json
{
  "processed": 3,
  "failed": 0,
  "errors": []
}
```

### Partial Failure

When some items fail (for example, due to authorization), the response includes details for each failure keyed by ID:

```json
{
  "processed": 2,
  "failed": 1,
  "errors": {
    "5": "You do not have permission to publish this post."
  }
}
```

### Common Error Scenarios

- **Item not found** -- If an ID passes validation but the resource cannot be loaded, the error message will indicate it was not found.
- **Authorization failure** -- Each item is authorized individually via the relevant policy. Unauthorized items are skipped and recorded in the `errors` object.
- **Execution failure** -- If the action itself throws an exception (for example, a database constraint), the error is caught, reported, and recorded.

## Authorization

Bulk actions do **not** use a single blanket authorization check. Instead, each item in the `ids` array is authorized individually against the resource's policy. This means a user might be able to delete some posts but not others, depending on ownership or role.

The policy method used is determined by the action:

- `delete` checks the `delete` policy method
- `publish` checks the `publish` policy method
- `draft` checks the `draft` policy method
- `activate` / `deactivate` check their respective policy methods

## Examples

### cURL

**Publish multiple posts:**

```bash
curl -X POST https://your-app.test/api/v1/posts/bulk \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "publish",
    "ids": [1, 2, 3]
  }'
```

**Delete multiple users:**

```bash
curl -X POST https://your-app.test/api/v1/users/bulk \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "delete",
    "ids": [10, 11, 12]
  }'
```

### PHP (Laravel HTTP Client)

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken( $token )
    ->post( 'https://your-app.test/api/v1/pages/bulk', [
        'action' => 'draft',
        'ids'    => [4, 5, 6],
    ] );

$result = $response->json();
// $result['processed'] -- number of successfully processed items
// $result['failed']    -- number of items that failed
// $result['errors']    -- associative array of ID => error message
```

### JavaScript (Fetch)

```javascript
const response = await fetch('/api/v1/posts/bulk', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    action: 'publish',
    ids: [1, 2, 3],
  }),
});

const result = await response.json();
console.log(`Processed: ${result.processed}, Failed: ${result.failed}`);
```

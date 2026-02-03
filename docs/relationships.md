# Model Relationships

This document provides a comprehensive overview of all Eloquent model relationships in the CMS Framework.

## Relationship Type Hints

All relationship methods in the framework use explicit return type hints for better IDE support and type safety:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}

public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'author_id');
}

public function categories(): BelongsToMany
{
    return $this->belongsToMany(Category::class, 'post_categories');
}
```

---

## Users Module

### User Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `roles()` | BelongsToMany | Role | User's assigned roles |
| `permissions()` | BelongsToMany | Permission | User's direct permissions |
| `notifications()` | BelongsToMany | Notification | User's notifications (via pivot) |
| `notificationPreferences()` | HasMany | NotificationPreference | User's notification preferences |
| `posts()` | HasMany | Post | Posts authored by user |
| `pages()` | HasMany | Page | Pages authored by user |

**Pivot Tables:**
- `role_user` - User-Role relationship
- `permission_user` - User-Permission relationship
- `notification_user` - User-Notification relationship with metadata (is_read, is_dismissed, read_at)

### Role Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `users()` | BelongsToMany | User | Users with this role |
| `permissions()` | BelongsToMany | Permission | Permissions granted to role |

**Pivot Tables:**
- `role_user`
- `permission_role`

### Permission Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `roles()` | BelongsToMany | Role | Roles with this permission |
| `users()` | BelongsToMany | User | Users with direct permission |

**Pivot Tables:**
- `permission_role`
- `permission_user`

---

## Blog Module

### Post Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `author()` | BelongsTo | User | Post author |
| `featuredImageMedia()` | BelongsTo | Media | Featured image (if media library used) |
| `categories()` | BelongsToMany | PostCategory | Post categories |
| `tags()` | BelongsToMany | PostTag | Post tags |

**Pivot Tables:**
- `post_categories` - Post-Category relationship
- `post_tags` - Post-Tag relationship

**Foreign Keys:**
- `author_id` → users.id
- `featured_image_id` → media.id (optional)

### PostCategory Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `posts()` | BelongsToMany | Post | Posts in this category |
| `parent()` | BelongsTo | PostCategory | Parent category |
| `children()` | HasMany | PostCategory | Child categories |

**Foreign Keys:**
- `parent_id` → post_categories.id (nullable)

### PostTag Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `posts()` | BelongsToMany | Post | Posts with this tag |

---

## Pages Module

Structure identical to Blog module with pages instead of posts:

### Page Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `author()` | BelongsTo | User | Page author |
| `featuredImageMedia()` | BelongsTo | Media | Featured image |
| `categories()` | BelongsToMany | PageCategory | Page categories |
| `tags()` | BelongsToMany | PageTag | Page tags |
| `parent()` | BelongsTo | Page | Parent page |
| `children()` | HasMany | Page | Child pages |

**Additional Features:**
- Hierarchical page structure (parent-child)
- Template support

---

## Content Types Module

### ContentType Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `customFields()` | HasMany | CustomField | Custom fields for this type |
| `taxonomies()` | HasMany | Taxonomy | Taxonomies for this type |

### CustomField Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `contentType()` | BelongsTo | ContentType | Parent content type |

### Taxonomy Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `contentType()` | BelongsTo | ContentType | Parent content type |
| `parent()` | BelongsTo | Taxonomy | Parent taxonomy |
| `children()` | HasMany | Taxonomy | Child taxonomies |

**Hierarchical Structure**: Taxonomies support parent-child relationships

---

## Notifications Module

### Notification Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `users()` | BelongsToMany | User | Users who received notification |

**Pivot Table**: `notification_user`

**Pivot Columns:**
- `is_read` (boolean)
- `is_dismissed` (boolean)
- `read_at` (timestamp, nullable)
- `dismissed_at` (timestamp, nullable)

**Example Usage:**

```php
// Get user's unread notifications
$notifications = $user->notifications()
    ->wherePivot('is_read', false)
    ->wherePivot('is_dismissed', false)
    ->get();

// Mark as read
$user->notifications()->updateExistingPivot($notificationId, [
    'is_read' => true,
    'read_at' => now(),
]);
```

### NotificationPreference Model

**Relationships:**

| Method | Type | Related Model | Description |
|--------|------|---------------|-------------|
| `user()` | BelongsTo | User | User with this preference |

**Foreign Keys:**
- `user_id` → users.id

---

## Plugins Module (Experimental)

### Plugin Model

**No direct relationships** - Plugins are standalone entities tracked by:
- `slug` (unique identifier)
- `version`
- `is_active` (boolean)
- `activated_at` (timestamp)

---

## Settings Module

### Setting Model

**No relationships** - Settings are key-value pairs:
- `key` (unique)
- `value`
- `type`
- `description`

---

## Relationship Traits

### HasRolesAndPermissions Trait

**Location**: `src/Modules/Users/Models/Concerns/HasRolesAndPermissions.php`

**Provides Methods:**
- `roles()` - BelongsToMany relationship
- `permissions()` - BelongsToMany relationship
- `hasRole(string|array $role): bool`
- `hasPermission(string $permission): bool`
- `hasAnyRole(array $roles): bool`
- `hasAllRoles(array $roles): bool`

**Usage:**

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
}
```

### HasNotifications Trait

**Location**: `src/Modules/Notifications/Models/Concerns/HasNotifications.php`

**Provides Methods:**
- `notifications()` - BelongsToMany relationship
- `unreadNotifications()` - Query scope
- `notificationPreferences()` - HasMany relationship
- `shouldReceiveNotification(string $key): bool`
- `shouldReceiveNotificationEmail(string $key): bool`

**Usage:**

```php
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Concerns\HasNotifications;

class User extends Authenticatable
{
    use HasNotifications;
}
```

### HasCustomFields Trait

**Location**: `src/Modules/ContentTypes/Models/Concerns/HasCustomFields.php`

**Provides Methods:**
- `customFieldValues()` - Polymorphic relationship
- `getCustomField(string $key): mixed`
- `setCustomField(string $key, mixed $value): void`

**Usage:**

```php
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasCustomFields;

class Post extends Model
{
    use HasCustomFields;
}
```

### HasFeaturedImage Trait

**Location**: `src/Modules/ContentTypes/Models/Concerns/HasFeaturedImage.php`

**Provides Methods:**
- `featuredImageMedia()` - BelongsTo relationship
- `getFeaturedImageUrl(): ?string`
- `setFeaturedImage(int $mediaId): void`

---

## Polymorphic Relationships

### Featurable (HasFeaturedImage)

Many models can have a featured image through a polymorphic relationship:

```php
public function featuredImage(): MorphTo
{
    return $this->morphTo();
}
```

**Models Using This:**
- Post
- Page
- (Any model using HasFeaturedImage trait)

---

## Eager Loading

### Best Practices

Always eager load relationships to avoid N+1 queries:

```php
// Bad - N+1 problem
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name;  // Separate query for each post
}

// Good - Eager loading
$posts = Post::with('author')->get();
foreach ($posts as $post) {
    echo $post->author->name;  // No additional queries
}
```

### Common Eager Loading Patterns

```php
// Load multiple relationships
$posts = Post::with(['author', 'categories', 'tags'])->get();

// Nested eager loading
$users = User::with(['roles.permissions'])->get();

// Conditional eager loading
$posts = Post::with(['categories' => function ($query) {
    $query->where('slug', 'news');
}])->get();

// Count relationships
$posts = Post::withCount(['categories', 'tags'])->get();
```

---

## Relationship Queries

### Querying Relationships

```php
// Has relationship
$users = User::has('posts')->get();

// Has relationship with count
$users = User::has('posts', '>=', 5)->get();

// WhereHas with conditions
$users = User::whereHas('posts', function ($query) {
    $query->where('status', 'published');
})->get();

// Doesn't have relationship
$users = User::doesntHave('posts')->get();
```

### Pivot Data Access

```php
// Access pivot data
$user = User::find(1);
foreach ($user->roles as $role) {
    echo $role->pivot->created_at;
}

// Query pivot data
$user->roles()->wherePivot('created_at', '>', now()->subDays(30))->get();

// Update pivot data
$user->roles()->updateExistingPivot($roleId, ['updated_at' => now()]);
```

---

## Foreign Key Conventions

The framework follows Laravel's foreign key naming conventions:

- **Singular model name + `_id`**: `author_id`, `role_id`, `user_id`
- **Polymorphic**: `{relation_name}_type` and `{relation_name}_id`

### Custom Foreign Keys

When foreign keys don't follow convention, they're explicitly defined:

```php
public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'author_id');
}
```

---

## Relationship Diagrams

### Users & Roles System

```
User ←→ Role (many-to-many via role_user)
User ←→ Permission (many-to-many via permission_user)
Role ←→ Permission (many-to-many via permission_role)
```

### Content Relationships

```
User ←── Post (one-to-many via author_id)
Post ←→ PostCategory (many-to-many via post_categories)
Post ←→ PostTag (many-to-many via post_tags)
PostCategory ←→ PostCategory (hierarchical via parent_id)
```

### Notification System

```
User ←→ Notification (many-to-many via notification_user)
User ←── NotificationPreference (one-to-many)
```

---

## Testing Relationships

### Factory Usage

```php
// Create with relationships
$post = Post::factory()
    ->for(User::factory(), 'author')
    ->hasCategories(3)
    ->hasTags(5)
    ->create();

// Create through relationship
$user = User::factory()
    ->has(Post::factory()->count(10))
    ->create();
```

### Relationship Assertions

```php
test('post belongs to author', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();

    expect($post->author)->toBeInstanceOf(User::class)
        ->and($post->author->id)->toBe($user->id);
});

test('user has many posts', function () {
    $user = User::factory()
        ->has(Post::factory()->count(3))
        ->create();

    expect($user->posts)->toHaveCount(3)
        ->each->toBeInstanceOf(Post::class);
});
```

---

## See Also

- [Models Documentation](Models)
- [Database Migrations](Migrations)
- [Query Scopes](Scopes)
- [Testing Guide](Testing)

# Upgrading the cms-framework Users module to artisanpack-ui/rbac

The cms-framework `Users` module previously shipped its own `Role`, `Permission`, and `HasRolesAndPermissions` implementation — schema, models, trait, observers, and migrations. Wave 4 of the ArtisanPack UI orchestration plan retires those duplicates in favor of the shared `artisanpack-ui/rbac` package; this version of cms-framework subclasses the rbac base everywhere instead.

If you're upgrading an existing application, work through the sections below in order. Most apps will only need to install the new dependency and run migrations.

## What changed

- `Role` and `Permission` now extend the rbac base models. The cms-framework subclass is preserved — and registered against `artisanpack.rbac.models.{role,permission}` — so existing imports keep working.
- `HasRolesAndPermissions` now composes rbac's `HasRoles` + `HasPermissions` traits. The same name on the same path; consumer User models don't need to change their `use` statement.
- The three RBAC migrations (`create_roles_table`, `create_permissions_table`, `create_user_role_permission_pivots`) shipped by cms-framework are **gone**. rbac owns the schema now and its migrations are guarded with `Schema::hasTable()` so they're idempotent against an already-migrated database.
- The bundled `RolesTableSeeder` + `PermissionsTableSeeder` are now **opt-in**. The default `DatabaseSeeder` only runs `SettingsTableSeeder`.
- `Users` API routes (`users`, `roles`, `permissions`, `users/bulk`) are now gated behind the `auth` middleware (bug fix #129).
- `PermissionController` now calls `authorizeResource()` so it consults `PermissionPolicy` like every other controller in the framework (bug fix #127).
- `PermissionPolicy` methods now take `Authenticatable $user` (Laravel's standard signature) instead of `string|int $id` (bug fix #128).
- New: a `role:` route middleware alias to pair with rbac's `permission:` alias (bug fix #131).
- `roles` and `permissions` schemas now have a `description` column (bug fix #130 — courtesy of rbac's base schema).

## Step-by-step upgrade

### 1. Install the dependency

```bash
composer require artisanpack-ui/rbac:^0.1
```

### 2. Run migrations

```bash
php artisan migrate
```

If your database was previously migrated by cms-framework's old RBAC migrations, the rbac migrations will detect the existing tables and no-op. The `description` column on `roles` and `permissions`, and the `slug` column added in rbac 0.1+, are added by their own migrations against an in-place table — no manual ALTER needed.

If the rbac slug column needs to backfill data from an existing `slug`-bearing schema, that's already true for cms-framework consumers (cms-framework had `slug`). The rbac auto-derivation kicks in only when a row is saved with an empty slug; existing data is left alone.

### 3. Update consumer User models — usually nothing

If your User model was already importing `HasRolesAndPermissions` from cms-framework:

```php
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;
```

…there's nothing to change. The trait still exists at the same path; internally it now composes rbac's traits.

### 4. Update direct rbac model usage — usually nothing

If you imported `Role` or `Permission` from `ArtisanPackUI\CMSFramework\Modules\Users\Models`, those classes still exist and now extend the rbac base. You can keep using them.

If your application code calls into the trait helpers (`hasRole()`, `hasPermissionTo()`, `assignRole()`, `givePermissionTo()`, `syncPermissions()`), the signatures are unchanged. Lookups by slug *or* name now both work — `hasRole('admin')` resolves whether you registered the role with `name => 'admin'` or `slug => 'admin'`.

### 5. Auth-gating callers of the API routes

The `users`, `roles`, `permissions` apiResource routes — and the `users/bulk` endpoint — now require `auth`. If your application called these routes from an unauthenticated context, you'll need to authenticate first. The framework picks the guard from `auth.defaults.guard` so consumers can wire sanctum, session, or a custom guard.

The `PermissionController` additionally calls `authorize()` for each method. Users who hit those endpoints need the relevant capability:

| Endpoint | Capability | Filterable hook |
|---|---|---|
| `GET /permissions` | `permissions.viewAny` | `permissions.viewAny` |
| `GET /permissions/{id}` | `permissions.view` | `permissions.view` |
| `POST /permissions` | `permissions.create` | `permissions.create` |
| `PUT /permissions/{id}` | `permissions.update` | `permissions.update` |
| `DELETE /permissions/{id}` | `permissions.delete` | `permissions.delete` |

The same capability set was already enforced for `roles` (the policy was correctly registered there); this PR brings `PermissionController` into line.

### 6. Drop the seeders if you don't want them

The framework no longer auto-runs `RolesTableSeeder` + `PermissionsTableSeeder`. If you maintain your own role/permission inventory (Keystone, custom apps), there's nothing to do — the unwanted defaults will simply not be inserted on `php artisan db:seed`.

If you *do* want the framework's defaults, call them explicitly from your own seeder:

```php
$this->call([
    \ArtisanPackUI\Database\Seeders\RolesTableSeeder::class,
    \ArtisanPackUI\Database\Seeders\PermissionsTableSeeder::class,
]);
```

### 7. Use the new `role:` middleware

```php
Route::middleware('role:admin')->group(function () {
    // …
});
Route::middleware('role:admin,editor')->group(function () {
    // user must have at least one of these roles
});
```

`permission:` continues to work the same way, registered by `artisanpack-ui/rbac`.

## Breaking changes

- `PermissionPolicy` method signatures changed from `string|int $id` to `Authenticatable $user`. If you registered the policy yourself or invoked its methods directly with an integer ID, switch to passing the `User` instance.
- The bundled seeders are no longer in the default `DatabaseSeeder` chain. Adopt explicitly if you want them.
- API routes now require auth; unauthenticated calls return 401.
- `PermissionController` now requires the appropriate capability; previously any authenticated request was allowed through.

## Migration table

| 1.x → | 2.x |
|---|---|
| `migrations/2025_09_15_215707_create_roles_table.php` | Removed; rbac owns the schema |
| `migrations/2025_09_15_215805_create_permissions_table.php` | Removed; rbac owns the schema |
| `migrations/2025_09_15_215844_create_user_role_permission_pivots.php` | Removed; rbac owns the schema |
| Standalone `Role` / `Permission` models | Subclass `\ArtisanPackUI\Rbac\Models\{Role,Permission}` |
| `HasRolesAndPermissions` trait inline | Trait composes `\ArtisanPackUI\Rbac\Concerns\{HasRoles,HasPermissions}` |
| Routes registered without `auth` | Wrapped in `Route::middleware('auth')` |
| `PermissionPolicy::view($id)` | `PermissionPolicy::view(Authenticatable $user)` |
| Seeders called from default `DatabaseSeeder` | Opt-in; consumer calls them explicitly |

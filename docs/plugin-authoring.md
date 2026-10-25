# Plugin Author Guide

Plugins extend the CMS Framework with new content types, custom fields, admin
pages, nav entries, hooks, and — in hosts that ship a Module Federation runtime
— React admin components. This guide walks through every piece a plugin author
needs and points at the reference example under
[`examples/hello-world-plugin/`](../examples/hello-world-plugin/).

- [Plugin lifecycle](#plugin-lifecycle)
- [`plugin.json` manifest schema](#pluginjson-manifest-schema)
- [Base `PluginServiceProvider`](#base-pluginserviceprovider)
- [Registering an admin page](#registering-an-admin-page)
- [Registering nav entries](#registering-nav-entries)
- [Migrations](#migrations)
- [Uninstalling](#uninstalling)
- [Hook subscriptions](#hook-subscriptions)
- [Registering custom field types](#registering-custom-field-types)
- [Injecting edit-screen panels & tabs](#injecting-edit-screen-panels--tabs)
- [Federated React modules](#federated-react-modules)
- [Versioning & host compatibility](#versioning--host-compatibility)
- [Testing your plugin](#testing-your-plugin)

## Plugin lifecycle

1. **Discovery** — The framework scans `base_path(config('cms.plugins.directory'))`
   ( default `plugins/` ) for directories containing a `plugin.json` manifest.
2. **Install** — When a plugin is uploaded, `PluginManager::installFromZip()`
   extracts the archive, validates the manifest and inserts a row in the
   `plugins` table. A plugin already on disk is registered with
   `PluginManager::installFromDisk()` instead. Since *2.8.0*, the
   archive's declared uncompressed size is checked against
   `cms.plugins.maxUncompressedSize` (100MB default) before extraction, as a
   zip-bomb guard; an archive that exceeds the ceiling is refused. Since
   *2.12.2*, a ZIP whose slug is already taken — by a `plugins` row or by any
   entry of that name in the plugins directory, compared case-insensitively —
   is refused with `PluginInstallationException::alreadyInstalled()` before
   anything is extracted, so an existing plugin's files are never overwritten.
   To ship a new version of an installed plugin, use the update flow (see
   [Shipping updates](#shipping-updates)).
3. **Activate** — On activation, the plugin's `service_provider` ( from
   `plugin.json` ) is registered with the container. This runs your
   `register()` and `boot()` methods, and any manifest-declared migrations.
4. **Deactivate** — The plugin row is marked inactive. The service provider is
   unregistered on the next request boot.
5. **Delete** — `PluginManager::delete()` deactivates the plugin if needed,
   fires `ap.cmsFramework.plugin.deleting`, runs the manifest's `uninstall`
   class ( *2.13.0*, see [Uninstalling](#uninstalling) ), resets the plugin's
   migrations when `rollback_migrations_on_delete` is set, removes the
   permissions it seeded, then removes its DB record and directory and fires
   `ap.cmsFramework.plugin.deleted`.

## `plugin.json` manifest schema

A plugin's root directory MUST contain a `plugin.json` file:

```json
{
  "$schema": "https://artisanpack-ui.dev/schemas/plugin.json",
  "slug": "hello-world",
  "name": "Hello World",
  "version": "1.0.0",
  "description": "Reference plugin demonstrating the framework's plugin API.",
  "author": {
    "name": "Jacob Martella",
    "email": "me@jacobmartella.com",
    "url": "https://jacobmartella.com"
  },
  "homepage": "https://example.com/hello-world",
  "license": "MIT",
  "service_provider": "HelloWorld\\HelloWorldServiceProvider",
  "requires": {
    "cms-framework": "^2.4",
    "php": "^8.2",
    "plugins": {
      "google-oauth": "^1.0"
    }
  },
  "conflicts": {
    "legacy-hello": "*"
  },
  "autoload": {
    "psr-4": {
      "HelloWorld\\": "src/"
    }
  },
  "migrations_path": "database/migrations",
  "rollback_migrations_on_delete": false,
  "uninstall": "HelloWorld\\Uninstall",
  "nav_entries": [
    {
      "slug": "hello-world",
      "label": "Hello World",
      "url": "/admin/hello-world",
      "icon": "fas.puzzle-piece",
      "permission": "access_admin_dashboard",
      "order": 60
    }
  ],
  "federated_module": {
    "name": "helloWorldAdmin",
    "entry": "dist/remoteEntry.js",
    "exposes": ["./HelloWorldPanel"]
  }
}
```

Declaring `nav_entries` and `federated_module` in the manifest is enough — the
framework bridges them into the runtime `PluginRegistry` when your plugin loads,
with no `register*()` call required. See
[Registering nav entries](#registering-nav-entries) and
[Federated React modules](#federated-react-modules).

### Required fields

| Field              | Type   | Notes |
| ------------------ | ------ | ----- |
| `slug`             | string | Lowercase, dashes; matches directory name. |
| `name`             | string | Human-readable name for the admin UI. |
| `version`          | string | Semver. Used for host-compatibility checks and update flows. |
| `service_provider` | string | Fully-qualified Laravel service provider class ( see below ). |

### Optional fields

| Field               | Type            | Notes |
| ------------------- | --------------- | ----- |
| `description`       | string          | Shown in the admin plugin list. |
| `author`            | object\|string  | `{ name, email?, url? }`, or a plain name string. |
| `homepage`          | string ( URL )  | Documentation or marketing URL. |
| `license`           | string          | SPDX identifier ( `MIT`, `Apache-2.0`, etc. ). |
| `min_framework_version` | string ( semver ) | *2.14.0*. Minimum **cms-framework** package version, e.g. `"2.14.0"`. Not the host application's version; use `min_app_version` for that. Enforced on activation. See [Versioning & host compatibility](#versioning--host-compatibility). |
| `min_host_version`  | string ( semver ) | **Deprecated** since *2.14.0*: alias of `min_framework_version`. Despite the name, it is compared against the cms-framework version, not the host application's. |
| `min_app_version`   | string ( semver ) | *2.14.0*. Minimum version of the **host application** ( e.g. Keystone CMS ), compared against the host's `cms.plugins.appVersion` config. Enforced on activation. See [Versioning & host compatibility](#versioning--host-compatibility). |
| `requires`          | object          | Semver constraints: `{ "cms-framework": "^2.4", "php": "^8.2" }`. A nested `plugins` object declares plugin-to-plugin dependencies. Enforced on activation. See [Plugin dependencies & conflicts](#plugin-dependencies--conflicts). |
| `conflicts`         | object          | Map of plugin slug to version constraint. Activation is refused when a matching plugin is installed. See [Plugin dependencies & conflicts](#plugin-dependencies--conflicts). |
| `composer`          | object          | Map of Composer package name to version constraint, e.g. `{ "artisanpack-ui/convertkit": "^1.2" }`. Installed from Packagist when the plugin is installed or updated, and checked again on activation. See [Composer-package dependencies](#composer-package-dependencies). |
| `autoload`          | object          | PSR-4 map. The framework hands this to Composer's runtime `ClassLoader`. Since *2.13.0* each namespace must end in a backslash ( `"HelloWorld\\"` ) and each path must be a relative string inside the plugin ( no `..`, no absolute paths ); anything else is refused at install and update. |
| `migrations_path`   | string ( path ) | Relative path to your migrations directory. Auto-run on activate. See [Migrations](#migrations). |
| `rollback_migrations_on_delete` | boolean | Default `false`. When `true`, deleting the plugin reverts every migration under `migrations_path`. See [Migrations](#migrations). |
| `uninstall`         | string ( class ) | *2.13.0*. Invokable class run on delete, even when the plugin is inactive. See [Uninstalling](#uninstalling). |
| `federated_module`  | object          | Single federated module descriptor `{ name?, entry, exposes?, boot_module? }`. `entry` is an `https` URL ( plain `http` only for a localhost dev server ) or a path inside a subdirectory of the plugin such as `dist/remoteEntry.js`, which the framework serves. `exposes` is a **list** of module ids. `boot_module` ( *2.14.0* ) is an exposed module the host preloads. Unknown keys are logged. Auto-registered on load. See [Federated React modules](#federated-react-modules). |
| `nav_entries`       | array           | List of static nav entries `{ slug, label, url?, icon?, permission?, order?, ... }`. Auto-registered on load; equivalent to calling `registerNavEntry()` from your provider. |
| `update`            | object          | Where self-updates come from. See [Shipping updates](#shipping-updates). |
| `update_url`        | string ( URL )  | Legacy custom JSON update feed. Superseded by `update`; still honored. |

> **Displaying the author.** `PluginManager::discoverPlugins()` and
> `::getPlugin()` return `author` verbatim from the manifest, so it is a
> string or an object depending on what `plugin.json` declares — echoing it
> directly risks an `Array to string conversion`. Each returned plugin also
> carries an `author_name` key, always a display-safe string ( the object's
> `name`, the plain string, or `''` ), which is what a host should render in
> a plugin list.

## Base `PluginServiceProvider`

Extend
`ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginServiceProvider`
for opinionated helpers:

```php
<?php

namespace HelloWorld;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginServiceProvider;

final class HelloWorldServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        // Bind services here.
    }

    public function boot(): void
    {
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

        $this->loadViewsFrom( $this->pluginPath( 'resources/views' ), 'hello-world' );
    }
}
```

Available helpers:

- `registerAdminPage( string $slug, array $config )` — attach an admin page to the shared `AdminMenuManager`. `view` for Blade, `component` for federated React.
- `registerNavEntry( array $entry )` — add an idempotent nav entry to the container-bound `PluginRegistry`.
- `registerFederatedModule( string $name, string $entryPath, array $exposes = [], ?string $bootModule = null )` — declare a Module Federation entry hosts can consume. `$entryPath` is an absolute URL, a root-relative URL on the host, or a path relative to the plugin directory ( `dist/remoteEntry.js` ), which the framework serves. Pass the relative path, not `pluginPath()`, which returns a filesystem path. `$bootModule` is *2.14.0*.
- `pluginPath( ?string $subpath = null )` — resolve a path within your plugin directory.
- `pluginConfig( ?string $key = null )` — read the plugin's `plugin.json`; dot-notation supported.
- `pluginSlug()` — resolves from your manifest.

## Registering an admin page

Blade version:

```php
$this->registerAdminPage( 'hello-world', [
    'title'      => __( 'Hello World' ),
    'section'    => 'tools',
    'view'       => 'hello-world::admin.index',
    'capability' => 'access_admin_dashboard',
] );
```

The `view` value is a namespaced Blade view; register it with `loadViewsFrom(
$this->pluginPath('resources/views'), 'hello-world' )` in `boot()`. The
framework wraps it in a closure before it reaches the route, so you get a
rendered page rather than Laravel's `Invalid route action` error.

### The admin layout

Extend `cms::admin.layouts.app` and fill its `title` and `content` sections:

```blade
@extends('cms::admin.layouts.app')

@section('title', __('Hello World'))

@section('content')
    <h1>{{ __('Hello World') }}</h1>
@endsection
```

The layout is deliberately plain — the framework is front-end agnostic and
ships no CSS build. It renders the admin menu, yields your content, and
exposes `styles` and `scripts` stacks. Host apps replace it with their own
chrome by publishing it:

```bash
php artisan vendor:publish --tag=cms-views
```

That writes to `resources/views/vendor/cms/`, which Laravel resolves ahead of
the package's copy — so a host swapping in its own chrome does not require any
plugin to change the view it extends.

Federated ( React ) version — same helper, use `component` instead of `view`:

```php
$this->registerAdminPage( 'hello-world', [
    'title'     => __( 'Hello World' ),
    'section'   => 'tools',
    'component' => 'helloWorldAdmin/HelloWorldPanel',
] );
```

Host apps map the `component` identifier to a real React component through
their Module Federation loader.

### The federated admin-page contract

**The framework does not own the frontend and ships no Module Federation
runtime.** It cannot resolve or hydrate a federated component itself — a host
application must do that. `registerAdminPage( component: )` gives you a
host-agnostic *mount contract*; the host holds the *hydration responsibility*.
There are two supported ways a host can hydrate the mount:

**1. Scan the Blade mount (host-agnostic).** A `component`-only admin page
renders the framework-owned `cms::admin.layouts.federated` shell, which emits a
single mount point inside the admin chrome:

```html
<div data-cms-federated-module="helloWorldAdmin/HelloWorldPanel">
    <p class="cms-admin__federated-fallback" role="status">
        This admin page loads the federated module "…", which the host
        application must hydrate. …
    </p>
</div>
```

A host whose front end scans for `data-cms-federated-module` mounts the named
module into that div, replacing the fallback notice. Until a host hydrates it,
the fallback notice renders in place of a blank page — so an unconfigured host
shows a clear "needs a host runtime" message rather than a **silent dead div**.

**2. Bridge to the host's own runtime via the filter (Inertia and others).**
A host that mounts components its own way — a different mount element, a
server-rendered island, an Inertia page — overrides the default through the
`ap.cmsFramework.admin.federatedPageAction` filter, which receives the default
route action, the component identifier and the page config, and returns its own
route action:

```php
addFilter(
    'ap.cmsFramework.admin.federatedPageAction',
    function ( \Closure $default, string $component, array $config ): \Closure {
        return static fn () => view( 'host::federated', ['module' => $component] );
    },
);
```

Returning anything other than a closure falls back to the shipped shell.

> **Inertia-based hosts (e.g. Keystone CMS).** Keystone's federation runtime is
> Inertia-page-based: it resolves page names like `plugins/<remote>/<page>` from
> a manifest built off the `ap.plugins.federatedModules` filter and mounts them
> through the Inertia page resolver — it does **not** scan for the Blade mount
> `data-cms-federated-module`. On such a host, ship your admin UI as an Inertia
> page under the `plugins/<remote>/<page>` route (paired with a
> [`federated_module`](#federated-react-modules) declaration so the host can
> resolve the remote), and — if you also want `registerAdminPage( component: )`
> to reach that page — bridge it with the filter above. Without the filter
> override, `registerAdminPage( component: )` renders the fallback notice on an
> Inertia host, because nothing hydrates the Blade mount there.

The shell is never handed to `Route::get()` as a bare string (which Laravel
rejects as an invalid route action; because admin routes register from a
`booted()` callback, that once surfaced on *every* request, not just the
plugin's own page, taking the whole application down). A page that declares
neither a `view` nor a `component` responds `501` on its own route instead of
breaking route registration.

## Registering nav entries

`registerNavEntry()` writes an entry to the framework's `PluginRegistry`; the
host's admin shell reads it via the `ap.admin.menu` filter. Entries are
identified by `slug` and are idempotent — repeated registrations from the same
plugin ( e.g. under Octane workers ) overwrite instead of accumulating.

You may alternatively pre-declare nav entries in `plugin.json` under
`nav_entries`. The framework bridges each declared entry into the registry
through the same `registerNavEntry()` path — including URL sanitization — when
the plugin loads, so declaration alone is enough. Programmatic registration is
preferred when nav visibility depends on per-request state ( feature flags,
tenant configuration, etc. ), and always wins: a manifest entry is skipped when
your provider has already registered the same `slug`.

## Migrations

Set `"migrations_path": "database/migrations"` in your manifest to have the
framework run your migrations on activation. Prefer schema-only changes;
data seeding belongs in a separate seeder invoked via an install hook.

> **Key name.** Earlier versions of this guide and of the hello-world example
> used `"migrations"`. The framework has only ever read `migrations_path`; a
> manifest using `migrations` had its migrations silently skipped.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create( 'hello_world_greetings', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'message' );
            $table->timestamps();
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'hello_world_greetings' );
    }
};
```

### Dropping your tables on delete

Plugin tables survive deletion by default, so reinstalling a plugin keeps its
data. Set `"rollback_migrations_on_delete": true` to have `delete()` revert
your migrations. Since *2.13.0* this runs `migrate:reset` scoped to your
`migrations_path`, so **every** migration the plugin ran is reverted, whatever
batch it ran in. Before 2.13.0 it ran `migrate:rollback`, which only reverted
the last global batch and left your tables behind once any other migration had
run.

**Give your migration files unique names.** Laravel's `migrations` table
records only each file's basename, not its path. If a plugin migration shares
a basename with a host or package migration, resetting the plugin runs your
`down()` and deletes that shared row, so the host migration looks un-run.
Prefix the descriptive part with your slug:

```text
database/migrations/2026_01_01_000000_hello_world_create_greetings_table.php
```

## Uninstalling

Since *2.13.0*, a plugin can declare an invokable class that cleans up when it
is deleted:

```json
"uninstall": "HelloWorld\\Uninstall"
```

```php
<?php

namespace HelloWorld;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;

final class Uninstall
{
    public function __invoke( Plugin $plugin ): void
    {
        // Remove settings rows, cached tokens, files under storage/, etc.
    }
}
```

Use it for anything your plugin keeps outside its own tables. Listening to
`ap.cmsFramework.plugin.deleting` is not enough: that hook only reaches a
plugin whose service provider booted in the same request, and plugins are
usually deactivated first and deleted later.

How it runs:

- `delete()` registers your PSR-4 autoloader, resolves the class from the
  container ( so its constructor may type-hint dependencies ) and invokes it
  with the `Plugin` model.
- It runs whether or not the plugin is active, and whether or not files are
  being deleted ( `delete( $slug, false )` ).
- It runs after the `deleting` hook and **before** the migration reset,
  permission removal and file deletion, so your tables and files are still
  there.
- A missing, non-invokable or throwing class is logged and never blocks the
  delete.

Constraints checked at install and update, and again at delete:

- The value must be a fully qualified, namespaced class name with no leading
  backslash.
- The class must live under one of your `autoload.psr-4` namespaces.

Checked only at delete, when the class is loaded:

- The class file must exist and sit inside your plugin directory. A namespace
  that overlaps a host namespace ( `App\` ) or a PSR-4 path that climbs out of
  the plugin cannot reach host code.

Install does not check that the class file exists. A missing file passes
install, then at delete the error is logged and cleanup is skipped, so ship
the file and test your uninstaller before releasing.

> **Trust.** Deleting a plugin runs its `uninstall` class even if the plugin
> was never activated. Installing, activating and deleting all require the
> same `manage-plugins` ability, so only install plugins you trust.

## Hook subscriptions

The framework's action / filter system ( `artisanpack-ui/hooks` ) is the
primary extension seam. Register callbacks in `boot()`:

```php
addAction( 'ap.contentTypes.created', function ( $contentType ) {
    logger()->info( 'A content type was created: ' . $contentType->slug );
} );

addFilter( 'ap.admin.contentEdit.saveData', function ( array $data, array $context ): array {
    if ( 'post' === $context['contentType'] ) {
        $data['metadata']['reviewed_at'] = now()->toIso8601String();
    }

    return $data;
} );
```

Full hook reference: [`docs/Hooks-and-Events.md`](./Hooks-and-Events.md).

## Registering custom field types

Plugins can extend the closed `FieldType` enum through the
`CustomFieldTypeRegistry`. Registered types become available in the admin
custom-field-type dropdown and validate on save.

```php
apRegisterFieldType( 'map_picker', [
    'label'              => __( 'Map Picker' ),
    'column_type'        => 'string',
    'validation_rules'   => ['string', 'regex:/^-?\d+\.\d+,-?\d+\.\d+$/'],
    'editor_component'   => 'helloWorldAdmin/MapPickerEditor',
    'renderer_component' => 'helloWorldAdmin/MapPickerRenderer',
    'meta'               => ['icon' => 'fas.map'],
] );
```

Filter-registered fields do not materialize a physical DB column. Their values
live in the content record's `metadata` JSON column — the framework's
`HasCustomFields` trait knows how to read and write them there.

## Injecting edit-screen panels & tabs

Host apps that build a post/page edit screen ( such as Keystone CMS ) consult
the `ContentEditExtensions` manager to render plugin-supplied panels and tabs.
Register from your provider's `boot()`:

```php
addFilter( 'ap.admin.contentEdit.panels', function ( array $panels ): array {
    $panels[] = [
        'slug'         => 'hello-world.seo',
        'title'        => __( 'SEO' ),
        'component'    => 'helloWorldAdmin/SeoPanel',
        'contentTypes' => ['post', 'page'],
        'order'        => 20,
    ];

    return $panels;
} );
```

Filters:

- `ap.admin.contentEdit.panels` — sidebar/right-column panels
- `ap.admin.contentEdit.tabs` — tabs above the main editor
- `ap.admin.contentEdit.beforeEditor` / `afterEditor` — blocks above/below the editor
- `ap.admin.contentEdit.saveData` — pre-persistence transform of the save payload

Entry shape: `{ slug, title, component, order?, contentTypes?, capability? }`.
`contentTypes` accepts `['*']` for "all" or an explicit list.

## Federated React modules

The framework does not own the frontend, and does not ship a Module Federation
runtime. Host apps ( e.g. Keystone CMS ) that support runtime-loaded React
plugins consume federated modules the plugin has declared through:

- **Manifest** — a `federated_module` object in `plugin.json`. The framework
  auto-registers it on load, keyed by the descriptor's optional `name` (falling
  back to the plugin slug). Declaration alone is enough.
- **Programmatic** — `$this->registerFederatedModule( $name, $entry, $exposes, $bootModule )`
  in `boot()`. A programmatic registration wins: a manifest module is skipped
  when the same name is already registered.

Hosts read the registered descriptors from the `ap.plugins.federatedModules`
filter, keyed by remote name: `{ entry, exposes?, bootModule? }`. `entry` is
always a URL the browser can load ( see below ).

Declaring a module makes it *resolvable* by the host; it does not, by itself,
place it on a page. To surface a federated module as an admin page, pair the
declaration with `registerAdminPage( component: )` and hydrate it per the
[federated admin-page contract](#the-federated-admin-page-contract) — either the
Blade mount or the filter bridge, depending on the host.

### The `entry`

*2.14.0.* The manifest's `entry` must be one of:

- An absolute `https` URL, e.g. a CDN: `https://cdn.example.com/acme/remoteEntry.js`.
- A plain `http` URL to a loopback dev server ( `localhost`, `*.localhost`,
  `127.0.0.1`, `[::1]` ), e.g. `http://localhost:5174/remoteEntry.js` while
  running `vite`.
- A path inside a **subdirectory** of the plugin, e.g. `dist/remoteEntry.js`.
  The framework serves it for you ( see [Serving your bundle](#serving-your-bundle) ).
  The file name may use letters, digits, `.`, `_`, `@` and `-`, and must not
  start with a dot. `..`, a leading `/`, backslashes and query strings are
  refused.

URLs with credentials ( `https://user:pass@…` ) are refused. `registerFederatedModule()`
additionally accepts a root-relative URL on the host ( `/build/remoteEntry.js` ),
which is passed through unchanged. An absolute filesystem path inside the
plugin ( e.g. from `pluginPath()` ) is treated as the matching relative path, one
under `public_path()` as its root-relative URL, and any other filesystem path is
logged and not registered, so a server path never reaches the browser.

`exposes` is a **list** of the module ids your federation build exposes, e.g.
`["./SettingsPage"]`, not the `{ "./SettingsPage": "./src/SettingsPage.tsx" }`
map from the build config.

### `boot_module`

*2.14.0.* `boot_module` names an exposed module the host loads before the first
plugin page mounts, typically `./boot`, so the plugin can register its JS hooks
before the host emits its first action. It does not need to be listed in
`exposes`. The descriptor carries it as `bootModule`. See the `hooks-js`
"Plugin `bootModule` recipe" for the module itself.

### Serving your bundle

*2.14.0.* When `entry` is a relative path, the framework publishes the entry's
directory on a public route, `GET /plugins/{slug}/assets/{path}`
( `plugins.assets` ), and registers the absolute URL, e.g.
`https://example.com/plugins/acme/assets/remoteEntry.js`. Your plugin needs no
asset controller of its own.

- Only active plugins are served. Everything else returns an empty `404`.
- Only the entry's own directory is published, which is why the entry must sit
  in a subdirectory: an entry at the plugin root would publish your source and
  manifest. A plugin publishes one asset directory.
- Files must resolve inside that directory ( symlinks that escape it are
  refused ) and have one of these extensions: `js`, `mjs`, `css`, `map`,
  `json`, `wasm`, `svg`, `png`, `jpg`, `jpeg`, `gif`, `webp`, `avif`, `woff`,
  `woff2`, `ttf`, `otf`. Dot-files and dot-directories ( `.vite/` ) are never
  served.
- Files whose name carries a bundler content hash ( Vite's `index-BxQ3k9aZ.js`,
  webpack's `main.3f9a1c2e.js` ) are cached as `immutable` for a year. The
  entry and every other file are sent `no-cache` with an ETag and
  Last-Modified, so they revalidate and a plugin update is picked up on the
  next load.
- Every file is sent with `X-Content-Type-Options: nosniff`. SVGs are also
  sandboxed with a CSP and sent as attachments, so `<img src>` works but
  opening one directly cannot run script.

The route is public, like the theme asset route: a federation runtime fetches
the bundle before any admin page exists to gate it. Do not put secrets in your
build. Source maps ( `.map` ) are served if you ship them.

### Host integration

- **Catch-all routes.** The asset route registers after every provider has
  booted, after the host's `routes/web.php`. A host with a GET catch-all
  ( e.g. CMS pages at `/{path}` ) must exclude the `plugins/` prefix, or the
  catch-all answers first and every plugin bundle fails to load:

  ```php
  Route::get( '/{path}', [PageController::class, 'show'] )
      ->where( 'path', '^(?!plugins($|/)).+$' );
  ```

- **Plugins with their own asset route.** Because the framework route loads
  last, a plugin that registers its own `/plugins/{slug}/assets/{path}` route
  from `boot()` keeps matching first.
- **Opting out.** A host that serves plugin assets itself can set
  `cms.plugins.assetRoute` ( env `CMS_PLUGINS_ASSET_ROUTE` ) to `false`. A
  relative entry still resolves to `/plugins/{slug}/assets/…`, so the host's
  route must answer that URL.

An example Module Federation config, Vite build, and remoteEntry contract live
in the Keystone CMS docs. See
[`examples/hello-world-plugin/`](../examples/hello-world-plugin/) for a stub
that pairs a Blade admin page with a `federated_module` declaration that
Keystone can consume.

## Versioning & host compatibility

Semver your plugin. A plugin runs inside two versioned things, and the manifest
can declare a minimum for each:

| Key | Compared against | Where the version comes from |
| --- | --- | --- |
| `min_framework_version` | The **cms-framework** package | The framework's `composer.json`, then Composer's `InstalledVersions`. |
| `min_app_version` | The **host application** ( e.g. Keystone CMS ) | The host's `cms.plugins.appVersion` config. |

```json
"min_framework_version": "2.14.0",
"min_app_version": "1.2.0"
```

> **`min_host_version` is the minimum _cms-framework_ version, not the host
> application's.** It was renamed to `min_framework_version` in *2.14.0* and is
> still honored as a deprecated alias. A plugin that wrote
> `"min_host_version": "1.0.0"` meaning "Keystone 1.0" was checked against
> cms-framework 2.x, which always passes. Use `min_app_version` for the host
> application.
>
> Hosts running cms-framework older than *2.14.0* read only `min_host_version`
> and ignore `min_framework_version` and `min_app_version`. To keep a framework
> minimum enforced there, declare `min_host_version` with the same value as
> `min_framework_version` ( or use a `requires.cms-framework` constraint, below ).
> Declaring both keys with different values is refused at validation.

`PluginManager::activate()` checks both before any state changes and throws
`IncompatiblePluginException` when the host is older than required. Its
`constraint` property is `framework` or `app`. Over HTTP the activate and update
endpoints return `409` with code `plugin_incompatible` and the details:

```json
{
    "message": "Plugin 'google' requires host application version 1.2.0 or higher; host is running 1.1.0.",
    "code": "plugin_incompatible",
    "plugin": "google",
    "constraint": "app",
    "required_version": "1.2.0",
    "host_version": "1.1.0"
}
```

The framework cannot detect the host application's version, so the host
supplies it, either through the `CMS_APP_VERSION` environment variable or from
a service provider's `register()` method:

```php
public function register(): void
{
    config( [ 'cms.plugins.appVersion' => config( 'keystone.version' ) ] );
}
```

Do not write `'appVersion' => config( 'keystone.version' )` in
`config/cms/plugins.php`: config files load in name order, so `keystone.php`
has not loaded yet and the value is `null`, which silently skips every
`min_app_version` check. ( `config( 'app.version' )` happens to work there,
only because `app` sorts before `cms`. )

**A check that cannot run is skipped, not failed.** When the version to compare
against is unknown ( the host sets no `appVersion`, or cms-framework runs from a
path-repo checkout with no resolvable version ) or is not a comparable semver
( `dev-main` ), activation goes ahead. The framework logs a warning and fires
the `ap.cmsFramework.plugin.versionCheckSkipped` action so a host can surface
it in its admin UI:

```php
addAction( 'ap.cmsFramework.plugin.versionCheckSkipped', function ( string $slug, array $details ): void {
    // $details = [
    //     'constraint'       => 'app',        // or 'framework'
    //     'required_version' => '1.2.0',
    //     'host_version'     => null,         // null when unresolved
    //     'reason'           => 'unresolved', // or 'not_semver'
    // ];
} );
```

For a range rather than a minimum, declare a constraint under `requires`
instead. It is checked against the cms-framework version on activation, and an
unmet constraint fails with `plugin_dependencies_unsatisfied` ( see
[Plugin dependencies & conflicts](#plugin-dependencies--conflicts) ):

```json
"requires": {
    "cms-framework": "^2.14",
    "php": "^8.2"
}
```

## Plugin dependencies & conflicts

Plugins can declare hard dependencies on, and conflicts with, other plugins.
Dependencies live under `requires.plugins` and conflicts under `conflicts`,
both mapping a plugin slug to a semver constraint (`*` matches any version):

```json
"requires": {
    "plugins": {
        "google-oauth": "^1.0"
    }
},
"conflicts": {
    "legacy-forms": "*"
}
```

`PluginManager::activate()` gates on these before any state changes. Activation
is refused when a required plugin is **missing** (not installed), **inactive**
(installed but not activated), or fails its **version constraint**, and when a
declared **conflict** is installed within its constraint range. Conflicts are
enforced **symmetrically** — activation is also refused when an already-installed
plugin declares a conflict against the plugin being activated, so a conflict
cannot be bypassed by activation order. The API returns `409` with a
machine-readable `code` (`plugin_dependencies_unsatisfied` or `plugin_conflict`)
and the offending details.

> **Conflicts are scoped to _installed_, not _active_, plugins** (Composer-style
> semantics). Two plugins that declare a conflict against each other cannot both
> be installed and activated: activation is refused while the other is present on
> disk, even if it is inactive. Resolve a mutual conflict by **deleting** one of
> the two plugins, not merely deactivating it.

Deactivation is guarded in the other direction: `PluginManager::deactivate()`
refuses to disable a plugin while active plugins still depend on it (API `code`
`plugin_has_active_dependents`). Deleting a plugin forces past this guard.

At boot, active plugins are registered **dependencies-first**, so a dependent's
service provider never boots before the provider whose services it consumes.

Resolution helpers:

| Method | Purpose |
| ------ | ------- |
| `checkDependencies( $slug ): DependencyResult` | Missing / inactive / version-mismatch / conflict buckets for one plugin. |
| `getDependents( $slug ): array` | Slugs of installed plugins that require `$slug`. |
| `canDeactivate( $slug ): bool` | Whether disabling `$slug` would break an active dependent. |
| `getActivationOrder( array $slugs ): array` | Dependency-first ordering; throws `CircularDependencyException` on a cycle. |

The same data is exposed over HTTP: `GET /api/v1/plugins/{slug}/dependencies`,
`GET /api/v1/plugins/{slug}/dependents`, and
`POST /api/v1/plugins/check-dependencies` (body `{ "plugins": [ "a", "b" ] }`),
which returns a resolved activation `order` alongside per-plugin status.

## Composer-package dependencies

A plugin can depend on a real Packagist package instead of vendoring it inside
the ZIP. Declare the requirement in a `composer` block — Composer package name
to semver constraint:

```json
{
    "slug": "convertkit",
    "name": "ConvertKit",
    "version": "1.0.0",
    "service_provider": "ConvertKit\\ConvertKitServiceProvider",
    "composer": {
        "artisanpack-ui/convertkit": "^1.2"
    }
}
```

This is the sibling of `requires.plugins` (plugin→plugin): `requires` resolves
*other plugins* by slug, while `composer` resolves *Packagist packages* and can
bring their vendor tree into the host. Keys are validated as Composer package
names (`vendor/package`), and each constraint must be a **bounded, stable**
semver range: a bare `*`, a `@dev` stability flag, a `dev-<branch>` reference, or
a lower-bound-only range (`>=1.0`, `>1.0`) is rejected at validation time, since
an auto-installed dependency must not pull the newest, an arbitrary branch's, or
a future major's code at activation. Bounded ranges — `^1.2`, `~2.0`,
`>=1.0 <2.0`, and bounded wildcards such as `1.2.*` — are fine. The `composer`
block is re-validated at activation, not only at install/update, so a
requirement that reached the row unvalidated still fails closed. A plugin also
may not name a package the host itself requires in its root `composer.json` — it
may bring in new packages, but never change the version of one the host owns.

**When it runs.** *(2.16.0)* When the plugin is installed. `installFromZip()`
and `installFromDisk()` resolve the `composer` block against the host's installed
packages (reusing `composer/semver`) once the manifest and slug checks pass,
before the plugin's row is created, and install whatever is unmet. An update
does the same after it extracts the new version, whether or not the plugin is
active. Activation resolves the block again before the service provider boots,
as a backstop: a package lost since install (a host `composer install` from a
lock that doesn't have it, a restored `vendor/`) is put back or reported.
Requirements already satisfied are left untouched.

**Installing.** An unmet requirement is installed by running
`composer require <package>:<constraint>` in the host root, so the dependency is
added to the host's `composer.json` / `composer.lock` and survives a fresh
deploy. The freshly installed package's PSR-4 prefixes are registered on the
running class loader, so its classes are autoloadable if the plugin is activated
in that same request. Composer itself arbitrates any conflict with the host's
existing lock, and every install is logged. This is controlled by
`cms.plugins.autoInstallComposerDependencies`, which is **on by default** since
2.16.0 (it was off before).

> **Security note.** Packages are installed when a plugin is *installed*, not
> when it is activated. Laravel's package discovery registers their service
> providers, so they boot on every request even while the plugin is inactive,
> and anyone who can install or update plugins can bring new third-party code
> into the host. Set `PLUGINS_AUTO_INSTALL_COMPOSER_DEPENDENCIES=false` where
> plugin installers aren't fully trusted.

**Package migrations (2.11.0).** A package that registers its own migrations
(`loadMigrationsFrom()` in its service provider) can only have them run once its
service provider boots, which is when the plugin is activated. So with
auto-install on, activating a plugin that declares a `composer` block runs
`migrate --force` after the plugin's service provider registers, so the
package's tables exist before first use. When the packages were installed in
the same process (by that activation, or by an update of an active plugin that
reactivates it straight away), the migrate runs in a fresh `php artisan migrate`
process, because this one booted before their providers existed *(2.16.0)*.
The command is idempotent; migrations already applied are skipped. A migration failure fails the activation and rolls
it back.

**Failing closed.** A plugin never installs or activates with its packages
missing. If an install cannot complete — Packagist unreachable, a
`composer.lock` conflict, a missing `composer` binary, a package the host itself
requires — `installFromZip()` and `installFromDisk()` throw a
`PluginInstallationException` carrying Composer's reason, and no row is created.
A ZIP install also removes the extracted plugin directory; `installFromDisk()`
leaves the directory alone, since the framework didn't put it there. Over HTTP
the install endpoint returns the reason as a `plugin_zip` validation error. On
the update flow the failure restores the previous version through the
backup-restore path. At activation, an unmet requirement raises
`ComposerDependencyNotSatisfiedException` and the activation transaction
unwinds; the activate endpoint returns `409` with code
`plugin_composer_dependencies_unsatisfied`.

**Deactivation & removal.** Packages a plugin brought in are **left in place**
on deactivate and delete. They are never auto-removed: the host or another
plugin may share the same package, and Composer offers no safe ref-counted
uninstall here. Remove an unwanted package with `composer remove` yourself.

**Hardened hosts.** Set `PLUGINS_AUTO_INSTALL_COMPOSER_DEPENDENCIES=false` to
forbid runtime Composer installs, e.g. on a read-only filesystem or an immutable
container image. Install and update then never touch the `composer` block, and
activation fails closed until the operator has installed the declared packages
themselves — the behavior before 2.16.0. `PLUGINS_COMPOSER_BINARY`
overrides the resolved `composer` command for sandboxed PHP-FPM pools.

## Shipping updates

Declare an update source in `plugin.json`. Publishing a new version is then
`git tag` plus a GitHub Release — no hosted JSON feed, no manual ZIP upload:

```json
"update": {
    "github": "ArtisanPack-UI/artisanpack-ui-plugin"
}
```

`update` accepts either form:

| Key      | Value | Notes |
| -------- | ----- | ----- |
| `github` | `owner/repo`, or a full `https://github.com/owner/repo` URL | Shorthand for the GitHub Releases source. |
| `url`    | Absolute `https://` URL | Handed to the source detector as-is, so GitLab repository URLs and custom JSON endpoints work through the same key. |

Both forms are https-only: the resolved archive is extracted into your
`plugins/` directory and its PHP is executed by the host.

`UpdateManager` walks your releases, skips prereleases, and picks the first
release asset ending in `.zip` — falling back to GitHub's generated
`zipball_url` when the release has no attached asset. **Attach a real ZIP.**
The generated zipball's root directory is named for the repository and commit,
not for your plugin slug, so extraction lands the plugin in the wrong
directory.

### Checksums

The updater verifies the downloaded archive against a SHA-256 digest, resolved
from either:

- a release asset named `{your-asset}.zip.sha256`, or
- a `SHA-256: <64 hex chars>` line in the release description.

With the shipped defaults ( `cms.updates.verify_checksum = true`,
`cms.updates.allow_unverified_updates = false` ) a release that publishes
**neither** is refused. Add a sidecar step to your release workflow:

```bash
sha256sum my-plugin.zip | cut -d' ' -f1 > my-plugin.zip.sha256
```

This is an integrity check, not an authenticity check — the digest comes from
the same release as the archive. It catches truncation and CDN corruption; it
does not defend against a compromised release-editor account.

Since *2.8.0*, legacy `update_url` feeds are held to the same bar. The feed's
`download_url` must be `https` — an `http` URL is refused up front, because it
would let a network attacker choose the archive that gets extracted and its
provider re-registered (arbitrary PHP execution) — and the downloaded archive
runs through the same checksum gate as the source-backed path. A feed that
advertises no digest is therefore refused with the shipped defaults, exactly
like a source-backed release; the only escape is
`cms.updates.allow_unverified_updates`. Add a `sha256` to your feed payload to
clear the gate.

### Private repositories

Public repositories need no credentials. For a private one, the host adds a
token keyed by your plugin slug:

```php
// config/cms.php
'plugins' => [
    'updateTokens' => [
        'my-private-plugin' => env( 'MY_PRIVATE_PLUGIN_UPDATE_TOKEN' ),
    ],
],
```

Tokens live in host config, never in `plugin.json` — the manifest ships inside
the distributed ZIP. They are per-slug rather than one global token: a plugin
names its own update host in its own manifest, so a shared token would be handed
to whatever host any installed plugin asks for. A site can therefore mix
sources freely — some plugins from a licensing server, others straight from a
private GitLab project — with each credential going only to its own plugin's
source.

A string token is sent by GitHub and GitLab sources as their own auth header,
and by a custom JSON feed as the `?token=` query parameter.

### Header auth for custom JSON feeds (2.12.0)

A query-string credential ends up in URLs, access logs and proxy logs. To send a
custom JSON feed's credential as a header instead, the host gives an array with
a `headers` key:

```php
// config/cms.php
'plugins' => [
    'updateTokens' => [
        'my-licensed-plugin' => [
            'headers' => [
                'Authorization' => 'Bearer ' . env( 'MY_LICENSE_KEY' ),
            ],
        ],
    ],
],
```

The headers are sent with the feed request and with the archive download, so
your feed's `download_url` can point at an authenticated endpoint rather than a
pre-signed or public URL. They are scoped to the feed's own origin ( scheme,
host and port ): if `download_url`, or a redirect from it, names a different
host, the headers are dropped for that request. Serve the archive from the
feed's host, or keep using a pre-signed URL for an off-host one.

The feed URL itself must answer directly: redirects are not followed while
credential headers are configured, so the credential cannot be passed along to
another host.

An optional `query` key alongside `headers` carries any query parameters the
feed still needs. Header auth applies to manifests that declare the source
through `update.url`; the legacy `update_url` key is fetched without
credentials.

### Scheduled update checks (2.12.0)

Update checks run on demand and are cached for `cms.plugins.updateCacheTtl`.
To keep those answers warm, the host schedules the built-in command:

```php
// routes/console.php
Schedule::command( 'cms:plugins:check-updates' )->daily();
```

It re-checks every plugin regardless of source, bypassing the cached answers,
and caches what it learns. One plugin's source failing does not stop the others
from being checked; the command reports each failure and exits non-zero.

The command wraps `UpdateManager::refreshUpdateChecks()`, which a host can call
directly — for example from its own queued job:

```php
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;

$result = app( UpdateManager::class )->refreshUpdateChecks();

$result['updates'];  // array<string, array> — available updates, keyed by plugin slug
$result['failures']; // array<string, string> — failure messages, keyed by plugin slug
```

A failed check is reported in `failures` rather than thrown, and leaves that
plugin's previously cached answer in place.

### Applying an update

`UpdateManager::updatePlugin( $slug )` installs the latest available version
and returns `false` when there is nothing newer than the installed version:

```php
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;

$updated = app( UpdateManager::class )->updatePlugin( 'hello-world' );
```

It fires `ap.cmsFramework.plugin.updating`, backs up the plugin directory,
deactivates the plugin if it was active, downloads and verifies the archive,
extracts it, re-validates the new `plugin.json` (its `slug` must match the
plugin being updated), saves the new version and manifest, reactivates the
plugin, and fires `ap.cmsFramework.plugin.updated`.

**Rollback.** If any step fails, the backup is restored and the `plugins` row
is reverted to the old version, manifest and active state, then a
`PluginUpdateException` is thrown. Since 2.13.0 this covers any `Throwable`,
not just exceptions, so an `Error` such as a `TypeError` from an `updated`
listener or the plugin's own provider also rolls the update back instead of
leaving it half-applied.

**Framework caches (2.13.0).** With `cms.plugins.autoClearFrameworkCaches` on,
a successful update runs `route:clear`, `config:clear` and `view:clear`, so
routes the new version adds or renames resolve under `route:cache`. The clear
runs after the `updated` hook. An active plugin is already cleared by the
deactivate / activate around the update, so the extra clear only runs for an
inactive one. See [Configuration](./Configuration.md#plugin-framework-cache-clearing).

## Testing your plugin

Plugins ship their own Pest / PHPUnit test suites. A minimal `TestCase`
extending Orchestra Testbench boots the framework in a testing container:

```php
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;
use HelloWorld\HelloWorldServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders( $app ): array
    {
        return [
            CMSFrameworkServiceProvider::class,
            HelloWorldServiceProvider::class,
        ];
    }
}
```

Test what matters: your admin page renders, your migrations run, your hook
subscriptions fire, and any custom field types round-trip through
`HasCustomFields`.

---

Reference plugin: [`examples/hello-world-plugin/`](../examples/hello-world-plugin/).

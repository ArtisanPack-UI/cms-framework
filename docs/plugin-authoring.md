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
- [Hook subscriptions](#hook-subscriptions)
- [Registering custom field types](#registering-custom-field-types)
- [Injecting edit-screen panels & tabs](#injecting-edit-screen-panels--tabs)
- [Federated React modules](#federated-react-modules)
- [Versioning & host compatibility](#versioning--host-compatibility)
- [Testing your plugin](#testing-your-plugin)

## Plugin lifecycle

1. **Discovery** — The framework scans `base_path(config('cms.plugins.directory'))`
   ( default `plugins/` ) for directories containing a `plugin.json` manifest.
2. **Install** — When a plugin is uploaded, `PluginManager::install()` validates
   the manifest and inserts a row in the `plugins` table. Since *2.8.0*, the
   archive's declared uncompressed size is checked against
   `cms.plugins.maxUncompressedSize` (100MB default) before extraction, as a
   zip-bomb guard; an archive that exceeds the ceiling is refused.
3. **Activate** — On activation, the plugin's `service_provider` ( from
   `plugin.json` ) is registered with the container. This runs your
   `register()` and `boot()` methods, and any manifest-declared migrations.
4. **Deactivate** — The plugin row is marked inactive. The service provider is
   unregistered on the next request boot.
5. **Delete** — The plugin's directory and DB record are removed.

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
  "migrations": "database/migrations",
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
| `requires`          | object          | Semver constraints: `{ "cms-framework": "^2.4", "php": "^8.2" }`. A nested `plugins` object declares plugin-to-plugin dependencies. Enforced on activation. See [Plugin dependencies & conflicts](#plugin-dependencies--conflicts). |
| `conflicts`         | object          | Map of plugin slug to version constraint. Activation is refused when a matching plugin is installed. See [Plugin dependencies & conflicts](#plugin-dependencies--conflicts). |
| `composer`          | object          | Map of Composer package name to version constraint, e.g. `{ "artisanpack-ui/convertkit": "^1.2" }`. Resolved from Packagist on activation. See [Composer-package dependencies](#composer-package-dependencies). |
| `autoload`          | object          | PSR-4 map. The framework hands this to Composer's runtime `ClassLoader`. |
| `migrations`        | string ( path ) | Relative path to your migrations directory. Auto-run on activate. |
| `federated_module`  | object          | Single federated module descriptor `{ name?, entry, exposes? }`. Auto-registered on load. See [Federated React modules](#federated-react-modules). |
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
- `registerFederatedModule( string $name, string $entryPath, array $exposes = [] )` — declare a Module Federation entry hosts can consume.
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

A `component`-only admin page renders the framework-owned
`cms::admin.layouts.federated` shell, which emits a mount point —
`<div data-cms-federated-module="…"></div>` — inside the admin chrome for the
host's Module Federation runtime to hydrate. It is never handed to
`Route::get()` as a bare string (which Laravel rejects as an invalid route
action; because admin routes register from a `booted()` callback, that once
surfaced on *every* request, not just the plugin's own page, taking the whole
application down). A page that declares neither a `view` nor a `component`
responds `501` on its own route instead of breaking route registration.

A federation host that mounts components its own way — a different mount
element, a server-rendered island, an Inertia response — overrides the default
through the `ap.cmsFramework.admin.federatedPageAction` filter, which receives
the default route action, the component identifier and the page config, and
returns its own route action:

```php
addFilter(
    'ap.cmsFramework.admin.federatedPageAction',
    function ( \Closure $default, string $component, array $config ): \Closure {
        return static fn () => view( 'host::federated', ['module' => $component] );
    },
);
```

Returning anything other than a closure falls back to the shipped shell.

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

Set `"migrations": "database/migrations"` in your manifest to have the
framework load your migrations on activation. Prefer schema-only changes;
data seeding belongs in a separate seeder invoked via an install hook.

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
- **Programmatic** — `$this->registerFederatedModule( $name, $entry, $exposes )`
  in `boot()`. A programmatic registration wins: a manifest module is skipped
  when the same name is already registered.

An example Module Federation config, Vite build, and remoteEntry contract live
in the Keystone CMS docs. See
[`examples/hello-world-plugin/`](../examples/hello-world-plugin/) for a stub
that pairs a Blade admin page with a `federated_module` declaration that
Keystone can consume.

## Versioning & host compatibility

Semver your plugin. Declare host-compatibility in `plugin.json`:

```json
"requires": {
    "cms-framework": "^2.4",
    "php": "^8.2"
}
```

`PluginManager::install()` refuses installation when the running framework
version does not satisfy the `cms-framework` constraint.

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
names (`vendor/package`); the same constraint rigor as the other manifest maps
applies.

**When it runs.** On activation, before the service provider boots, the
framework resolves the `composer` block against the host's installed packages
(reusing `composer/semver`). Requirements already satisfied are left untouched.

**Installing.** When a requirement is unmet and
`cms.plugins.autoInstallComposerDependencies` is `true` (the default), the
framework runs `composer require <package>:<constraint>` in the host root, so
the dependency is added to the host's `composer.json` / `composer.lock` and
survives a fresh deploy. The freshly installed package's PSR-4 prefixes are
registered on the running class loader so its classes are autoloadable when the
plugin boots in that same request. Composer itself arbitrates any conflict with
the host's existing lock.

**Failing closed.** Resolution never half-activates a plugin. If a requirement
is unmet and auto-install is disabled, or an install cannot complete — Packagist
unreachable, a `composer.lock` conflict, or a missing `composer` binary —
activation raises `ComposerDependencyNotSatisfiedException` and the activation
transaction unwinds. Over HTTP the activate endpoint returns `409` with code
`plugin_composer_dependencies_unsatisfied`. On the update flow the same failure
restores the previous version through the backup-restore path.

**Deactivation & removal.** Packages a plugin brought in are **left in place**
on deactivate and delete. They are never auto-removed: the host or another
plugin may share the same package, and Composer offers no safe ref-counted
uninstall here. Remove an unwanted package with `composer remove` yourself.

**Hardened hosts.** Set `PLUGINS_AUTO_INSTALL_COMPOSER_DEPENDENCIES=false` to
forbid runtime Composer installs; activation then requires the operator to have
installed the declared packages ahead of time. `PLUGINS_COMPOSER_BINARY`
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
the distributed ZIP.

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

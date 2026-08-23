# Hello World Plugin

Reference plugin for the ArtisanPack UI CMS Framework. It exists to give plugin
authors a canonical example of every extension point the framework exposes.

**This is a documentation-only skeleton.** It is not installed by the framework
and is not a Composer package. Copy the tree into your host application's
`plugins/hello-world/` directory to explore it interactively.

## What it demonstrates

| Piece | File |
| ----- | ---- |
| Manifest schema, including `requires`, `autoload`, `nav_entries`, `federated_module` | [`plugin.json`](./plugin.json) |
| Base `PluginServiceProvider` usage, admin page, nav entry, federated module | [`src/HelloWorldServiceProvider.php`](./src/HelloWorldServiceProvider.php) |
| Custom field type registration ( `map_picker` ) via `apRegisterFieldType()` | [`src/HelloWorldServiceProvider.php`](./src/HelloWorldServiceProvider.php) |
| Edit-screen panel registration via `ap.admin.contentEdit.panels` | [`src/HelloWorldServiceProvider.php`](./src/HelloWorldServiceProvider.php) |
| Action-hook subscription ( `ap.contentTypes.created` ) | [`src/HelloWorldServiceProvider.php`](./src/HelloWorldServiceProvider.php) |
| Migration path — plugin ships its own schema | [`database/migrations/`](./database/migrations/) |
| Blade admin page | [`resources/views/admin/index.blade.php`](./resources/views/admin/index.blade.php) |

## Two admin-page flavors, one plugin

`plugin.json` declares BOTH a Blade admin view and a federated module the host
can consume. The framework itself is view-layer agnostic — it hands the
declarations to `AdminMenuManager` and `PluginRegistry`, and each host renders
whichever flavor fits its stack.

- **Blade flavor** — `registerAdminPage()` with a `view` key. Works in any
  Laravel host today. See `resources/views/admin/index.blade.php`.
- **Federated React flavor** — `registerFederatedModule()` +
  `registerAdminPage()` with a `component` key. Requires a host that ships a
  Module Federation runtime — for example, the Keystone CMS. The
  `dist/remoteEntry.js` path referenced in `plugin.json` is intentionally
  omitted here; Keystone's docs cover the full federated-plugin build
  ( Vite / Module Federation config, remote-entry contract, host loader ).

## Trying it in a host app

1. Copy this directory into your host's `plugins/` folder ( default:
   `base_path('plugins/hello-world')` ).
2. Upload / activate via the admin plugin list, or seed a row into the
   `plugins` table with `is_active = 1`.
3. Navigate to `/admin/hello-world`. The Blade page renders.
4. In a Module Federation host: build a federated module from the
   `helloWorldAdmin` entry and let the host load the React panel. Either keep
   the Blade `view` ( render your own mount point in it ) and declare the module
   with `registerFederatedModule()`, or swap the admin page's `view` for a
   `component` — the framework renders its `cms::admin.layouts.federated` shell,
   a mount point inside the admin chrome, for the host runtime to hydrate.

## Local testing

Plugin authors typically ship their own test suite. A minimal `TestCase` looks
like:

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

Then write feature tests that exercise your admin page, hook subscriptions,
and any custom field types.

## Further reading

- [Plugin author guide](../../docs/plugin-authoring.md)
- [Hooks & events](../../docs/Hooks-and-Events.md)

---
title: Themes - Lifecycle Hooks
---

# Theme Lifecycle Hooks

The Themes module fires action hooks at four points in a theme's lifecycle — two on activation, two on installation. Hooks use the `artisanpack-ui/hooks` package (`doAction()` / `addAction()`).

> **Added in 2.0.0.**

## Activation hooks

`ThemeManager::activateTheme($slug)` fires:

### `theme.activating`

- **Fired:** before the active-theme setting changes.
- **Arguments:** `(string $slug, ThemeContract $theme)`
- **Listener may throw:** yes — a thrown exception short-circuits activation and the prior theme stays active.
- **Typical uses:** validate the incoming theme against app-side preconditions, clear theme-scoped caches, write an audit log.

### `theme.activated`

- **Fired:** after the active-theme setting is updated and view paths are re-registered.
- **Arguments:** `(string $slug, ThemeContract $theme)`
- **Listener may throw:** yes, but the change is already committed — throwing rolls back nothing.
- **Typical uses:** warm caches that depend on the active theme, send a "theme switched" notification, fire a webhook.

## Installation hooks

`ThemeManager::installFromZip($zipPath)` fires:

### `theme.installing`

- **Fired:** after successful extraction and strict manifest validation, **before** the install is committed.
- **Arguments:** `(string $slug, array $manifest)`
- **Listener may throw:** yes — a thrown exception aborts the install. The extracted directory is rolled back so you never end up with a half-installed theme.
- **Typical uses:** verify the installing user has permission, check a license server, scan the extracted files for unwanted contents.

### `theme.installed`

- **Fired:** after the install is committed.
- **Arguments:** `(string $slug, array $manifest)`
- **Listener may throw:** technically yes, but the install is already on disk; throwing won't roll it back.
- **Typical uses:** run theme-shipped seeders, queue a thumbnail-generation job, notify the user, fire a webhook.

## Registering listeners

Register with `addAction()`:

```php
use function addAction;

// In a service provider's boot()
addAction('theme.activating', function (string $slug, $theme) {
    if (! auth()->user()?->can('themes.activate')) {
        throw new \RuntimeException('Not allowed to activate themes.');
    }
});

addAction('theme.installed', function (string $slug, array $manifest) {
    \Log::info('Theme installed', ['slug' => $slug, 'version' => $manifest['version'] ?? 'unknown']);

    \App\Jobs\GenerateThemeThumbnail::dispatch($slug);
});
```

## Listener priority

`addAction()` accepts an optional priority argument — lower numbers run first, default is 10. Use this to ensure your listener runs before or after another package's listener:

```php
addAction('theme.installing', fn () => /* runs first */, 5);
addAction('theme.installing', fn () => /* runs default */);
addAction('theme.installing', fn () => /* runs last */, 20);
```

## Order of operations

```
activateTheme($slug):
  1. discoverThemes() (refresh cache)
  2. resolveTheme($slug)
  3. doAction('theme.activating', $slug, $theme) ← may throw to abort
  4. update settings['themes.activeTheme'] = $slug
  5. re-register view paths
  6. doAction('theme.activated', $slug, $theme)

installFromZip($zipPath):
  1. validateZip($zipPath)
  2. extractZip($zipPath) → temporary directory
  3. validateManifest($extractedManifest) ← may throw, rolls back
  4. doAction('theme.installing', $slug, $manifest) ← may throw, rolls back
  5. move extracted directory into themes path
  6. doAction('theme.installed', $slug, $manifest)
```

## Removing listeners

Use `removeAction()` to unregister a specific callback, or `removeAllActions()` to clear everything at a given priority (or for the whole hook):

```php
use function removeAction;
use function removeAllActions;

$callback = fn (string $slug) => \Log::info('Activated', ['slug' => $slug]);
addAction('theme.activated', $callback);

// Remove just this callback
removeAction('theme.activated', $callback);

// Remove all callbacks at priority 5
removeAllActions('theme.activated', 5);

// Remove every listener for the hook
removeAllActions('theme.activated');
```

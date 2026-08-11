---
title: Themes - Updating an Installed Theme
---

# Updating an Installed Theme

Themes can be updated in place from a GitHub or GitLab Release, with the same
check → download → verify → install → roll back path plugins have. Before this
existed, updating an installed theme meant deleting its directory by hand and
re-uploading — on a server with no repo checked out, an SFTP session.

> **Added in 2.8.0.**

## Declaring an update source

Add an optional `update` object to your `theme.json`. It is spelled exactly like
the plugin manifest key, so the two never drift:

```json
{
    "slug": "artisanpack-ui",
    "name": "ArtisanPack UI",
    "version": "1.0.0",
    "update": {
        "github": "ArtisanPack-UI/artisanpack-ui-theme"
    }
}
```

| Key      | Value | Notes |
| -------- | ----- | ----- |
| `github` | `owner/repo`, or a full `https://github.com/owner/repo` URL | Shorthand for the GitHub Releases source. |
| `url`    | Absolute `https://` URL | Handed to the source detector as-is, so GitLab repository URLs and custom JSON endpoints work through the same key. |

Both forms are https-only, and the requirement is re-checked on every read
rather than trusted from install time. An update seats a new manifest on disk,
and a plaintext metadata fetch lets a network attacker choose both the download
URL *and* the `sha256` it is verified against — the digest and the archive come
from the same document, so verification would confirm the attacker's own
archive.

A theme with no `update` key simply has no update source, exactly as before.
Nothing about existing themes changes.

## Publishing a release

Publishing an update is `git tag` plus a release. **Attach a real ZIP asset**
whose single top-level directory is your theme slug. The updater walks your
releases, skips prereleases, and picks the first asset ending in `.zip`,
falling back to the generated `zipball_url` — whose root directory is named for
the repository and commit rather than your slug, which is the wrong directory
name for a theme.

### Checksums

The downloaded archive is verified against a SHA-256 digest resolved from
either a `{your-asset}.zip.sha256` sidecar asset or a `SHA-256: <64 hex chars>`
line in the release description. With the shipped defaults
(`cms.updates.verify_checksum = true`, `cms.updates.allow_unverified_updates =
false`) a release publishing neither is refused. Add a step to your release
workflow:

```bash
sha256sum my-theme.zip | cut -d' ' -f1 > my-theme.zip.sha256
```

This is an integrity check, not an authenticity check — the digest comes from
the same release as the archive. It catches truncation and CDN corruption; it
does not defend against a compromised release-editor account.

## Checking and installing

```php
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\UpdateManager;

$updateManager = app(UpdateManager::class);

// Every installed theme that has a newer release, keyed by slug.
$updates = $updateManager->checkForUpdates();

// One theme; null when it declares no source or is already current.
$updateInfo = $updateManager->checkThemeUpdate('artisanpack-ui');

// Returns false when the theme is already current.
$updateManager->updateTheme('artisanpack-ui');
```

Checks are cached for `cms.themes.updateCacheTtl` (12 hours by default),
including negative results, and forgotten automatically after a successful
update.

A check that could not be completed — a rate-limited or unreachable source, a
malformed response — **throws** rather than returning null, and is not cached.
"The source says nothing is newer" and "we never reached the source" are
different answers, and reporting the second as the first would tell you your
theme is current when nothing was ever checked. `checkForUpdates()` is the
exception: it logs and skips an unreachable theme so one broken repository
cannot hide every other theme's available update.

### REST endpoints

Both sit behind the same `auth:sanctum` group as the rest of the themes API.

```
GET  /v1/themes/updates
POST /v1/themes/{slug}/update
```

`GET /v1/themes/updates` returns updates keyed by slug, in the same shape the
plugins endpoint returns, so one admin component can render both extension
types:

```json
{
    "updates": {
        "artisanpack-ui": {
            "version": "2.0.0",
            "current": "1.0.0",
            "download_url": "https://github.com/owner/repo/releases/download/v2.0.0/theme.zip",
            "changelog": "…",
            "release_date": "2026-08-01T00:00:00Z",
            "sha256": "…",
            "file_size": 24576,
            "metadata": { "source": "github" }
        }
    }
}
```

`POST /v1/themes/{slug}/update` returns `updated: false` when the theme is
already current, 404 when it is not installed, and a `ValidationException`
error bag (HTTP 422, keyed `slug`) when the update fails — so host apps using
Inertia get a working error bag instead of an unhandled response.

## What happens during an update

1. `ap.cmsFramework.theme.updating` fires with `( $slug, $oldVersion,
   $newVersion )`. Listeners may veto by throwing; nothing has changed yet, so
   a veto costs nothing.
2. The archive is downloaded — streamed to disk, https enforced across the
   request and every redirect — and checksum-verified.
3. It is extracted into `themes/.updates/` and fully validated there: ZIP-slip
   guard, schema validation, strict manifest validation, and the assertion that
   the manifest slug matches both the extracted directory *and* the theme being
   updated. A bad archive is rejected here, before anything touches the live
   theme.
4. The installed directory is archived to `cms.themes.backupPath`
   (`storage/theme-backups` by default). This happens *after* staging, not
   before: there is no point copying the whole theme for an update that never
   produced a valid replacement, and a release published without a checksum
   fails on every retry — backing up first would write another full copy each
   time.
5. The staged directory is swapped into place.
6. The discovery and update caches are forgotten, view paths are re-registered,
   and the compiled view cache is cleared if the updated theme is active.
7. `ap.cmsFramework.theme.updated` fires with `( $slug, $newVersion, $manifest )`.

A failure at or after step 5 restores from the backup. The restore deletes the
live directory outright before re-extracting, so files the failed update
*added* are removed rather than left orphaned alongside the restored ones.

## Updating the active theme

The active theme can be updated without dropping the site into maintenance
mode. That is a deliberate choice, not an accident of implementation.

The application updater uses maintenance mode because it also runs migrations
and `composer install` — a window measured in minutes during which the app is
genuinely inconsistent. A theme update replaces static files, and the staging
step above means the swap is two `rename()` calls on the same filesystem: the
installed directory is moved aside, then the staged directory takes its name.
The window during which the active theme's directory does not exist is a single
syscall wide, rather than however long it takes to delete and re-extract a
theme's worth of files.

If the second rename fails, the first is undone and the installed theme is left
exactly as it was.

The staging root lives at `themes/.updates/` rather than under `storage/` so
those renames are guaranteed to stay on one filesystem even when
`cms.themes.directory` points at an external mount. It is created on demand,
its contents are cleaned up after every update, and it is skipped by theme
discovery.

## Private repositories

Public repositories need no credentials. For a private one, the host adds a
token keyed by theme slug:

```php
// config/cms.php
'themes' => [
    'updateTokens' => [
        'my-private-theme' => env( 'MY_PRIVATE_THEME_UPDATE_TOKEN' ),
    ],
],
```

Tokens live in host config, never in `theme.json` — the manifest ships inside
the distributed ZIP. They are deliberately per-slug rather than one global
token: a theme names its own update host in its own manifest, so a shared token
would be handed to whatever host any installed theme asks for.

## Configuration

| Key | Default | Purpose |
| --- | ------- | ------- |
| `cms.themes.updateCacheTtl` | `43200` | Seconds an update check is cached. |
| `cms.themes.backupPath` | `theme-backups` | Backup directory, relative to `storage_path()`. |
| `cms.themes.maxUpdateSize` | `52428800` | Size ceiling for a downloaded update archive. Separate from `maxUploadSize`, which is an abuse control on the upload endpoint — a theme shipping images and fonts clears 10MB easily, and gating updates on the upload ceiling would leave it permanently un-updatable. |
| `cms.themes.updateTokens` | `[]` | Per-slug tokens for private update sources. |
| `cms.updates.verify_checksum` | `true` | Shared with the application and plugin updaters. |
| `cms.updates.allow_unverified_updates` | `false` | Shared; allows a release with no published digest. |

## Related

- [[themes/Installing From Zip]] — first-time install of a theme from a ZIP
- [[themes/Lifecycle Hooks]] — the full set of theme lifecycle hooks
- [[plugin-authoring]] — the plugin-side equivalent of this guide

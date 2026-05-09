<?php

/**
 * Entity Resolver Contract
 *
 * Site-editor entities (templates, template parts, patterns, global styles,
 * navigation menus) are resolved through implementations of this interface.
 * Each implementation merges DB overrides with theme-file fallbacks per
 * plan 14 §4.2 — DB row wins when present.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

/**
 * @since 1.2.0
 */
interface EntityResolver
{
    /**
     * Resolve an entity by slug for the active theme.
     *
     * Returns the merged authoritative content: DB row if one exists for
     * the active theme + slug, otherwise the theme file. Returns null
     * when neither source has the slug.
     *
     * @since 1.2.0
     */
    public function resolve(string $slug): ?ResolvedEntity;

    /**
     * List all entities for the active theme (file + DB merged).
     *
     * Returns one ResolvedEntity per unique slug, with DB rows taking
     * precedence over theme files for slugs present in both.
     *
     * @since 1.2.0
     *
     * @return array<int, ResolvedEntity>
     */
    public function all(): array;

    /**
     * Revert an entity to its theme-file source by deleting the DB override.
     *
     * Returns true when a DB row was deleted, false when no DB row existed.
     * For purely custom entities (no theme-file backing), this deletes the
     * entity entirely.
     *
     * @since 1.2.0
     */
    public function revert(string $slug): bool;
}

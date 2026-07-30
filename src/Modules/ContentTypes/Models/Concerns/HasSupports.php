<?php

declare( strict_types=1 );

/**
 * HasSupports Trait
 *
 * Provides post-type `supports` schema resolution for content-type models.
 *
 * @since 2.6.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\SupportsFeature;

/**
 * Trait for exposing a canonical `supports` array on a content-type model.
 *
 * Mirrors WordPress's `post_type_supports()` pattern: each model declares
 * which editor panels/sections it supports so admin edit screens can render
 * accordingly. Three resolution modes are supported and checked in order:
 *
 * 1. The model returns a non-null array from {@see explicitSupports()} —
 *    used by {@see \ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType}
 *    to hand back the DB-persisted column.
 * 2. The model overrides {@see defaultSupports()} to return per-class defaults
 *    ( built-in Post and Page do this ).
 * 3. Fallback: `[title, editor]`, a minimal editor surface.
 *
 * Values are always filtered through {@see SupportsFeature::filter()} so a
 * stale or plugin-injected flag string can't reach downstream consumers.
 *
 * @since 2.6.0
 */
trait HasSupports
{
    /**
     * Return the effective supports array for this instance, as a list of
     * flag string values ( matching {@see SupportsFeature::values()} ).
     *
     * @since 2.6.0
     *
     * @return list<string>
     */
    public function supports(): array
    {
        $explicit = $this->explicitSupports();

        if ( null !== $explicit ) {
            return SupportsFeature::filter( $explicit );
        }

        $defaults = $this->defaultSupports();

        if ( [] === $defaults ) {
            return [ SupportsFeature::Title->value, SupportsFeature::Editor->value ];
        }

        return SupportsFeature::filter( $defaults );
    }

    /**
     * Whether this model supports a given feature. `title` is always on,
     * matching the "required — always on" note in the editor spec.
     *
     * Accepts either the enum case or its string value so callers can pass
     * `SupportsFeature::Editor` or the literal `'editor'` interchangeably.
     *
     * @since 2.6.0
     */
    public function supportsFeature( SupportsFeature|string $feature ): bool
    {
        $value = $feature instanceof SupportsFeature ? $feature->value : $feature;

        if ( SupportsFeature::Title->value === $value ) {
            return true;
        }

        return in_array( $value, $this->supports(), true );
    }

    /**
     * Per-model default supports. Override on Post/Page ( and any host model
     * that opts into the trait ) to declare which flags are on by default.
     * The default here is a minimal `[title, editor]` set so a model that
     * opts in without overriding still renders the basic editor surface.
     *
     * @since 2.6.0
     *
     * @return list<string>
     */
    protected function defaultSupports(): array
    {
        return [ SupportsFeature::Title->value, SupportsFeature::Editor->value ];
    }

    /**
     * Explicit ( per-instance ) supports array. Returns `null` when the model
     * has no instance-scoped source and callers should fall back to
     * {@see defaultSupports()}. {@see \ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType}
     * overrides this to return the DB-persisted column.
     *
     * @since 2.6.0
     *
     * @return list<string>|null
     */
    protected function explicitSupports(): ?array
    {
        return null;
    }
}

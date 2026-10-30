<?php

declare( strict_types=1 );

/**
 * HasPermalink Trait
 *
 * Provides the filterable `permalink` accessor shared by content models.
 *
 * @since 2.17.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

/**
 * Trait for resolving a content model's public URL.
 *
 * The using model supplies its default URL and filter hook name. Apps with
 * their own permalink structure replace the URL through that hook.
 *
 * @since 2.17.0
 */
trait HasPermalink
{
    /**
     * Get the public URL for the model.
     *
     * A callback may return an absolute URL or a root-relative path such as
     * `/`, which is made absolute. A callback that returns anything other
     * than a non-empty string is ignored and the default URL is returned.
     * Callbacks must build their URL from the model's fields, because reading
     * `$model->permalink` inside a callback runs the filter again.
     *
     * @since 2.17.0
     */
    public function getPermalinkAttribute(): string
    {
        $default   = $this->defaultPermalink();
        $permalink = applyFilters( $this->permalinkHook(), $default, $this );

        return is_string( $permalink ) && '' !== $permalink ? url( $permalink ) : $default;
    }

    /**
     * Get the URL used when no callback replaces it.
     *
     * @since 2.17.0
     */
    abstract protected function defaultPermalink(): string;

    /**
     * Get the name of the filter hook that can replace the URL.
     *
     * @since 2.17.0
     */
    abstract protected function permalinkHook(): string;
}

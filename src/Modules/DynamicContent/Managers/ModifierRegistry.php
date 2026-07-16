<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts\Modifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\DateModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\DefaultModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\LowerModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\Nl2brModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\TimeModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\TruncateModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\UpperModifier;

/**
 * Registry of built-in token modifiers.
 *
 * Sealed to public API in v1: only the built-in modifiers are exposed.
 *
 * @since 2.4.0
 */
class ModifierRegistry
{
    /**
     * @var array<string, Modifier>
     */
    protected array $modifiers = [];

    public function __construct()
    {
        foreach ( [
            new DefaultModifier(),
            new UpperModifier(),
            new LowerModifier(),
            new TruncateModifier(),
            new DateModifier(),
            new TimeModifier(),
            new Nl2brModifier(),
        ] as $modifier ) {
            $this->modifiers[ $modifier->slug() ] = $modifier;
        }
    }

    public function get( string $slug ): ?Modifier
    {
        return $this->modifiers[ $slug ] ?? null;
    }

    /**
     * @return array<string, Modifier>
     */
    public function all(): array
    {
        return $this->modifiers;
    }
}

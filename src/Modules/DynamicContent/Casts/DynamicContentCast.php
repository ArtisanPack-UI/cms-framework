<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Casts;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services\DynamicContentResolver;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent cast that auto-resolves dynamic-content tokens on attribute access.
 *
 * Usage in a model:
 *   protected function casts(): array {
 *       return [ 'body' => DynamicContentCast::class ];
 *   }
 *
 * SECURITY: dynamic content is a **public content pipeline** by design —
 * every record and every field is readable by anyone who can write a
 * token to a casted attribute. Do NOT store secrets (API keys, credentials,
 * private user data) in dynamic content records. If a low-trust author can
 * write to a casted attribute, they can render any dynamic-content field
 * into the resolved output. Restrict `manage_dynamic_content` accordingly
 * and keep sensitive configuration in {@see \ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting}
 * or environment variables instead.
 *
 * The resolver escapes text-shaped field values at render time (see
 * {@see \ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes\AbstractFieldType})
 * — rich-text fields are NOT escaped because that's their point. Hosts
 * accepting untrusted rich-text authors must sanitize at save time
 * (e.g. via `artisanpack-ui/security`'s `kses()` helper).
 *
 * @implements CastsAttributes<string, string>
 *
 * @since 2.4.0
 */
class DynamicContentCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get( Model $model, string $key, mixed $value, array $attributes ): ?string
    {
        if ( null === $value ) {
            return null;
        }

        return app( DynamicContentResolver::class )->render(
            (string) $value,
            [ 'model' => $model::class, 'model_id' => $model->getKey(), 'attribute' => $key ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set( Model $model, string $key, mixed $value, array $attributes ): mixed
    {
        return $value;
    }
}

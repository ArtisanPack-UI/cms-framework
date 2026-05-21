<?php

declare( strict_types=1 );

/**
 * Registered Settings Request.
 *
 * Validates by-key bulk-update payloads for the `PUT /api/v1/settings`
 * endpoint. The body is a `settings` map of `key => value` pairs; every
 * key must correspond to a setting registered via
 * `SettingsManager::registerSetting()` so the controller can route each
 * write through the manager (applying the per-key sanitizer and type).
 *
 * @since   1.2.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for the registered-settings bulk-update endpoint.
 *
 * Authorization is delegated to the controller's `SettingPolicy` check;
 * this request only guarantees the payload shape and that each supplied
 * key is registered.
 *
 * @since 1.2.0
 */
class RegisteredSettingsRequest extends FormRequest
{
    /**
     * Authorization is delegated to the controller's policy check.
     *
     * @since 1.2.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for the bulk-update body.
     *
     * Per-key registration is enforced in {@see withValidator()} because
     * setting keys are dynamic (and dotted, e.g. `site.title`), which the
     * static rule array cannot express.
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * Reject any keys in the payload that are not registered settings.
     *
     * Routing an unregistered key through `SettingsManager::updateSetting()`
     * would store a raw, untyped value — the exact gap this endpoint exists
     * to close — so unknown keys fail validation instead.
     *
     * @since 1.2.0
     *
     * @param  Validator  $validator  The validator instance for this request.
     */
    public function withValidator( Validator $validator ): void
    {
        $validator->after( function ( Validator $validator ): void {
            $settings = $this->input( 'settings' );

            if ( ! is_array( $settings ) ) {
                return;
            }

            $registered = array_keys( applyFilters( 'ap.settings.registeredSettings', [] ) );

            foreach ( array_keys( $settings ) as $key ) {
                if ( ! in_array( $key, $registered, true ) ) {
                    $validator->errors()->add(
                        "settings.{$key}",
                        __( 'The setting ":key" is not registered.', ['key' => $key] ),
                    );
                }
            }
        });
    }
}

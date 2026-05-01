<?php

/**
 * WordPress theme.json Validator
 *
 * Validates the WP-shape subset of a cms-framework theme manifest against
 * a pinned WordPress theme.json schema, plus the cms-framework `menus.locations`
 * extension.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Validation;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use RuntimeException;

/**
 * Validates the WP-shape subset of a theme manifest against the pinned
 * WordPress theme.json schema, plus the cms-framework `menus.locations`
 * extension.
 *
 * cms-framework's manifest fields (`name`, `version`, `description`, `author`,
 * `screenshot`, `slug`) are intentionally not run through the WP schema:
 * the WP schema declares `additionalProperties: false` at the top level and
 * types `version` as the integer schema-version (`const: 3`), which conflicts
 * with cms-framework's semver-string `version`. Instead, this validator
 * extracts only the WP-shape keys and validates them as a synthetic minimal
 * object.
 *
 * @since 1.2.0
 */
class WpThemeJsonValidator
{
    /**
     * Top-level keys defined by the WordPress theme.json schema.
     *
     * @since 1.2.0
     */
    private const WP_SHAPE_KEYS = [
        'settings',
        'styles',
        'customTemplates',
        'templateParts',
        'patterns',
    ];

    /**
     * Validate a parsed theme manifest.
     *
     * Themes carrying none of the WP-shape keys and no `menus` extension
     * pass through unchecked (backwards-compatible with pre-Phase-H themes).
     *
     * @since 1.2.0
     *
     * @param  array  $manifest  Decoded theme.json contents.
     */
    public function validate( array $manifest ): WpThemeJsonValidationResult
    {
        $present = array_intersect_key( $manifest, array_flip( self::WP_SHAPE_KEYS ) );

        if ( [] === $present && ! array_key_exists( 'menus', $manifest ) ) {
            return WpThemeJsonValidationResult::success();
        }

        if ( [] !== $present ) {
            $wpResult = $this->validateAgainstWpSchema( $present );

            if ( ! $wpResult->valid ) {
                return $wpResult;
            }
        }

        if ( array_key_exists( 'menus', $manifest ) ) {
            $menusResult = $this->validateMenusExtension( $manifest['menus'] );

            if ( ! $menusResult->valid ) {
                return $menusResult;
            }
        }

        return WpThemeJsonValidationResult::success();
    }

    /**
     * Run the WP-shape subset through the pinned WP theme.json schema.
     *
     * @since 1.2.0
     *
     * @param  array  $wpShapeSubset  Manifest keys that overlap with the WP schema.
     */
    protected function validateAgainstWpSchema( array $wpShapeSubset ): WpThemeJsonValidationResult
    {
        $version = (string) config( 'cms.themes.wpThemeJsonSchemaVersion', '3' );
        $schema  = $this->loadSchema( $version );

        $subject = json_decode( json_encode( [ 'version' => (int) $version ] + $wpShapeSubset ) );

        $validator = new Validator();
        $validator->validate( $subject, $schema, Constraint::CHECK_MODE_TYPE_CAST );

        if ( $validator->isValid() ) {
            return WpThemeJsonValidationResult::success();
        }

        $errors = $validator->getErrors();
        $first  = $errors[0] ?? [
            'property' => '',
            'message'  => 'Unknown schema validation error',
        ];
        $key = '' !== $first['property'] ? $first['property'] : '(root)';

        return WpThemeJsonValidationResult::failure( $key, $first['message'] );
    }

    /**
     * Validate the cms-framework `menus.locations` extension shape.
     *
     * `menus` must be an object containing only `locations` (no other sibling
     * keys are recognised). `menus.locations` must be an object mapping
     * string location keys to string display labels.
     *
     * @since 1.2.0
     *
     * @param  mixed  $menus  Value of the manifest's `menus` key.
     */
    protected function validateMenusExtension( mixed $menus ): WpThemeJsonValidationResult
    {
        if ( ! is_array( $menus ) || ( [] !== $menus && array_is_list( $menus ) ) ) {
            return WpThemeJsonValidationResult::failure( 'menus', 'menus must be an object.' );
        }

        $unknownKeys = array_diff( array_keys( $menus ), [ 'locations' ] );

        if ( [] !== $unknownKeys ) {
            return WpThemeJsonValidationResult::failure(
                'menus',
                'menus may only contain the "locations" key; unknown keys: ' . implode( ', ', $unknownKeys ) . '.',
            );
        }

        if ( ! array_key_exists( 'locations', $menus ) ) {
            return WpThemeJsonValidationResult::success();
        }

        $locations = $menus['locations'];

        if ( ! is_array( $locations ) || array_is_list( $locations ) ) {
            return WpThemeJsonValidationResult::failure(
                'menus.locations',
                'menus.locations must be an object mapping location keys to display labels.',
            );
        }

        foreach ( $locations as $key => $label ) {
            if ( ! is_string( $key ) || '' === $key ) {
                return WpThemeJsonValidationResult::failure(
                    'menus.locations',
                    'menus.locations keys must be non-empty strings.',
                );
            }

            if ( ! is_string( $label ) ) {
                return WpThemeJsonValidationResult::failure(
                    "menus.locations.{$key}",
                    "menus.locations.{$key} must be a string label.",
                );
            }
        }

        return WpThemeJsonValidationResult::success();
    }

    /**
     * Load the bundled WP theme.json schema for the given version.
     *
     * @since 1.2.0
     *
     * @param  string  $version  Schema version (e.g. `'3'`).
     *
     * @throws RuntimeException If the bundled schema file is missing or malformed.
     */
    protected function loadSchema( string $version ): object
    {
        $path = __DIR__ . "/schemas/wp-theme-json-v{$version}.json";

        if ( ! is_file( $path ) ) {
            throw new RuntimeException(
                "Bundled WP theme.json schema for version {$version} not found at {$path}.",
            );
        }

        $schema = json_decode( file_get_contents( $path ) );

        if ( ! is_object( $schema ) ) {
            throw new RuntimeException(
                "Bundled WP theme.json schema for version {$version} is not valid JSON.",
            );
        }

        return $schema;
    }
}

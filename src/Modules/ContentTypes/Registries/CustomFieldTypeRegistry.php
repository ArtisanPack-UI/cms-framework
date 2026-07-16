<?php

declare( strict_types=1 );

/**
 * CustomFieldTypeRegistry.
 *
 * Registry for custom-field types. Ships with the framework's 16 built-in
 * `FieldType` enum cases pre-registered and merges plugin-registered types
 * from the `ap.contentTypes.registeredFieldTypes` filter on every read.
 *
 * The closed `FieldType` enum remains supported for backwards compatibility;
 * new code should register plugin field types through this registry so the
 * admin UI, validation, and `HasCustomFields` runtime all recognize them.
 *
 * @since 2.4.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Registries;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\FieldType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Support\FieldTypeDefinition;
use Throwable;

/**
 * In-memory registry keyed by field-type slug.
 *
 * @since 2.4.0
 */
class CustomFieldTypeRegistry
{
    /**
     * Column-type mapping for the built-in enum cases, keeping DB-materialized
     * fields storage-shape consistent with the legacy behavior.
     *
     * @var array<string,string>
     */
    protected const BUILTIN_COLUMN_TYPES = [
        'text'     => 'string',
        'textarea' => 'text',
        'number'   => 'integer',
        'select'   => 'string',
        'checkbox' => 'boolean',
        'radio'    => 'string',
        'boolean'  => 'boolean',
        'date'     => 'date',
        'datetime' => 'datetime',
        'time'     => 'time',
        'email'    => 'string',
        'url'      => 'string',
        'tel'      => 'string',
        'color'    => 'string',
        'file'     => 'string',
        'image'    => 'string',
    ];

    /**
     * Locally registered ( non-filter ) field types keyed by slug.
     *
     * @var array<string,FieldTypeDefinition>
     */
    protected array $types = [];

    public function __construct()
    {
        $this->registerBuiltins();
    }

    /**
     * Register a field type. Overwrites any prior registration with the same slug.
     *
     * @since 2.4.0
     */
    public function register( FieldTypeDefinition $definition ): void
    {
        $this->types[ $definition->slug ] = $definition;
    }

    /**
     * Fetch a single field-type definition by slug, merging local registrations
     * and filter-registered plugin types. Returns null when nothing matches.
     *
     * @since 2.4.0
     */
    public function get( string $slug ): ?FieldTypeDefinition
    {
        return $this->all()[ $slug ] ?? null;
    }

    /**
     * Return every known field-type definition keyed by slug. Locally
     * registered entries take precedence over filter-registered ones.
     *
     * @since 2.4.0
     *
     * @return array<string,FieldTypeDefinition>
     */
    public function all(): array
    {
        $filtered = applyFilters( 'ap.contentTypes.registeredFieldTypes', [] );

        $out = [];
        if ( is_array( $filtered ) ) {
            foreach ( $filtered as $key => $entry ) {
                $definition = $this->normalizeFilterEntry( $key, $entry );
                if ( null !== $definition ) {
                    $out[ $definition->slug ] = $definition;
                }
            }
        }

        // Locally registered types overwrite filter entries with matching slugs.
        foreach ( $this->types as $slug => $definition ) {
            $out[ $slug ] = $definition;
        }

        return $out;
    }

    /**
     * Whether the registry knows about the given field-type slug.
     *
     * @since 2.4.0
     */
    public function has( string $slug ): bool
    {
        return null !== $this->get( $slug );
    }

    /**
     * Convenience: list every known slug ( e.g. for `Rule::in(...)` validation ).
     *
     * @since 2.4.0
     *
     * @return array<int,string>
     */
    public function slugs(): array
    {
        return array_values( array_keys( $this->all() ) );
    }

    /**
     * Pre-register every built-in `FieldType` enum case so consumers of the
     * registry ( admin field-type dropdown, validation, HasCustomFields ) always
     * see the framework defaults even before any plugin has loaded.
     *
     * @since 2.4.0
     */
    protected function registerBuiltins(): void
    {
        foreach ( FieldType::cases() as $case ) {
            $slug = $case->value;

            $this->register( new FieldTypeDefinition(
                slug       : $slug,
                label      : $case->label(),
                columnType : self::BUILTIN_COLUMN_TYPES[ $slug ] ?? 'string',
                meta       : ['builtin' => true],
            ) );
        }
    }

    /**
     * Coerce a raw filter entry into a `FieldTypeDefinition`. Accepts either
     * a `FieldTypeDefinition` instance or an array literal. Skips malformed
     * entries silently rather than crashing the registry.
     *
     * @since 2.4.0
     *
     * @param  int|string  $key
     */
    protected function normalizeFilterEntry( int|string $key, mixed $entry ): ?FieldTypeDefinition
    {
        if ( $entry instanceof FieldTypeDefinition ) {
            return $entry;
        }

        if ( ! is_array( $entry ) ) {
            return null;
        }

        if ( ! isset( $entry['slug'] ) ) {
            if ( ! is_string( $key ) || '' === $key ) {
                return null;
            }
            $entry['slug'] = $key;
        }

        // Reject malformed plugin payloads before they reach FieldTypeDefinition
        // — a single non-scalar slug would otherwise fatal via `(string)` cast
        // and take down every consumer of the registry.
        if ( ! $this->hasScalarString( $entry, 'slug' ) ) {
            return null;
        }
        if ( '' === trim( ( string ) $entry['slug'] ) ) {
            return null;
        }
        foreach ( ['label', 'column_type', 'columnType', 'editor_component', 'editorComponent', 'renderer_component', 'rendererComponent'] as $optionalString ) {
            if ( isset( $entry[ $optionalString ] ) && ! $this->hasScalarString( $entry, $optionalString ) ) {
                return null;
            }
        }

        try {
            return FieldTypeDefinition::fromArray( $entry );
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * Whether `$entry[$key]` is present and scalar-string-safe.
     *
     * @since 2.4.0
     *
     * @param  array<string,mixed>  $entry
     */
    protected function hasScalarString( array $entry, string $key ): bool
    {
        if ( ! isset( $entry[ $key ] ) ) {
            return false;
        }

        $value = $entry[ $key ];

        return is_string( $value ) || is_int( $value ) || is_float( $value )
            || ( is_object( $value ) && method_exists( $value, '__toString' ) );
    }
}

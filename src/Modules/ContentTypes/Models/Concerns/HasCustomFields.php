<?php

declare( strict_types=1 );

/**
 * HasCustomFields Trait
 *
 * Provides custom fields functionality for content types.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Trait for adding custom fields support to models.
 *
 * DB-registered fields ( storage = `column` ) resolve through the model's
 * physical column. Filter-registered fields ( storage = `metadata` ) resolve
 * through the model's `metadata` JSON column, falling back to `default_value`.
 *
 * Models using filter-registered custom fields must expose a `metadata` JSON
 * attribute cast to `array` — Post, Page, and any host content type intending
 * to accept plugin-registered custom fields should follow that convention.
 *
 * @since 1.0.0
 */
trait HasCustomFields
{
    /**
     * Per-model-class cache of the real DB columns for the model's table.
     * Populated on first read so we never mistake a real column for a
     * plugin-registered custom-field key.
     *
     * @var array<class-string,array<int,string>>
     */
    protected static array $customFieldsRealColumnsCache = [];

    /**
     * Per-instance cache of the custom-field collection for this model's
     * content type. Cleared implicitly when the instance is destroyed.
     *
     * @var Collection<int,CustomField>|null
     */
    protected ?Collection $customFieldsCache = null;

    /**
     * Magic getter for custom field values.
     *
     * @since 1.0.0
     *
     * @param  string  $key
     *
     * @return mixed
     */
    public function __get( $key )
    {
        $field = $this->findCustomFieldByKey( $key );

        if ( null !== $field && 'metadata' === $field->storageMode() ) {
            $metadata = ( array ) ( $this->getAttribute( 'metadata' ) ?? [] );

            return array_key_exists( $key, $metadata ) ? $metadata[ $key ] : $field->default_value;
        }

        $value = parent::__get( $key );

        if ( null === $value && null !== $field && 'column' === $field->storageMode() ) {
            return $field->default_value;
        }

        return $value;
    }

    /**
     * Magic setter for custom field values.
     *
     * Assignment here is trusted: a key naming a real column falls through
     * to `parent::__set()` so host code keeps writing its own columns even
     * while a plugin has that key filter-registered as a custom field.
     * Untrusted custom-field payloads must go through
     * {@see applyCustomFieldValues()}, which drops shadowing keys first.
     *
     * @since 1.0.0
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function __set( $key, $value ): void
    {
        $field = $this->findCustomFieldByKey( $key );

        if ( null !== $field && 'metadata' === $field->storageMode() ) {
            $this->assertMetadataColumnAvailable( $key );

            $metadata         = ( array ) ( $this->getAttribute( 'metadata' ) ?? [] );
            $metadata[ $key ] = $value;
            $this->setAttribute( 'metadata', $metadata );

            return;
        }

        parent::__set( $key, $value );
    }

    /**
     * Apply an untrusted map of custom-field values onto this model.
     *
     * Every value routes through the magic setter above, so a
     * metadata-storage field lands in the `metadata` JSON column exactly as
     * a direct `$model->key = $value` write would. The difference is the
     * guard: a key that names a real DB column, cast, mutator, accessor, or
     * relation is **silently dropped** rather than written, unless it is a
     * DB-persisted column-storage custom field ( see
     * {@see isPersistedColumnCustomField()} ), whose whole purpose is to
     * write its own column.
     *
     * That guard is the whole point of routing custom-field payloads through
     * here instead of assigning them directly. The payload originates in
     * request input, and the keys it may carry are whatever a plugin has
     * filter-registered — neither is trusted. Without the check, a payload
     * key naming a real column (`author_id`, `status`, `password` on a host
     * model) falls through the trait's setter to `parent::__set()` and
     * overwrites the column, sidestepping the fillable allowlist the caller
     * applied to the attribute half of the same request. Dropping the key is
     * safe because a shadowing field can never round-trip anyway: the getter
     * resolves a real column through Eloquent, never through metadata.
     *
     * The magic setter itself deliberately does *not* apply this guard —
     * `$post->title = 'New title'` from host code must keep working even
     * while a rogue plugin has `title` filter-registered as a custom field.
     * Trusted assignment and untrusted payload application are different
     * operations; this method is the untrusted one.
     *
     * @since 2.7.1
     *
     * @param  array<string,mixed>  $values  Custom-field values keyed by field key.
     */
    public function applyCustomFieldValues( array $values ): void
    {
        foreach ( $values as $key => $value ) {
            $key = ( string ) $key;

            if ( $this->isReservedModelAttribute( $key ) && ! $this->isPersistedColumnCustomField( $key ) ) {
                continue;
            }

            $this->{$key} = $value;
        }
    }

    /**
     * Get the custom fields for the content type. Memoized per instance so a
     * single Blade render / JSON serialization doesn't re-run the DB query on
     * every unknown attribute access.
     *
     * @since 1.0.0
     */
    public function getCustomFieldsForType(): Collection
    {
        if ( null !== $this->customFieldsCache ) {
            return $this->customFieldsCache;
        }

        return $this->customFieldsCache = app( CustomFieldManager::class )
            ->getFieldsForContentType( $this->getTable() );
    }

    /**
     * Clear the per-instance memoized custom-field list. Called automatically
     * on save/delete via Eloquent's model events; hosts can call it manually
     * after registering a new filter-scoped field mid-request.
     *
     * @since 2.4.0
     */
    public function flushCustomFieldsCache(): void
    {
        $this->customFieldsCache = null;
    }

    /**
     * Wire up Eloquent model events so the memoized custom-field list is
     * flushed at the right lifecycle points.
     *
     * @since 2.4.0
     */
    protected static function bootHasCustomFields(): void
    {
        static::saved( fn ( $model ) => $model->flushCustomFieldsCache() );
        static::deleted( fn ( $model ) => $model->flushCustomFieldsCache() );
    }

    /**
     * Locate a custom field for this model by key. Returns null when the key
     * is not a registered custom field so callers can fall back to the normal
     * attribute pipeline.
     *
     * The lookup order is defensive: keys that name a real DB column, cast,
     * mutator, accessor, or relation short-circuit before we ever consult
     * the custom-field registry. This blocks a plugin from shadowing host
     * attributes ( e.g. `password`, `is_admin`, or the model's own `metadata`
     * column ) by registering a filter-scoped field with a colliding key.
     *
     * @since 2.4.0
     */
    protected function findCustomFieldByKey( string $key ): ?CustomField
    {
        if ( $this->isReservedModelAttribute( $key ) ) {
            return null;
        }

        foreach ( $this->getCustomFieldsForType() as $field ) {
            if ( $field->key === $key ) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Whether the given key already belongs to the host model — a real DB
     * column, a hydrated attribute, a cast, a mutator/accessor, or a
     * relation. Such a key can never resolve to a custom field, so both
     * {@see findCustomFieldByKey()} and {@see applyCustomFieldValues()}
     * short-circuit on it.
     *
     * @since 2.7.1
     */
    protected function isReservedModelAttribute( string $key ): bool
    {
        if ( array_key_exists( $key, $this->attributes ) ) {
            return true;
        }
        if ( array_key_exists( $key, $this->getCasts() ) ) {
            return true;
        }
        if ( $this->isRealDatabaseColumn( $key ) ) {
            return true;
        }
        if ( method_exists( $this, $key ) ) {
            return true;
        }
        if ( method_exists( $this, 'hasGetMutator' ) && $this->hasGetMutator( $key ) ) {
            return true;
        }
        if ( method_exists( $this, 'hasSetMutator' ) && $this->hasSetMutator( $key ) ) {
            return true;
        }
        if ( method_exists( $this, 'hasAttributeMutator' ) && $this->hasAttributeMutator( $key ) ) {
            return true;
        }
        if ( method_exists( $this, 'hasAttributeGetMutator' ) && $this->hasAttributeGetMutator( $key ) ) {
            return true;
        }
        if ( method_exists( $this, 'hasAttributeSetMutator' ) && $this->hasAttributeSetMutator( $key ) ) {
            return true;
        }
        if ( method_exists( $this, 'isRelation' ) && $this->isRelation( $key ) ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the key names a DB-persisted, column-storage custom field on
     * this content type — an admin-created field whose physical column
     * {@see CustomFieldManager::createField()} added to the table.
     *
     * Such a field is the one legitimate reason a custom-field payload may
     * carry a key that names a real column, so
     * {@see applyCustomFieldValues()} exempts it from the shadow-column
     * drop. Without the exemption, every DB-registered field's value would
     * be discarded — they are always column-storage, because the
     * `custom_fields` table has no `storage` column and
     * {@see CustomField::storageMode()} resolves a persisted row to
     * `column`.
     *
     * The `exists` check is the trust boundary. Filter-registered fields are
     * hydrated by `CustomFieldManager::filterFieldsForContentType()`, which
     * forces `exists = false` on every one, so a plugin cannot mint a field
     * that authorizes a real-column write — not even by declaring
     * `storage => 'column'` in its registration args. Only a row in the
     * `custom_fields` table qualifies, and writing one requires the
     * custom-field admin capability.
     *
     * @since 2.7.1
     */
    protected function isPersistedColumnCustomField( string $key ): bool
    {
        if ( ! $this->isRealDatabaseColumn( $key ) ) {
            return false;
        }

        foreach ( $this->getCustomFieldsForType() as $field ) {
            if ( $field->key === $key && $field->exists && 'column' === $field->storageMode() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the given key is a real column on this model's table. Cached
     * per model class so we don't hit `information_schema` on every read.
     *
     * @since 2.4.0
     */
    protected function isRealDatabaseColumn( string $key ): bool
    {
        $class = static::class;

        if ( ! isset( self::$customFieldsRealColumnsCache[ $class ] ) ) {
            $table = $this->getTable();
            try {
                self::$customFieldsRealColumnsCache[ $class ] = Schema::getColumnListing( $table );
            } catch ( Throwable ) {
                // Schema introspection can fail during boot or with drivers
                // that don't support column listing — fall back to an empty
                // listing so we don't cache a false negative permanently.
                return false;
            }
        }

        return in_array( $key, self::$customFieldsRealColumnsCache[ $class ], true );
    }

    /**
     * Ensure the host model actually exposes a `metadata` JSON attribute
     * before we try to write into it. Fail loud rather than silently drop
     * writes on models that opt into `HasCustomFields` but never added the
     * `metadata` column.
     *
     * @since 2.4.0
     */
    protected function assertMetadataColumnAvailable( string $key ): void
    {
        $casts = $this->getCasts();

        // `metadata` must be either an array-cast attribute or a real column.
        if ( isset( $casts['metadata'] ) ) {
            return;
        }

        if ( $this->isRealDatabaseColumn( 'metadata' ) ) {
            return;
        }

        throw new RuntimeException( sprintf(
            'Cannot write custom field "%s" via HasCustomFields on %s: the model has no `metadata` JSON column. '
            . 'Add a `metadata` JSON column ( cast to `array` ) or register the field as `storage = column` instead.',
            $key,
            static::class,
        ) );
    }
}

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
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Support\CustomFieldColumnCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
     * Payload keys already reported as dropped by this instance, so a large
     * probing payload produces one log line per key rather than a flood.
     *
     * @var array<string,true>
     */
    protected array $droppedCustomFieldKeys = [];

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

        // Substitute the default only when the attribute is *absent*, not
        // whenever it reads null. Treating a stored null as "unset" meant a
        // value an editor had deliberately cleared read back as the default,
        // and a read-then-save round-trip resurrected that default into the
        // column — so clearing a field silently failed to stick. The metadata
        // branch above already gets this right via `array_key_exists()`.
        if (
            null === $value
            && null !== $field
            && 'column' === $field->storageMode()
            && ! array_key_exists( $key, $this->attributes )
        ) {
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
     * This is an **allowlist**: a key is applied only when it names a custom
     * field actually registered for this content type. Everything else — an
     * unknown key, a key naming a real DB column, cast, mutator, accessor or
     * relation, a case variant of one of those, a `column->path` JSON key, or
     * one of Eloquent's own properties — is silently dropped and logged.
     *
     * The allowlist shape is deliberate, and the reason is worth recording so
     * nobody inverts it back into a denylist. The payload originates in
     * request input and the keys it may carry are whatever a plugin has
     * filter-registered; neither is trusted. A denylist has to enumerate
     * every dangerous key correctly, and it did not: a key naming a real
     * column overwrote that column, sidestepping the fillable allowlist the
     * caller applied to the attribute half of the same request; a case
     * variant of such a key slipped past a case-sensitive comparison while
     * MySQL and SQLite resolved it to the real column anyway; a `metadata->x`
     * key wrote straight through `fillJsonAttribute()` into the very store
     * the guard protects; and an unregistered key with no column reached
     * `save()` and raised a `QueryException`, rolling the whole write back.
     *
     * Values are assigned through `setAttribute()` — never `$this->{$key}`.
     * This method is compiled into the model class, so a dynamic property
     * write from here resolves Eloquent's *declared protected properties*
     * directly instead of falling through to `__set()`. A payload carrying
     * `table`, `exists` and `attributes` could therefore repoint the model at
     * another table and issue an arbitrary UPDATE against it on `save()`.
     *
     * The one legitimate reason a payload may carry a key naming a real
     * column is a DB-persisted, column-storage custom field ( see
     * {@see isPersistedColumnCustomField()} ), whose whole purpose is to
     * write its own column.
     *
     * The magic setter deliberately does *not* apply this guard —
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
        $registered = [];

        foreach ( $this->getCustomFieldsForType() as $field ) {
            $registered[ ( string ) $field->key ] = $field;
        }

        foreach ( $values as $key => $value ) {
            $key   = ( string ) $key;
            $field = $registered[ $key ] ?? null;

            if ( null === $field ) {
                $this->logDroppedCustomFieldKey( $key, 'not a registered custom field' );

                continue;
            }

            if ( $this->isReservedModelAttribute( $key ) && ! $this->isPersistedColumnCustomField( $key ) ) {
                $this->logDroppedCustomFieldKey( $key, 'shadows a model attribute' );

                continue;
            }

            if ( 'metadata' === $field->storageMode() ) {
                $this->assertMetadataColumnAvailable( $key );

                $metadata         = ( array ) ( $this->getAttribute( 'metadata' ) ?? [] );
                $metadata[ $key ] = $value;
                $this->setAttribute( 'metadata', $metadata );

                continue;
            }

            $this->setAttribute( $key, $value );
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
     * Forget the cached real-column listing.
     *
     * The listing is cached for the life of the process, so anything that
     * mutates a content type's schema — notably
     * {@see CustomFieldManager::addColumnToTable()} and
     * {@see CustomFieldManager::removeColumnFromTable()} — must flush it.
     * Without the flush, a removed column keeps being treated as real and
     * every payload key naming it is silently dropped for the rest of the
     * process, which under Octane or a long-lived queue worker spans many
     * requests.
     *
     * Passing no table flushes every cached listing.
     *
     * @since 2.7.1
     *
     * @param  string|null  $table  Table name to forget, or null for all.
     */
    public static function flushCustomFieldsRealColumnsCache( ?string $table = null ): void
    {
        CustomFieldColumnCache::flush( $table );
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
        // A `column->path` key is never a custom field, and Eloquent's
        // `setAttribute()` routes it to `fillJsonAttribute()` — writing the
        // real column named by the segment before the arrow.
        if ( str_contains( $key, '->' ) ) {
            return true;
        }
        if ( $this->matchesKeyCaseInsensitively( $key, array_keys( $this->attributes ) ) ) {
            return true;
        }
        if ( $this->matchesKeyCaseInsensitively( $key, array_keys( $this->getCasts() ) ) ) {
            return true;
        }
        if ( $this->isRealDatabaseColumn( $key ) ) {
            return true;
        }
        // A bare `method_exists()` looks over-broad — it reserves any key
        // colliding with any method name (`save`, `delete`, `touch`,
        // `refresh`) — but narrowing it changes nothing: Laravel implements
        // `isRelation()` below as `method_exists() || relationResolver()`, so
        // every such key is caught there regardless. Removing this check would
        // buy no behaviour and cost clarity. The collision is surfaced to the
        // plugin author at registration time instead — see
        // {@see CustomFieldManager::registerField()}.
        if ( '' === $key || method_exists( $this, $key ) ) {
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
        $table   = $this->getTable();
        $columns = CustomFieldColumnCache::get( $table );

        if ( null === $columns ) {
            try {
                $columns = Schema::getColumnListing( $table );
            } catch ( Throwable ) {
                // Schema introspection can fail during boot or with drivers
                // that don't support column listing. Return false without
                // caching so we don't pin a false negative for the life of
                // the process — note that for the duration of the failure the
                // shadow guard is off for every key, which is why the failure
                // must never be cached.
                return false;
            }

            CustomFieldColumnCache::put( $table, $columns );
            $columns = CustomFieldColumnCache::get( $table ) ?? [];
        }

        // Compared lower-cased: MySQL and SQLite resolve identifiers
        // case-insensitively, so `AUTHOR_ID` is the real `author_id` column to
        // the database even though the canonical listing spells it lower-case.
        return in_array( strtolower( $key ), $columns, true );
    }

    /**
     * Case-insensitive membership test for an attribute/cast key list.
     *
     * @since 2.7.1
     *
     * @param  string  $key  Key to look for.
     * @param  array<int,string>  $candidates  Known key names.
     *
     * @return bool True when a case-insensitive match exists.
     */
    protected function matchesKeyCaseInsensitively( string $key, array $candidates ): bool
    {
        $needle = strtolower( $key );

        foreach ( $candidates as $candidate ) {
            if ( strtolower( ( string ) $candidate ) === $needle ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record that an untrusted custom-field key was dropped.
     *
     * Dropping used to be entirely silent, which left a plugin author staring
     * at a field that "just doesn't save" with no breadcrumbs, and gave an
     * operator no signal that someone was probing `custom_fields[author_id]`.
     * Deduplicated per instance per key so a large probing payload cannot
     * flood the log.
     *
     * @since 2.7.1
     *
     * @param  string  $key  The dropped payload key.
     * @param  string  $reason  Why it was dropped.
     */
    protected function logDroppedCustomFieldKey( string $key, string $reason ): void
    {
        if ( isset( $this->droppedCustomFieldKeys[ $key ] ) ) {
            return;
        }

        $this->droppedCustomFieldKeys[ $key ] = true;

        Log::warning( 'Dropped a custom-field payload key.', [
            'key'    => $key,
            'reason' => $reason,
            'model'  => static::class,
            'table'  => $this->getTable(),
        ] );
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

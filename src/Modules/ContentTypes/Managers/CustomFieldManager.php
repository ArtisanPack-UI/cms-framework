<?php

declare( strict_types=1 );

/**
 * CustomField Manager
 *
 * Manages custom field registration and operations, including migration generation.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Support\CustomFieldColumnCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Manages custom field registration and operations.
 *
 * @since 1.0.0
 */
class CustomFieldManager
{
    /**
     * Framework-reserved custom-field keys.
     *
     * These name columns the framework itself owns on content-type tables, or
     * attributes a host model is overwhelmingly likely to carry. Adopting one
     * as a custom field would hand every content editor a write primitive on
     * it via the `isPersistedColumnCustomField()` exemption — see
     * {@see HasCustomFields::applyCustomFieldValues()}.
     *
     * @since 2.7.1
     *
     * @var array<int,string>
     */
    public const RESERVED_FIELD_KEYS = [
        'author_id',
        'created_at',
        'deleted_at',
        'id',
        'metadata',
        'parent_id',
        'password',
        'published_at',
        'remember_token',
        'slug',
        'status',
        'updated_at',
        'user_id',
        'uuid',
    ];

    /**
     * Memoized field lists keyed by content type slug.
     *
     * The trait's per-*instance* memo meant an unknown attribute access on
     * each model in a collection ran its own
     * `CustomField::whereJsonContains(…)->get()` — 50 posts, 50 queries — and
     * `bootHasCustomFields()` flushes that memo on every `saved`/`deleted`, so
     * a bulk import re-queried on every iteration. This manager is resolved
     * once per request, so hoisting the memo here collapses that to one query
     * per content type.
     *
     * @since 2.7.1
     *
     * @var array<string,Collection<int,CustomField>>
     */
    protected array $fieldsByContentType = [];

    /**
     * Register a custom field programmatically.
     *
     * @since 1.0.0
     *
     * @param  array  $args  Custom field configuration.
     */
    public function registerField( array $args ): void
    {
        $this->flushFieldCache();
        $this->warnOnReservedFieldKey( $args );

        /**
         * Filters the array of registered custom fields.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.registeredCustomFields
         *
         * @param  array  $fields  Associative array of registered custom fields keyed by field key.
         *
         * @return array Filtered custom fields array.
         */
        addFilter( 'ap.contentTypes.registeredCustomFields', function ( $fields ) use ( $args ) {
            $key = $args['key'] ?? '';
            if ( $key ) {
                $fields[ $key ] = $args;
            }

            return $fields;
        } );
    }

    /**
     * Get fields for a specific content type.
     *
     * @since 1.0.0
     *
     * @param  string  $contentType  Content type slug.
     */
    public function getFieldsForContentType( string $contentType ): Collection
    {
        if ( isset( $this->fieldsByContentType[ $contentType ] ) ) {
            return $this->fieldsByContentType[ $contentType ];
        }

        return $this->fieldsByContentType[ $contentType ] = $this->loadFieldsForContentType( $contentType );
    }

    /**
     * Forget the memoized field list for a content type, or for all of them.
     *
     * @since 2.7.1
     *
     * @param  string|null  $contentType  Content type slug, or null to flush everything.
     */
    public function flushFieldCache( ?string $contentType = null ): void
    {
        if ( null === $contentType ) {
            $this->fieldsByContentType = [];

            return;
        }

        unset( $this->fieldsByContentType[ $contentType ] );
    }

    /**
     * Create a new custom field and add columns to tables.
     *
     * @since 1.0.0
     *
     * @param  array  $data  Custom field data.
     *
     * @throws InvalidArgumentException When the key is reserved or already names a column.
     */
    public function createField( array $data ): CustomField
    {
        $this->assertFieldKeyIsAvailable( ( string ) ( $data['key'] ?? '' ), ( array ) ( $data['content_types'] ?? [] ) );

        $field = DB::transaction( function () use ( $data ) {
            $field = CustomField::create( $data );

            // Add columns to content type tables. Restricted to DB-persisted
            // content types so a plugin's filter-registered `table_name` can
            // never steer Schema::table at an arbitrary host table.
            foreach ( $field->content_types as $contentTypeSlug ) {
                $contentType = app( ContentTypeManager::class )->getPersistedContentType( $contentTypeSlug );
                if ( $contentType ) {
                    $this->addColumnToTable( $field, $contentType->table_name );
                }
            }

            return $field;
        } );

        $this->flushFieldCache();

        /**
         * Fires after a custom field has been created.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.customFieldCreated
         *
         * @param  CustomField  $field  The created custom field instance.
         */
        doAction( 'ap.contentTypes.customFieldCreated', $field );

        return $field;
    }

    /**
     * Update a custom field.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Custom field ID.
     * @param  array  $data  Custom field data.
     */
    public function updateField( int $id, array $data ): CustomField
    {
        $field = DB::transaction( function () use ( $id, $data ) {
            $field           = CustomField::findOrFail( $id );
            $oldContentTypes = $field->content_types;

            $field->update( $data );

            // Handle content type changes
            if ( isset( $data['content_types'] ) ) {
                $newContentTypes = $data['content_types'];
                $addedTypes      = array_diff( $newContentTypes, $oldContentTypes );
                $removedTypes    = array_diff( $oldContentTypes, $newContentTypes );

                // Add columns to new content types. Restricted to DB-persisted
                // content types ( see createField() ) to prevent plugin-supplied
                // `table_name` values from steering Schema::table.
                foreach ( $addedTypes as $contentTypeSlug ) {
                    $contentType = app( ContentTypeManager::class )->getPersistedContentType( $contentTypeSlug );
                    if ( $contentType ) {
                        $this->addColumnToTable( $field, $contentType->table_name );
                    }
                }

                // Remove columns from removed content types.
                foreach ( $removedTypes as $contentTypeSlug ) {
                    $contentType = app( ContentTypeManager::class )->getPersistedContentType( $contentTypeSlug );
                    if ( $contentType ) {
                        $this->removeColumnFromTable( $field, $contentType->table_name );
                    }
                }
            }

            return $field;
        } );

        $this->flushFieldCache();

        /**
         * Fires after a custom field has been updated.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.customFieldUpdated
         *
         * @param  CustomField  $field  The updated custom field instance.
         */
        doAction( 'ap.contentTypes.customFieldUpdated', $field );

        return $field;
    }

    /**
     * Delete a custom field and remove columns from tables.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Custom field ID.
     */
    public function deleteField( int $id ): bool
    {
        return DB::transaction( function () use ( $id ) {
            $field = CustomField::findOrFail( $id );

            /**
             * Fires before a custom field is deleted.
             *
             * @since 1.0.0
             *
             * @hook ap.contentTypes.customFieldDeleting
             *
             * @param  CustomField  $field  The custom field being deleted.
             */
            doAction( 'ap.contentTypes.customFieldDeleting', $field );

            $deleted = $field->delete();

            if ( $deleted ) {
                $this->flushFieldCache();

                // Remove columns from content type tables. DB-only lookup
                // matches the createField() / updateField() paths so we
                // never dropColumn against a plugin-supplied `table_name`.
                foreach ( $field->content_types as $contentTypeSlug ) {
                    $contentType = app( ContentTypeManager::class )->getPersistedContentType( $contentTypeSlug );
                    if ( $contentType ) {
                        $this->removeColumnFromTable( $field, $contentType->table_name );
                    }
                }

                /**
                 * Fires after a custom field has been deleted.
                 *
                 * @since 1.0.0
                 *
                 * @hook ap.contentTypes.customFieldDeleted
                 *
                 * @param  string  $key  The key of the deleted custom field.
                 */
                doAction( 'ap.contentTypes.customFieldDeleted', $field->key );
            }

            return $deleted;
        } );
    }

    /**
     * Add a column to a table.
     *
     * @since 1.0.0
     *
     * @param  CustomField  $field  The custom field.
     * @param  string  $tableName  The table name.
     */
    public function addColumnToTable( CustomField $field, string $tableName ): void
    {
        if ( ! Schema::hasTable( $tableName ) ) {
            return;
        }

        if ( Schema::hasColumn( $tableName, $field->key ) ) {
            return;
        }

        Schema::table( $tableName, function ( $table ) use ( $field ): void {
            $column = $table->{$field->column_type->value}( $field->key );

            if ( ! $field->required ) {
                $column->nullable();
            }

            if ( null !== $field->default_value ) {
                $column->default( $field->default_value );
            }
        } );

        CustomFieldColumnCache::flush( $tableName );

        /**
         * Fires after a custom field column has been added.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.customFieldColumnAdded
         *
         * @param  CustomField  $field  The custom field.
         * @param  string  $tableName  The table name.
         */
        doAction( 'ap.contentTypes.customFieldColumnAdded', $field, $tableName );
    }

    /**
     * Remove a column from a table.
     *
     * @since 1.0.0
     *
     * @param  CustomField  $field  The custom field.
     * @param  string  $tableName  The table name.
     */
    public function removeColumnFromTable( CustomField $field, string $tableName ): void
    {
        if ( ! Schema::hasTable( $tableName ) ) {
            return;
        }

        if ( ! Schema::hasColumn( $tableName, $field->key ) ) {
            return;
        }

        Schema::table( $tableName, function ( $table ) use ( $field ): void {
            $table->dropColumn( $field->key );
        } );

        CustomFieldColumnCache::flush( $tableName );

        /**
         * Fires after a custom field column has been removed.
         *
         * @since 1.0.0
         *
         * @hook ap.contentTypes.customFieldColumnRemoved
         *
         * @param  CustomField  $field  The custom field.
         * @param  string  $tableName  The table name.
         */
        doAction( 'ap.contentTypes.customFieldColumnRemoved', $field, $tableName );
    }

    /**
     * Generate a migration file for a custom field.
     *
     * @since 1.0.0
     *
     * @param  CustomField  $field  The custom field.
     * @param  string  $action  The action (add or remove).
     *
     * @return string The migration file path.
     */
    public function generateMigration( CustomField $field, string $action ): string
    {
        $timestamp = date( 'Y_m_d_His' );
        $className = Str::studly( "{$action}_" . $field->key . '_to_content_types' );
        $fileName  = "{$timestamp}_{$action}_{$field->key}_to_content_types.php";

        $migrationPath = database_path( 'migrations/' . $fileName );

        $stub = $this->getMigrationStub( $field, $action, $className );

        file_put_contents( $migrationPath, $stub );

        return $migrationPath;
    }

    /**
     * Find why a proposed custom-field key is unavailable, or null when it is
     * fine.
     *
     * Single source of truth for both enforcement points — `createField()`
     * below and `CustomFieldRequest::withValidator()` — so the API and the
     * admin UI cannot drift into disagreeing about which keys are allowed.
     *
     * `addColumnToTable()` silently returns when the column already exists, so
     * without this check an admin creating a field keyed `author_id` does not
     * get an error: the existing column is *adopted* as a custom field, and
     * from then on any content editor's `custom_fields[author_id]` writes it
     * through the legitimate exemption path. That is a permanent, quiet
     * escalation from "manage custom fields" to "reassign authorship of any
     * post", and a plain footgun besides.
     *
     * @since 2.7.1
     *
     * @param  string  $key  The proposed field key.
     * @param  array<int,string>  $contentTypes  Content type slugs the field targets.
     *
     * @return array{reason: 'column'|'reserved', table: string|null}|null Conflict, or null when the key is available.
     */
    public function findKeyConflict( string $key, array $contentTypes ): ?array
    {
        if ( '' === $key ) {
            return null;
        }

        $normalized = strtolower( $key );

        if ( in_array( $normalized, self::RESERVED_FIELD_KEYS, true ) ) {
            return ['reason' => 'reserved', 'table' => null];
        }

        foreach ( $contentTypes as $contentTypeSlug ) {
            if ( ! is_string( $contentTypeSlug ) ) {
                continue;
            }

            $contentType = app( ContentTypeManager::class )->getPersistedContentType( $contentTypeSlug );

            if ( ! $contentType || ! Schema::hasTable( $contentType->table_name ) ) {
                continue;
            }

            foreach ( Schema::getColumnListing( $contentType->table_name ) as $column ) {
                if ( strtolower( (string) $column ) === $normalized ) {
                    return ['reason' => 'column', 'table' => $contentType->table_name];
                }
            }
        }

        return null;
    }

    /**
     * Load the field list for a content type from the database and filters.
     *
     * @since 2.7.1
     *
     * @param  string  $contentType  Content type slug.
     */
    protected function loadFieldsForContentType( string $contentType ): Collection
    {
        $dbFields = CustomField::whereJsonContains( 'content_types', $contentType )
            ->orderBy( 'order' )
            ->get();

        $filterFields = $this->filterFieldsForContentType( $contentType, $dbFields->pluck( 'key' )->all() );

        if ( $filterFields->isEmpty() ) {
            return $dbFields;
        }

        return $dbFields->concat( $filterFields )->values();
    }

    /**
     * Warn a plugin author whose field key can never resolve.
     *
     * A key colliding with a model method — `author`, `comments`, `tags`,
     * `order`, `save`, `touch` — is treated as reserved by
     * `HasCustomFields::isReservedModelAttribute()`, so the field is silently
     * inert: the getter never reads it and `applyCustomFieldValues()` drops
     * the value. That is the correct behaviour ( a plugin must not be able to
     * shadow a relation ), but it used to happen with no signal whatsoever, so
     * the author saw only "my field doesn't save".
     *
     * A warning rather than an exception: registration usually runs from a
     * service provider during boot, and a third-party plugin picking an
     * unlucky key must not take the application down.
     *
     * @since 2.7.1
     *
     * @param  array  $args  Registration arguments.
     */
    protected function warnOnReservedFieldKey( array $args ): void
    {
        $key = (string) ( $args['key'] ?? '' );

        if ( '' === $key ) {
            return;
        }

        if ( ! in_array( strtolower( $key ), self::RESERVED_FIELD_KEYS, true ) ) {
            return;
        }

        Log::warning(
            'cms-framework: a custom field was registered under a reserved key and will be silently ignored.',
            [
                'key'   => $key,
                'hint'  => 'Reserved keys name framework-owned columns. Choose a different key.',
                'types' => $args['content_types'] ?? [],
            ],
        );
    }

    /**
     * Reject a custom-field key that is framework-reserved or already names a
     * column on one of the target content types' tables.
     *
     * @since 2.7.1
     *
     * @param  string  $key  The proposed field key.
     * @param  array<int,string>  $contentTypes  Content type slugs the field targets.
     *
     * @throws InvalidArgumentException When the key is unavailable.
     */
    protected function assertFieldKeyIsAvailable( string $key, array $contentTypes ): void
    {
        $conflict = $this->findKeyConflict( $key, $contentTypes );

        if ( null === $conflict ) {
            return;
        }

        if ( 'reserved' === $conflict['reason'] ) {
            throw new InvalidArgumentException( sprintf(
                'The custom field key "%s" is reserved by the CMS framework and cannot be used.',
                $key,
            ) );
        }

        throw new InvalidArgumentException( sprintf(
            'The custom field key "%s" already names a column on the "%s" table. '
            . 'Choose a different key — an existing column cannot be adopted as a custom field.',
            $key,
            $conflict['table'],
        ) );
    }

    /**
     * Hydrate filter-registered custom fields into unpersisted `CustomField`
     * models for the given content type. DB rows always win on key collisions.
     *
     * Filter-registered fields carry the `storage = 'metadata'` flag so
     * `HasCustomFields` knows to read/write through the model's `metadata`
     * JSON column rather than a physical column that doesn't exist.
     *
     * @since 2.4.0
     *
     * @param  array<int,string>  $excludeKeys
     */
    protected function filterFieldsForContentType( string $contentType, array $excludeKeys ): Collection
    {
        $filtered = applyFilters( 'ap.contentTypes.registeredCustomFields', [] );
        $out      = collect();

        if ( ! is_array( $filtered ) ) {
            return $out;
        }

        foreach ( $filtered as $key => $args ) {
            if ( ! is_array( $args ) ) {
                continue;
            }
            $resolvedKey = ( string ) ( $args['key'] ?? $key );

            if ( in_array( $resolvedKey, $excludeKeys, true ) ) {
                continue;
            }

            $contentTypes = ( array ) ( $args['content_types'] ?? [] );
            if ( ! in_array( $contentType, $contentTypes, true ) ) {
                continue;
            }

            $args['key']     = $resolvedKey;
            $args['storage'] = $args['storage'] ?? 'metadata';

            // Strip persistence-critical keys so a plugin cannot seed `id`
            // or timestamps onto the hydrated CustomField model.
            $args = array_diff_key( $args, array_flip( [
                'id',
                'created_at',
                'updated_at',
                'deleted_at',
            ] ) );

            $field         = new CustomField;
            $field->forceFill( $args );
            $field->exists = false;

            $out->push( $field );
        }

        return $out->sortBy( fn ( CustomField $field ) => $field->order ?? 0 )->values();
    }

    /**
     * Get the migration stub content.
     *
     * @since 1.0.0
     *
     * @param  CustomField  $field  The custom field.
     * @param  string  $action  The action (add or remove).
     * @param  string  $className  The migration class name.
     */
    protected function getMigrationStub( CustomField $field, string $action, string $className ): string
    {
        // DB-only lookup: the generated migration writes literal `table_name`
        // values into a Schema::table() call, so we must never emit a
        // plugin-supplied table name from a filter registration.
        $tables = [];
        foreach ( $field->content_types as $contentTypeSlug ) {
            $contentType = app( ContentTypeManager::class )->getPersistedContentType( $contentTypeSlug );
            if ( $contentType ) {
                $tables[] = $contentType->table_name;
            }
        }

        $upCode   = '';
        $downCode = '';

        foreach ( $tables as $tableName ) {
            if ( 'add' === $action ) {
                $upCode .= "        Schema::table('{$tableName}', function (Blueprint \$table) {\n";
                $upCode .= "            {$field->getMigrationColumnDefinition()}\n";
                $upCode .= "        });\n\n";

                $downCode .= "        Schema::table('{$tableName}', function (Blueprint \$table) {\n";
                $downCode .= "            \$table->dropColumn('{$field->key}');\n";
                $downCode .= "        });\n\n";
            } else {
                $upCode .= "        Schema::table('{$tableName}', function (Blueprint \$table) {\n";
                $upCode .= "            \$table->dropColumn('{$field->key}');\n";
                $upCode .= "        });\n\n";

                $downCode .= "        Schema::table('{$tableName}', function (Blueprint \$table) {\n";
                $downCode .= "            {$field->getMigrationColumnDefinition()}\n";
                $downCode .= "        });\n\n";
            }
        }

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
{$upCode}    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downCode}    }
};
PHP;
    }
}

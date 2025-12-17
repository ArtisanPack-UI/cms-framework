<?php

/**
 * CustomField Manager
 *
 * Manages custom field registration and operations, including migration generation.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Manages custom field registration and operations.
 *
 * @since 2.0.0
 */
class CustomFieldManager
{
    /**
     * Register a custom field programmatically.
     *
     * @since 2.0.0
     *
     * @param  array  $args  Custom field configuration.
     */
    public function registerField(array $args): void
    {
        /**
         * Filters the array of registered custom fields.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.registeredCustomFields
         *
         * @param  array  $fields  Associative array of registered custom fields keyed by field key.
         * @return array Filtered custom fields array.
         */
        addFilter('ap.contentTypes.registeredCustomFields', function ($fields) use ($args) {
            $key = $args['key'] ?? '';
            if ($key) {
                $fields[$key] = $args;
            }

            return $fields;
        });
    }

    /**
     * Get fields for a specific content type.
     *
     * @since 2.0.0
     *
     * @param  string  $contentType  Content type slug.
     */
    public function getFieldsForContentType(string $contentType): Collection
    {
        return CustomField::whereJsonContains('content_types', $contentType)
            ->orderBy('order')
            ->get();
    }

    /**
     * Create a new custom field and add columns to tables.
     *
     * @since 2.0.0
     *
     * @param  array  $data  Custom field data.
     */
    public function createField(array $data): CustomField
    {
        $field = DB::transaction(function () use ($data) {
            $field = CustomField::create($data);

            // Add columns to content type tables
            foreach ($field->content_types as $contentTypeSlug) {
                $contentType = app(ContentTypeManager::class)->getContentType($contentTypeSlug);
                if ($contentType) {
                    $this->addColumnToTable($field, $contentType->table_name);
                }
            }

            return $field;
        });

        /**
         * Fires after a custom field has been created.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.customFieldCreated
         *
         * @param  CustomField  $field  The created custom field instance.
         */
        doAction('ap.contentTypes.customFieldCreated', $field);

        return $field;
    }

    /**
     * Update a custom field.
     *
     * @since 2.0.0
     *
     * @param  int  $id  Custom field ID.
     * @param  array  $data  Custom field data.
     */
    public function updateField(int $id, array $data): CustomField
    {
        $field = DB::transaction(function () use ($id, $data) {
            $field = CustomField::findOrFail($id);
            $oldContentTypes = $field->content_types;

            $field->update($data);

            // Handle content type changes
            if (isset($data['content_types'])) {
                $newContentTypes = $data['content_types'];
                $addedTypes = array_diff($newContentTypes, $oldContentTypes);
                $removedTypes = array_diff($oldContentTypes, $newContentTypes);

                // Add columns to new content types
                foreach ($addedTypes as $contentTypeSlug) {
                    $contentType = app(ContentTypeManager::class)->getContentType($contentTypeSlug);
                    if ($contentType) {
                        $this->addColumnToTable($field, $contentType->table_name);
                    }
                }

                // Remove columns from removed content types
                foreach ($removedTypes as $contentTypeSlug) {
                    $contentType = app(ContentTypeManager::class)->getContentType($contentTypeSlug);
                    if ($contentType) {
                        $this->removeColumnFromTable($field, $contentType->table_name);
                    }
                }
            }

            return $field;
        });

        /**
         * Fires after a custom field has been updated.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.customFieldUpdated
         *
         * @param  CustomField  $field  The updated custom field instance.
         */
        doAction('ap.contentTypes.customFieldUpdated', $field);

        return $field;
    }

    /**
     * Delete a custom field and remove columns from tables.
     *
     * @since 2.0.0
     *
     * @param  int  $id  Custom field ID.
     */
    public function deleteField(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $field = CustomField::findOrFail($id);

            /**
             * Fires before a custom field is deleted.
             *
             * @since 2.0.0
             *
             * @hook ap.contentTypes.customFieldDeleting
             *
             * @param  CustomField  $field  The custom field being deleted.
             */
            doAction('ap.contentTypes.customFieldDeleting', $field);

            $deleted = $field->delete();

            if ($deleted) {
                // Remove columns from content type tables
                foreach ($field->content_types as $contentTypeSlug) {
                    $contentType = app(ContentTypeManager::class)->getContentType($contentTypeSlug);
                    if ($contentType) {
                        $this->removeColumnFromTable($field, $contentType->table_name);
                    }
                }

                /**
                 * Fires after a custom field has been deleted.
                 *
                 * @since 2.0.0
                 *
                 * @hook ap.contentTypes.customFieldDeleted
                 *
                 * @param  string  $key  The key of the deleted custom field.
                 */
                doAction('ap.contentTypes.customFieldDeleted', $field->key);
            }

            return $deleted;
        });
    }

    /**
     * Add a column to a table.
     *
     * @since 2.0.0
     *
     * @param  CustomField  $field  The custom field.
     * @param  string  $tableName  The table name.
     */
    public function addColumnToTable(CustomField $field, string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (Schema::hasColumn($tableName, $field->key)) {
            return;
        }

        Schema::table($tableName, function ($table) use ($field) {
            $column = $table->{$field->column_type}($field->key);

            if (! $field->required) {
                $column->nullable();
            }

            if ($field->default_value !== null) {
                $column->default($field->default_value);
            }
        });

        /**
         * Fires after a custom field column has been added.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.customFieldColumnAdded
         *
         * @param  CustomField  $field  The custom field.
         * @param  string  $tableName  The table name.
         */
        doAction('ap.contentTypes.customFieldColumnAdded', $field, $tableName);
    }

    /**
     * Remove a column from a table.
     *
     * @since 2.0.0
     *
     * @param  CustomField  $field  The custom field.
     * @param  string  $tableName  The table name.
     */
    public function removeColumnFromTable(CustomField $field, string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, $field->key)) {
            return;
        }

        Schema::table($tableName, function ($table) use ($field) {
            $table->dropColumn($field->key);
        });

        /**
         * Fires after a custom field column has been removed.
         *
         * @since 2.0.0
         *
         * @hook ap.contentTypes.customFieldColumnRemoved
         *
         * @param  CustomField  $field  The custom field.
         * @param  string  $tableName  The table name.
         */
        doAction('ap.contentTypes.customFieldColumnRemoved', $field, $tableName);
    }

    /**
     * Generate a migration file for a custom field.
     *
     * @since 2.0.0
     *
     * @param  CustomField  $field  The custom field.
     * @param  string  $action  The action (add or remove).
     * @return string The migration file path.
     */
    public function generateMigration(CustomField $field, string $action): string
    {
        $timestamp = date('Y_m_d_His');
        $className = Str::studly("{$action}_".$field->key.'_to_content_types');
        $fileName = "{$timestamp}_{$action}_{$field->key}_to_content_types.php";

        $migrationPath = database_path('migrations/'.$fileName);

        $stub = $this->getMigrationStub($field, $action, $className);

        file_put_contents($migrationPath, $stub);

        return $migrationPath;
    }

    /**
     * Get the migration stub content.
     *
     * @since 2.0.0
     *
     * @param  CustomField  $field  The custom field.
     * @param  string  $action  The action (add or remove).
     * @param  string  $className  The migration class name.
     */
    protected function getMigrationStub(CustomField $field, string $action, string $className): string
    {
        $tables = [];
        foreach ($field->content_types as $contentTypeSlug) {
            $contentType = app(ContentTypeManager::class)->getContentType($contentTypeSlug);
            if ($contentType) {
                $tables[] = $contentType->table_name;
            }
        }

        $upCode = '';
        $downCode = '';

        foreach ($tables as $tableName) {
            if ($action === 'add') {
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

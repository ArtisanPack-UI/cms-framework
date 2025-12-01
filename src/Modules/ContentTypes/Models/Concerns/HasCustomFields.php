<?php

/**
 * HasCustomFields Trait
 *
 * Provides custom fields functionality for content types.
 *
 * @since 2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use Illuminate\Support\Collection;

/**
 * Trait for adding custom fields support to models.
 *
 * @since 2.0.0
 */
trait HasCustomFields
{
    /**
     * Get the custom fields for the content type.
     *
     * @since 2.0.0
     */
    public function getCustomFieldsForType(): Collection
    {
        $contentType = $this->getTable();

        return app(CustomFieldManager::class)->getFieldsForContentType($contentType);
    }

    /**
     * Magic getter for custom field values.
     *
     * @since 2.0.0
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        // First try to get the attribute from the parent
        try {
            return parent::__get($key);
        } catch (\Exception $e) {
            // If it doesn't exist, check if it's a custom field
            $customFields = $this->getCustomFieldsForType();

            foreach ($customFields as $field) {
                if ($field->key === $key) {
                    return $this->attributes[$key] ?? $field->default_value;
                }
            }

            // If not a custom field, throw the original exception
            throw $e;
        }
    }

    /**
     * Magic setter for custom field values.
     *
     * @since 2.0.0
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        // Check if it's a custom field
        $customFields = $this->getCustomFieldsForType();
        $isCustomField = false;

        foreach ($customFields as $field) {
            if ($field->key === $key) {
                $isCustomField = true;
                break;
            }
        }

        if ($isCustomField) {
            $this->attributes[$key] = $value;
        } else {
            parent::__set($key, $value);
        }
    }
}

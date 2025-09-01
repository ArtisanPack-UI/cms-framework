<?php

namespace ArtisanPackUI\CMSFramework\Observers;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\CMSFramework\Services\SearchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * SearchIndexObserver.
 *
 * Handles automatic search indexing when models are created, updated, or deleted.
 * This observer ensures that the search index stays synchronized with content changes.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Observers
 * @since   1.2.0
 */
class SearchIndexObserver
{
    /**
     * SearchService instance.
     *
     * @var SearchService
     */
    protected SearchService $searchService;

    /**
     * Create a new SearchIndexObserver instance.
     *
     * @since 1.2.0
     *
     * @param SearchService $searchService
     */
    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Handle the model "created" event.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return void
     */
    public function created(Model $model): void
    {
        if (!$this->shouldIndex($model)) {
            return;
        }

        try {
            $this->searchService->indexModel($model);
            
            Log::debug('Search index: Model created and indexed', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Search index: Failed to index created model', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the model "updated" event.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return void
     */
    public function updated(Model $model): void
    {
        if (!$this->shouldIndex($model)) {
            return;
        }

        try {
            // Check if searchable fields were actually changed
            if ($this->hasSearchableChanges($model)) {
                $this->searchService->indexModel($model);
                
                Log::debug('Search index: Model updated and reindexed', [
                    'model_type' => get_class($model),
                    'model_id' => $model->id,
                    'changed_attributes' => array_keys($model->getDirty()),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Search index: Failed to reindex updated model', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the model "deleted" event.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        if (!$this->shouldIndex($model)) {
            return;
        }

        try {
            $this->searchService->removeFromIndex($model);
            
            Log::debug('Search index: Model deleted and removed from index', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Search index: Failed to remove deleted model from index', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the model "restored" event.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return void
     */
    public function restored(Model $model): void
    {
        if (!$this->shouldIndex($model)) {
            return;
        }

        try {
            $this->searchService->indexModel($model);
            
            Log::debug('Search index: Model restored and reindexed', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Search index: Failed to reindex restored model', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the model "force deleted" event.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return void
     */
    public function forceDeleted(Model $model): void
    {
        if (!$this->shouldIndex($model)) {
            return;
        }

        try {
            $this->searchService->removeFromIndex($model);
            
            Log::debug('Search index: Model force deleted and removed from index', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Search index: Failed to remove force deleted model from index', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if the model should be indexed.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return bool
     */
    protected function shouldIndex(Model $model): bool
    {
        // Check if search indexing is enabled
        if (!config('cms.search.enabled', true)) {
            return false;
        }

        // Check if this model type should be indexed
        $indexableModels = config('cms.search.indexable_models', [
            Content::class,
            Term::class,
        ]);

        return in_array(get_class($model), $indexableModels);
    }

    /**
     * Check if the model has changes to searchable fields.
     *
     * @since 1.2.0
     *
     * @param Model $model
     * @return bool
     */
    protected function hasSearchableChanges(Model $model): bool
    {
        if ($model instanceof Content) {
            return $this->hasContentSearchableChanges($model);
        }

        if ($model instanceof Term) {
            return $this->hasTermSearchableChanges($model);
        }

        // For other models, assume any change affects searchability
        return !empty($model->getDirty());
    }

    /**
     * Check if Content model has searchable changes.
     *
     * @since 1.2.0
     *
     * @param Content $content
     * @return bool
     */
    protected function hasContentSearchableChanges(Content $content): bool
    {
        $searchableFields = [
            'title',
            'slug',
            'content',
            'type',
            'status',
            'author_id',
            'published_at',
            'meta',
        ];

        $changedFields = array_keys($content->getDirty());

        return !empty(array_intersect($searchableFields, $changedFields));
    }

    /**
     * Check if Term model has searchable changes.
     *
     * @since 1.2.0
     *
     * @param Term $term
     * @return bool
     */
    protected function hasTermSearchableChanges(Term $term): bool
    {
        $searchableFields = [
            'name',
            'slug',
            'taxonomy_id',
            'parent_id',
        ];

        $changedFields = array_keys($term->getDirty());

        return !empty(array_intersect($searchableFields, $changedFields));
    }
}
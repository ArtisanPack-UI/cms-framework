<?php

/**
 * Page Manager
 *
 * Manages page operations including hierarchy management and page retrieval.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Managers;

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Manages page operations.
 *
 * @since 2.0.0
 */
class PageManager
{
    /**
     * Get a hierarchical page tree structure.
     *
     * @since 2.0.0
     *
     * @param  array  $filters  Optional filters (status, author, template).
     */
    public function getPageTree(array $filters = []): Collection
    {
        $query = Page::query()->topLevel()->with(['children' => function ($query) {
            $query->orderBy('order');
        }]);

        $this->applyFilters($query, $filters);

        $topLevel = $query->orderBy('order')->get();

        // Recursively load all children
        $topLevel->each(function ($page) {
            $this->loadChildrenRecursively($page);
        });

        return $topLevel;
    }

    /**
     * Load children recursively for a page.
     *
     * @since 2.0.0
     *
     * @param  Page  $page  The page to load children for.
     */
    protected function loadChildrenRecursively(Page $page): void
    {
        if ($page->children->isNotEmpty()) {
            $page->children->each(function ($child) {
                $child->load(['children' => function ($query) {
                    $query->orderBy('order');
                }]);
                $this->loadChildrenRecursively($child);
            });
        }
    }

    /**
     * Get top-level pages (pages without a parent).
     *
     * @since 2.0.0
     *
     * @param  array  $filters  Optional filters (status, author, template).
     */
    public function getTopLevelPages(array $filters = []): Collection
    {
        $query = Page::query()->topLevel();

        $this->applyFilters($query, $filters);

        return $query->orderBy('order')->get();
    }

    /**
     * Get pages by template.
     *
     * @since 2.0.0
     *
     * @param  string  $template  Template name to filter by.
     * @param  array  $filters  Optional filters (status, author).
     */
    public function getPagesByTemplate(string $template, array $filters = []): Collection
    {
        $query = Page::query()->byTemplate($template);

        $this->applyFilters($query, $filters);

        return $query->orderBy('order')->get();
    }

    /**
     * Reorder pages.
     *
     * @since 2.0.0
     *
     * @param  array  $order  Array of page IDs with their new order values.
     *                        Format: ['page_id' => order_value, ...]
     */
    public function reorderPages(array $order): void
    {
        foreach ($order as $pageId => $orderValue) {
            Page::where('id', $pageId)->update(['order' => $orderValue]);
        }
    }

    /**
     * Move a page to a new parent in the hierarchy.
     *
     * @since 2.0.0
     *
     * @param  int  $pageId  The ID of the page to move.
     * @param  int|null  $newParentId  The ID of the new parent page (null for top-level).
     */
    public function movePage(int $pageId, ?int $newParentId = null): void
    {
        $page = Page::findOrFail($pageId);

        // Prevent moving a page to itself or to its own descendant
        if ($newParentId !== null) {
            if ($newParentId === $pageId) {
                throw new \InvalidArgumentException('Cannot move a page to itself.');
            }

            $descendantIds = $page->descendants()->pluck('id')->toArray();
            if (in_array($newParentId, $descendantIds)) {
                throw new \InvalidArgumentException('Cannot move a page to its own descendant.');
            }
        }

        $page->update(['parent_id' => $newParentId]);
    }

    /**
     * Get all pages query with filters.
     *
     * @since 2.0.0
     *
     * @param  array  $filters  Array of filters to apply (status, author, template, search).
     */
    public function getPageQuery(array $filters = []): Builder
    {
        $query = Page::query()->with(['author', 'categories', 'tags']);

        $this->applyFilters($query, $filters);

        // Order by order column and title
        $query->orderBy('order')->orderBy('title');

        return $query;
    }

    /**
     * Apply filters to a query.
     *
     * @since 2.0.0
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array  $filters  Array of filters to apply.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Apply status filter
        if (isset($filters['status'])) {
            if ($filters['status'] === 'published') {
                $query->published();
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Apply author filter
        if (isset($filters['author'])) {
            $query->byAuthor($filters['author']);
        }

        // Apply template filter
        if (isset($filters['template'])) {
            $query->byTemplate($filters['template']);
        }

        // Apply search filter
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Get pages by author.
     *
     * @since 2.0.0
     *
     * @param  int  $authorId  Author ID to filter by.
     */
    public function getPagesByAuthor(int $authorId): Collection
    {
        return $this->getPageQuery(['author' => $authorId])->get();
    }

    /**
     * Get pages by category.
     *
     * @since 2.0.0
     *
     * @param  int|string  $category  Category ID or slug.
     */
    public function getPagesByCategory($category): Collection
    {
        // If category is a string (slug), find the category ID
        if (is_string($category)) {
            $categoryModel = \ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageCategory::where('slug', $category)->first();
            if ($categoryModel) {
                $category = $categoryModel->id;
            } else {
                return collect();
            }
        }

        $query = $this->getPageQuery();
        $query->whereHas('categories', function ($q) use ($category) {
            $q->where('page_categories.id', $category);
        });

        return $query->get();
    }

    /**
     * Get pages by tag.
     *
     * @since 2.0.0
     *
     * @param  int|string  $tag  Tag ID or slug.
     */
    public function getPagesByTag($tag): Collection
    {
        // If tag is a string (slug), find the tag ID
        if (is_string($tag)) {
            $tagModel = \ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageTag::where('slug', $tag)->first();
            if ($tagModel) {
                $tag = $tagModel->id;
            } else {
                return collect();
            }
        }

        $query = $this->getPageQuery();
        $query->whereHas('tags', function ($q) use ($tag) {
            $q->where('page_tags.id', $tag);
        });

        return $query->get();
    }

    /**
     * Get recent pages.
     *
     * @since 2.0.0
     *
     * @param  int  $limit  Number of pages to retrieve.
     */
    public function getRecentPages(int $limit = 10): Collection
    {
        return $this->getPageQuery(['status' => 'published'])
            ->limit($limit)
            ->get();
    }
}

<?php

/**
 * Blog Manager
 *
 * Manages blog operations including archive queries and post retrieval.
 *
 * @since 2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Blog\Managers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Managers;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Manages blog operations.
 *
 * @since 2.0.0
 */
class BlogManager
{
    /**
     * Build an archive query with filters.
     *
     * @since 2.0.0
     *
     * @param  array  $filters  Array of filters to apply (category, tag, author, year, month, day, status).
     */
    public function getArchiveQuery(array $filters = []): Builder
    {
        $query = Post::query()->with(['author', 'categories', 'tags']);

        // Apply status filter (default to published)
        if (isset($filters['status'])) {
            if ($filters['status'] === 'published') {
                $query->published();
            } else {
                $query->where('status', $filters['status']);
            }
        } else {
            $query->published();
        }

        // Apply category filter
        if (isset($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        // Apply tag filter
        if (isset($filters['tag'])) {
            $query->byTag($filters['tag']);
        }

        // Apply author filter
        if (isset($filters['author'])) {
            $query->byAuthor($filters['author']);
        }

        // Apply date filters
        if (isset($filters['year'])) {
            if (isset($filters['month'])) {
                $query->byMonth($filters['year'], $filters['month']);
            } else {
                $query->byYear($filters['year']);
            }
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

        // Order by published date descending
        $query->orderBy('published_at', 'desc');

        return $query;
    }

    /**
     * Get posts by date.
     *
     * @since 2.0.0
     *
     * @param  int  $year  Year to filter by.
     * @param  int|null  $month  Month to filter by (optional).
     * @param  int|null  $day  Day to filter by (optional).
     */
    public function getPostsByDate(int $year, ?int $month = null, ?int $day = null): Collection
    {
        $filters = ['year' => $year];

        if ($month !== null) {
            $filters['month'] = $month;
        }

        return $this->getArchiveQuery($filters)->get();
    }

    /**
     * Get posts by author.
     *
     * @since 2.0.0
     *
     * @param  int  $authorId  Author ID to filter by.
     */
    public function getPostsByAuthor(int $authorId): Collection
    {
        return $this->getArchiveQuery(['author' => $authorId])->get();
    }

    /**
     * Get posts by category.
     *
     * @since 2.0.0
     *
     * @param  int|string  $category  Category ID or slug.
     */
    public function getPostsByCategory($category): Collection
    {
        // If category is a string (slug), find the category ID
        if (is_string($category)) {
            $categoryModel = \ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory::where('slug', $category)->first();
            if ($categoryModel) {
                $category = $categoryModel->id;
            } else {
                return collect();
            }
        }

        return $this->getArchiveQuery(['category' => $category])->get();
    }

    /**
     * Get posts by tag.
     *
     * @since 2.0.0
     *
     * @param  int|string  $tag  Tag ID or slug.
     */
    public function getPostsByTag($tag): Collection
    {
        // If tag is a string (slug), find the tag ID
        if (is_string($tag)) {
            $tagModel = \ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag::where('slug', $tag)->first();
            if ($tagModel) {
                $tag = $tagModel->id;
            } else {
                return collect();
            }
        }

        return $this->getArchiveQuery(['tag' => $tag])->get();
    }

    /**
     * Get recent posts.
     *
     * @since 2.0.0
     *
     * @param  int  $limit  Number of posts to retrieve.
     */
    public function getRecentPosts(int $limit = 10): Collection
    {
        return $this->getArchiveQuery()
            ->limit($limit)
            ->get();
    }

    /**
     * Get popular posts based on view count in metadata.
     *
     * @since 2.0.0
     *
     * @param  int  $limit  Number of posts to retrieve.
     */
    public function getPopularPosts(int $limit = 10): Collection
    {
        return $this->getArchiveQuery()
            ->orderByRaw('CAST(JSON_EXTRACT(metadata, "$.view_count") AS UNSIGNED) DESC')
            ->limit($limit)
            ->get();
    }
}

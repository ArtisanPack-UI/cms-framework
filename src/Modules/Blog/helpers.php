<?php

/**
 * Blog Module Helper Functions for the CMS Framework.
 *
 * This file contains global helper functions for working with blog posts,
 * categories, and tags throughout the application.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Blog
 */

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use Illuminate\Database\Eloquent\Collection;

if (! function_exists('getPost')) {
    /**
     * Get a post by ID.
     *
     * Retrieves a single post instance by its ID, with optional relationship loading.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The post ID to retrieve.
     * @param  array|string  $with  Optional relationships to eager load.
     * @return Post|null The post instance or null if not found.
     */
    function getPost(int $id, array|string $with = []): ?Post
    {
        $query = Post::query();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }
}

if (! function_exists('getPostBySlug')) {
    /**
     * Get a post by slug.
     *
     * Retrieves a single post instance by its slug, with optional relationship loading.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The post slug to retrieve.
     * @param  array|string  $with  Optional relationships to eager load.
     * @return Post|null The post instance or null if not found.
     */
    function getPostBySlug(string $slug, array|string $with = []): ?Post
    {
        $query = Post::query();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->where('slug', $slug)->first();
    }
}

if (! function_exists('getPostCategory')) {
    /**
     * Get a post category by ID.
     *
     * Retrieves a single category instance by its ID, with optional relationship loading.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The category ID to retrieve.
     * @param  array|string  $with  Optional relationships to eager load.
     * @return PostCategory|null The category instance or null if not found.
     */
    function getPostCategory(int $id, array|string $with = []): ?PostCategory
    {
        $query = PostCategory::query();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }
}

if (! function_exists('getPostCategoryBySlug')) {
    /**
     * Get a post category by slug.
     *
     * Retrieves a single category instance by its slug, with optional relationship loading.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The category slug to retrieve.
     * @param  array|string  $with  Optional relationships to eager load.
     * @return PostCategory|null The category instance or null if not found.
     */
    function getPostCategoryBySlug(string $slug, array|string $with = []): ?PostCategory
    {
        $query = PostCategory::query();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->where('slug', $slug)->first();
    }
}

if (! function_exists('getPostTag')) {
    /**
     * Get a post tag by ID.
     *
     * Retrieves a single tag instance by its ID, with optional relationship loading.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The tag ID to retrieve.
     * @param  array|string  $with  Optional relationships to eager load.
     * @return PostTag|null The tag instance or null if not found.
     */
    function getPostTag(int $id, array|string $with = []): ?PostTag
    {
        $query = PostTag::query();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }
}

if (! function_exists('getPostTagBySlug')) {
    /**
     * Get a post tag by slug.
     *
     * Retrieves a single tag instance by its slug, with optional relationship loading.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The tag slug to retrieve.
     * @param  array|string  $with  Optional relationships to eager load.
     * @return PostTag|null The tag instance or null if not found.
     */
    function getPostTagBySlug(string $slug, array|string $with = []): ?PostTag
    {
        $query = PostTag::query();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->where('slug', $slug)->first();
    }
}

if (! function_exists('getRecentPosts')) {
    /**
     * Get recent published posts.
     *
     * Retrieves the most recently published posts, limited to the specified count.
     *
     * @since 2.0.0
     *
     * @param  int  $limit  The maximum number of posts to retrieve.
     * @param  array|string  $with  Optional relationships to eager load.
     * @return Collection<int, Post> Collection of recent posts.
     */
    function getRecentPosts(int $limit = 10, array|string $with = []): Collection
    {
        return app(BlogManager::class)->getRecentPosts($limit, $with);
    }
}

if (! function_exists('getBlogArchive')) {
    /**
     * Get blog posts with archive filters.
     *
     * Retrieves blog posts filtered by various criteria such as category, tag,
     * author, date, and search terms.
     *
     * @since 2.0.0
     *
     * @param  array  $filters  Array of filter criteria:
     *                          - status: Post status (published, draft)
     *                          - category: Category slug
     *                          - tag: Tag slug
     *                          - author: Author ID
     *                          - year: Publication year
     *                          - month: Publication month
     *                          - day: Publication day
     *                          - search: Search term
     *                          - limit: Maximum number of posts
     * @return Collection<int, Post> Collection of filtered posts.
     */
    function getBlogArchive(array $filters = []): Collection
    {
        $query = app(BlogManager::class)->getArchiveQuery($filters);

        $limit = $filters['limit'] ?? null;

        if ($limit) {
            return $query->limit($limit)->get();
        }

        return $query->get();
    }
}

if (! function_exists('postExists')) {
    /**
     * Check if a post exists by ID or slug.
     *
     * @since 2.0.0
     *
     * @param  int|string  $identifier  The post ID or slug to check.
     * @return bool True if the post exists, false otherwise.
     */
    function postExists(int|string $identifier): bool
    {
        if (is_int($identifier)) {
            return Post::where('id', $identifier)->exists();
        }

        return Post::where('slug', $identifier)->exists();
    }
}

if (! function_exists('postCategoryExists')) {
    /**
     * Check if a post category exists by ID or slug.
     *
     * @since 2.0.0
     *
     * @param  int|string  $identifier  The category ID or slug to check.
     * @return bool True if the category exists, false otherwise.
     */
    function postCategoryExists(int|string $identifier): bool
    {
        if (is_int($identifier)) {
            return PostCategory::where('id', $identifier)->exists();
        }

        return PostCategory::where('slug', $identifier)->exists();
    }
}

if (! function_exists('postTagExists')) {
    /**
     * Check if a post tag exists by ID or slug.
     *
     * @since 2.0.0
     *
     * @param  int|string  $identifier  The tag ID or slug to check.
     * @return bool True if the tag exists, false otherwise.
     */
    function postTagExists(int|string $identifier): bool
    {
        if (is_int($identifier)) {
            return PostTag::where('id', $identifier)->exists();
        }

        return PostTag::where('slug', $identifier)->exists();
    }
}

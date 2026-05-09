<?php

/**
 * Block Patterns Controller (unsynced patterns)
 *
 * Implements the WordPress `/wp/v2/block-patterns/patterns` REST shape — the
 * inserter catalogue Gutenberg uses for unsynced patterns. Lists theme +
 * user-source unsynced patterns merged. Write operations are restricted to
 * user-source rows; theme patterns return 403 on PUT/DELETE.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\BlockPatternRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\BlockPatternResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @since 1.2.0
 */
#[Group('Site Editor / Block Patterns (unsynced)', weight: 34)]
class BlockPatternsController extends Controller
{
    /**
     * @since 1.2.0
     */
    public function __construct(
        private PatternResolver $resolver,
    ) {}

    /**
     * GET /api/v1/block-patterns/patterns — list theme + user-source unsynced patterns.
     *
     * @since 1.2.0
     */
    public function index(): JsonResponse
    {
        $unsynced = array_filter(
            $this->resolver->all(),
            static fn (ResolvedPattern $pattern) => ! $pattern->synced,
        );

        return response()->json(BlockPatternResource::collection($unsynced));
    }

    /**
     * GET /api/v1/block-patterns/patterns/{slug}.
     *
     * @since 1.2.0
     */
    public function show(string $slug): JsonResponse
    {
        $pattern = $this->resolver->resolve($slug);

        if (null === $pattern || $pattern->synced) {
            return response()->json(['message' => 'Pattern not found.'], 404);
        }

        return response()->json(BlockPatternResource::toArray($pattern));
    }

    /**
     * POST /api/v1/block-patterns/patterns — create an unsynced user pattern.
     *
     * @since 1.2.0
     */
    public function store(BlockPatternRequest $request): JsonResponse
    {
        try {
            $pattern = BlockPattern::create($this->createAttributes($request->validated()));
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return $this->slugConflictResponse();
            }

            throw $e;
        }

        $resolved = $this->resolver->resolve($pattern->userFacingSlug());

        return response()->json(BlockPatternResource::toArray($resolved), 201);
    }

    /**
     * PUT /api/v1/block-patterns/patterns/{slug} — update an unsynced user pattern.
     *
     * Theme patterns are read-only at this endpoint. A PUT to a theme-source
     * slug returns 403 to make the boundary explicit; the admin can still
     * clone the theme pattern into a user pattern via the resolver's
     * `cloneToUser()` and update the resulting user-source row.
     *
     * @since 1.2.0
     */
    public function update(BlockPatternRequest $request, string $slug): JsonResponse
    {
        if (! SlugValidator::isValid($slug)) {
            return $this->invalidSlugResponse();
        }

        $existing = $this->resolver->resolve($slug);

        if (null !== $existing && BlockPattern::SOURCE_THEME === $existing->source) {
            return response()->json([
                'message' => 'Theme patterns are read-only. Clone the pattern to a user pattern before editing.',
            ], 403);
        }

        $validated = $request->validated();

        if (array_key_exists('slug', $validated) && $validated['slug'] !== $slug) {
            return response()->json([
                'message' => 'Body slug does not match URL slug.',
                'errors'  => ['slug' => ['Slug in the request body must match the URL slug.']],
            ], 422);
        }

        unset($validated['slug']);

        $pattern = $this->upsertUnsynced($slug, $validated);

        return response()->json(BlockPatternResource::toArray($this->resolver->resolve($pattern->userFacingSlug())));
    }

    /**
     * DELETE /api/v1/block-patterns/patterns/{slug}.
     *
     * Theme patterns 403; user-source rows are deleted.
     *
     * @since 1.2.0
     */
    public function destroy(string $slug): JsonResponse
    {
        if (! SlugValidator::isValid($slug)) {
            return $this->invalidSlugResponse();
        }

        $existing = $this->resolver->resolve($slug);

        if (null !== $existing && BlockPattern::SOURCE_THEME === $existing->source) {
            return response()->json([
                'message' => 'Theme patterns cannot be deleted.',
            ], 403);
        }

        $deleted = BlockPattern::query()
            ->where('slug', BlockPattern::withUserPrefix($slug))
            ->where('source', BlockPattern::SOURCE_USER)
            ->where('synced', false)
            ->delete();

        if ($deleted < 1) {
            return response()->json(['message' => 'Pattern not found.'], 404);
        }

        return response()->json(null, 204);
    }

    /**
     * Race-safe upsert for an unsynced user pattern.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $validated
     */
    protected function upsertUnsynced(string $slug, array $validated): BlockPattern
    {
        $existing = BlockPattern::query()
            ->where('slug', BlockPattern::withUserPrefix($slug))
            ->where('source', BlockPattern::SOURCE_USER)
            ->first();

        if (null !== $existing) {
            $validated['synced'] = false;
            $existing->update($validated);

            return $existing->refresh();
        }

        $attributes         = $this->createAttributes($validated);
        $attributes['slug'] = $slug;

        try {
            return BlockPattern::create($attributes);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $existing = BlockPattern::query()
                ->where('slug', BlockPattern::withUserPrefix($slug))
                ->where('source', BlockPattern::SOURCE_USER)
                ->firstOrFail();

            unset($validated['slug']);
            $validated['synced'] = false;
            $existing->update($validated);

            return $existing->refresh();
        }
    }

    /**
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $validated
     *
     * @return array<string, mixed>
     */
    protected function createAttributes(array $validated): array
    {
        $validated['source']    = BlockPattern::SOURCE_USER;
        $validated['synced']    = false;
        $validated['theme']     = null;
        $validated['author_id'] = $validated['author_id'] ?? auth()->id();

        return $validated;
    }

    /**
     * @since 1.2.0
     */
    protected function slugConflictResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'A pattern with this slug already exists.',
            'errors'  => ['slug' => ['Slug must be unique across user patterns.']],
        ], 409);
    }

    /**
     * @since 1.2.0
     */
    protected function invalidSlugResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'URL slug is not in canonical kebab-case form.',
            'errors'  => ['slug' => ['Slug must be lowercase letters, numbers, and hyphens only.']],
        ], 422);
    }

    /**
     * @since 1.2.0
     */
    protected function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->getCode();

        if ('23000' === $sqlState || '23505' === $sqlState) {
            return true;
        }

        return str_contains(strtolower($e->getMessage()), 'unique');
    }
}

<?php

/**
 * Global Styles Controller
 *
 * Implements the WordPress `/wp/v2/global-styles` REST shape (singleton-per-
 * theme — no `{id_or_slug}` segment). Read returns the resolved styles tree
 * for the active theme; write creates or updates the user-customization DB
 * row; delete reverts to file-only authority.
 *
 * Two action endpoints supplement the standard CRUD:
 *
 *   - `GET /global-styles/variations` — list theme-declared variations.
 *   - `GET /global-styles/css` — emit the resolved CSS (the same output the
 *     `@cmsGlobalStyles` Blade directive renders).
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission\GlobalStylesEmitter;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\GlobalStylesRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\GlobalStylesResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * @since 1.2.0
 */
#[Group('Site Editor / Global Styles', weight: 36)]
class GlobalStylesController extends Controller
{
    /**
     * @since 1.2.0
     */
    public function __construct(
        private GlobalStylesResolver $resolver,
        private GlobalStylesEmitter $emitter,
    ) {}

    /**
     * GET /api/v1/global-styles — return the resolved styles for the active theme.
     *
     * @since 1.2.0
     */
    public function show(): JsonResponse
    {
        $resolved = $this->resolver->resolve();

        if (null === $resolved) {
            return response()->json(['message' => 'No active theme.'], 409);
        }

        return response()->json(GlobalStylesResource::toArray($resolved));
    }

    /**
     * PUT /api/v1/global-styles — create or update the user-customization row.
     *
     * @since 1.2.0
     */
    public function update(GlobalStylesRequest $request): JsonResponse
    {
        $payload              = $request->validated();
        $payload['author_id'] = optional($request->user())->id;

        $model = $this->resolver->update($payload);

        if (null === $model) {
            return response()->json(['message' => 'No active theme.'], 409);
        }

        $resolved = $this->resolver->resolve();

        if (null === $resolved) {
            return response()->json(['message' => 'No active theme.'], 409);
        }

        return response()->json(GlobalStylesResource::toArray($resolved));
    }

    /**
     * DELETE /api/v1/global-styles — revert to file-only authority.
     *
     * @since 1.2.0
     */
    public function destroy(): JsonResponse
    {
        // Active-theme check first — `revert()` returns false in two distinct
        // cases (no active theme, no DB row to delete); separating the checks
        // keeps the no-theme path on a 409 and the no-row path on a 404.
        if (null === $this->resolver->resolve()) {
            return response()->json(['message' => 'No active theme.'], 409);
        }

        if (! $this->resolver->revert()) {
            return response()->json(['message' => 'No user customization to revert.'], 404);
        }

        $resolved = $this->resolver->resolve();

        if (null === $resolved) {
            return response()->json(['message' => 'No active theme.'], 409);
        }

        return response()->json(GlobalStylesResource::toArray($resolved));
    }

    /**
     * GET /api/v1/global-styles/variations.
     *
     * @since 1.2.0
     */
    public function variations(): JsonResponse
    {
        return response()->json($this->resolver->variations());
    }

    /**
     * GET /api/v1/global-styles/css.
     *
     * @since 1.2.0
     */
    public function css(): Response
    {
        return response($this->emitter->emit(), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
        ]);
    }
}

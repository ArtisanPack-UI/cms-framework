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
 *   - `GET /global-styles/css` — emit the full canvas stylesheet: the
 *     emitter output plus the theme's optional `style.css` and
 *     `editor.css`. The `@cmsGlobalStyles` Blade directive renders only
 *     the emitter output, so `editor.css` never leaks to the front-end.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Emission\GlobalStylesEmitter;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\GlobalStylesRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\GlobalStylesResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeStylesheetReader;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * @since 2.0.0
 */
#[Group( 'Site Editor / Global Styles', weight: 36 )]
class GlobalStylesController extends Controller
{
    /**
     * @since 2.0.0
     */
    public function __construct(
        private GlobalStylesResolver $resolver,
        private GlobalStylesEmitter $emitter,
        private ThemeStylesheetReader $stylesheets,
    ) {
    }

    /**
     * GET /api/v1/global-styles — return the resolved styles for the active theme.
     *
     * @since 2.0.0
     */
    public function show(): JsonResponse
    {
        $resolved = $this->resolver->resolve();

        if ( null === $resolved ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        return response()->json( GlobalStylesResource::toArray( $resolved ) );
    }

    /**
     * PUT /api/v1/global-styles — create or update the user-customization row.
     *
     * @since 2.0.0
     */
    public function update( GlobalStylesRequest $request ): JsonResponse
    {
        $payload              = $request->validated();
        $payload['author_id'] = optional( $request->user() )->id;

        $model = $this->resolver->update( $payload );

        if ( null === $model ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        $resolved = $this->resolver->resolve();

        if ( null === $resolved ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        return response()->json( GlobalStylesResource::toArray( $resolved ) );
    }

    /**
     * DELETE /api/v1/global-styles — revert to file-only authority.
     *
     * @since 2.0.0
     */
    public function destroy(): JsonResponse
    {
        // Active-theme check first — `revert()` returns false in two distinct
        // cases (no active theme, no DB row to delete); separating the checks
        // keeps the no-theme path on a 409 and the no-row path on a 404.
        if ( null === $this->resolver->resolve() ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        if ( ! $this->resolver->revert() ) {
            return response()->json( ['message' => 'No user customization to revert.'], 404 );
        }

        $resolved = $this->resolver->resolve();

        if ( null === $resolved ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        return response()->json( GlobalStylesResource::toArray( $resolved ) );
    }

    /**
     * GET /api/v1/global-styles/variations.
     *
     * @since 2.0.0
     */
    public function variations(): JsonResponse
    {
        return response()->json( $this->resolver->variations() );
    }

    /**
     * GET /api/v1/global-styles/css — the full canvas stylesheet for the
     * active theme. Concatenates three sources in order:
     *
     *   1. {@see GlobalStylesEmitter::emit()} — compiled CSS from theme.json
     *      `settings` + `styles`, merged with any DB override the user
     *      authored through the Styles section.
     *   2. `themes/{slug}/style.css` — the theme's hand-authored stylesheet,
     *      the same one the public front-end loads via `<link rel>`.
     *   3. `themes/{slug}/editor.css` — canvas-only overrides, the analog
     *      of WordPress's `add_editor_style()`. Never emitted on the
     *      front-end, so themes can use bare element selectors here
     *      without leaking into inspector-panel mini-previews.
     *
     * Order matters: the emitter declares `--wp--preset--*` custom
     * properties on `:root` first; `style.css` can consume those tokens
     * and override emitter rules; `editor.css` runs last so canvas-only
     * overrides win.
     *
     * The `@cmsGlobalStyles` Blade directive is intentionally NOT changed
     * to include either file — the front-end stays emitter-only, so
     * `editor.css` cannot leak there.
     *
     * Response carries an `ETag` derived from the emitter's content-hash
     * plus the freshest theme-stylesheet mtime, and honors
     * `If-None-Match` with a 304. `Cache-Control: private, must-revalidate`
     * keeps intermediaries from serving a stale payload while still
     * allowing a client (the site-editor canvas fetch) to revalidate
     * cheaply between edits.
     *
     * @since 2.0.0
     */
    public function css( Request $request ): Response
    {
        $body = $this->buildCss();
        $etag = $this->buildEtag( $body );

        if ( $request->headers->get( 'If-None-Match' ) === $etag ) {
            return response( '', 304, [
                'ETag'          => $etag,
                'Cache-Control' => 'private, must-revalidate',
            ] );
        }

        return response( $body, 200, [
            'Content-Type'  => 'text/css; charset=UTF-8',
            'ETag'          => $etag,
            'Cache-Control' => 'private, must-revalidate',
        ] );
    }

    /**
     * Assemble the concatenated CSS body from the emitter and the
     * theme's optional top-level stylesheets.
     *
     * @since 2.5.0
     */
    protected function buildCss(): string
    {
        $parts = [
            rtrim( $this->emitter->emit() ),
            $this->stylesheets->readWrapped( 'style.css' ),
            $this->stylesheets->readWrapped( 'editor.css' ),
        ];

        return implode( "\n\n", array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );
    }

    /**
     * Compose the `ETag` value. The body's SHA-1 already captures every
     * input that changes the response (emitter content-hash, style.css /
     * editor.css bytes, banner framing), so a single hash is sufficient
     * and matches the `Cache::get()` cache-key hygiene the emitter uses.
     *
     * @since 2.5.0
     */
    protected function buildEtag( string $body ): string
    {
        return '"' . sha1( $body ) . '"';
    }
}

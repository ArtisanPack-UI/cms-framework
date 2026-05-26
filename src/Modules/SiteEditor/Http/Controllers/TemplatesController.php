<?php

/**
 * Templates Controller
 *
 * Implements the WordPress `/wp/v2/templates` REST shape against
 * the cms-framework `TemplateResolver` + `Template` model.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\TemplateRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\TemplateResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @since 2.0.0
 */
#[Group( 'Site Editor / Templates', weight: 30 )]
class TemplatesController extends Controller
{
    /**
     * @since 2.0.0
     */
    public function __construct(
        private TemplateResolver $resolver,
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * GET /api/v1/templates — list resolved templates for the active theme.
     *
     * @since 2.0.0
     */
    public function index(): JsonResponse
    {
        return response()->json( TemplateResource::collection( $this->resolver->all() ) );
    }

    /**
     * GET /api/v1/templates/{slug} — show a single resolved template.
     *
     * @since 2.0.0
     */
    public function show( string $slug ): JsonResponse
    {
        $entity = $this->resolver->resolve( $slug );

        if ( null === $entity ) {
            return response()->json( ['message' => 'Template not found.'], 404 );
        }

        return response()->json( TemplateResource::toArray( $entity ) );
    }

    /**
     * POST /api/v1/templates — create a DB-stored template (custom or override).
     *
     * @since 2.0.0
     */
    public function store( TemplateRequest $request ): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        try {
            $template = Template::create( $this->normalizeAttributes( $theme, $request->validated() ) );
        } catch ( QueryException $e ) {
            if ( $this->isUniqueViolation( $e ) ) {
                return response()->json( [
                    'message' => 'A template with this slug already exists for the active theme.',
                    'errors'  => ['slug' => ['Slug must be unique within the theme.']],
                ], 409 );
            }

            throw $e;
        }

        $entity = $this->resolver->resolve( $template->slug );

        return response()->json( TemplateResource::toArray( $entity ), 201 );
    }

    /**
     * PUT /api/v1/templates/{slug} — update or upsert a DB-stored template.
     *
     * @since 2.0.0
     */
    public function update( TemplateRequest $request, string $slug ): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        // The Form Request only validates `slug` when present in the payload.
        // For PUT, the route slug is canonical and may be the only slug in
        // play, so guard it directly against the same canonical pattern.
        // Without this, a PUT to /api/v1/templates/Invalid_Slug! with no body
        // slug would create a DB row the resolver subsequently can't read.
        if ( ! SlugValidator::isValid( $slug ) ) {
            return response()->json( [
                'message' => 'URL slug is not in canonical kebab-case form.',
                'errors'  => ['slug' => ['Slug must be lowercase letters, numbers, and hyphens only.']],
            ], 422 );
        }

        $validated = $request->validated();

        // PUT identifies the resource via the route slug. If the body also
        // carries a slug it must match, so a client that mistakenly tries to
        // rename the resource gets a 422 instead of silently being ignored.
        if ( array_key_exists( 'slug', $validated ) && $validated['slug'] !== $slug ) {
            return response()->json( [
                'message' => 'Body slug does not match URL slug.',
                'errors'  => ['slug' => ['Slug in the request body must match the URL slug.']],
            ], 422 );
        }

        // Strip slug from the validated attributes — the route slug is
        // canonical for both branches below.
        unset( $validated['slug'] );

        $template = $this->upsertForTheme( $theme, $slug, $validated );

        $entity = $this->resolver->resolve( $template->slug );

        return response()->json( TemplateResource::toArray( $entity ) );
    }

    /**
     * DELETE /api/v1/templates/{slug} — revert to theme file by deleting the DB row.
     *
     * Returns 204 when a DB row was deleted, 404 when none existed.
     *
     * @since 2.0.0
     */
    public function destroy( string $slug ): JsonResponse
    {
        $deleted = $this->resolver->revert( $slug );

        if ( ! $deleted ) {
            return response()->json( ['message' => 'No template override to revert.'], 404 );
        }

        return response()->json( null, 204 );
    }

    /**
     * Race-safe upsert for the active theme + route slug.
     *
     * Splits create vs. update so omitted `is_custom` and `author_id` keep
     * their existing values on update (matching WP REST PUT semantics, where
     * `normalizeAttributes` only stamps create-time defaults).
     *
     * Concurrent PUTs can race between the existence check and the create —
     * one wins the unique-index, the loser raises a `QueryException` which we
     * recover from by re-fetching the now-existing row and falling through to
     * the update branch. The race window is narrow but the recovery is
     * deterministic so neither caller sees a 500.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $validated
     */
    protected function upsertForTheme( string $theme, string $slug, array $validated ): Template
    {
        $existing = Template::query()
            ->where( 'theme', $theme )
            ->where( 'slug', $slug )
            ->first();

        if ( null !== $existing ) {
            $existing->update( $validated );

            return $existing->refresh();
        }

        $attributes         = $this->normalizeAttributes( $theme, $validated );
        $attributes['slug'] = $slug;

        try {
            return Template::create( $attributes );
        } catch ( QueryException $e ) {
            if ( ! $this->isUniqueViolation( $e ) ) {
                throw $e;
            }

            $existing = Template::query()
                ->where( 'theme', $theme )
                ->where( 'slug', $slug )
                ->firstOrFail();

            $existing->update( $validated );

            return $existing->refresh();
        }
    }

    /**
     * @since 2.0.0
     */
    protected function activeThemeSlug(): ?string
    {
        $theme = $this->themeManager->getActiveTheme();

        return null !== $theme && ! empty( $theme['slug'] ) ? (string) $theme['slug'] : null;
    }

    /**
     * Detect a unique-constraint violation on a {@see QueryException}.
     *
     * MySQL/MariaDB report SQLSTATE 23000 + driver code 1062; PostgreSQL
     * reports SQLSTATE 23505; SQLite (used in tests) raises 23000 with
     * 'UNIQUE constraint failed' in the message.
     *
     * @since 2.0.0
     */
    protected function isUniqueViolation( QueryException $e ): bool
    {
        $sqlState = $e->getCode();

        if ( '23000' === $sqlState || '23505' === $sqlState ) {
            return true;
        }

        return str_contains( strtolower( $e->getMessage() ), 'unique' );
    }

    /**
     * Build the model attribute payload from validated request data,
     * stamping the active theme.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $validated
     *
     * @return array<string, mixed>
     */
    protected function normalizeAttributes( string $theme, array $validated ): array
    {
        $validated['theme']     = $theme;
        $validated['author_id'] = $validated['author_id'] ?? auth()->id();

        if ( ! array_key_exists( 'is_custom', $validated)) {
            $validated['is_custom'] = false;
        }

        return $validated;
    }
}

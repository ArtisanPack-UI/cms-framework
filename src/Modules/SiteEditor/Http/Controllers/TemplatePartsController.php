<?php

/**
 * Template Parts Controller
 *
 * Implements the WordPress `/wp/v2/template-parts` REST shape against
 * the cms-framework `TemplatePartResolver` + `TemplatePart` model.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\TemplatePartRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\TemplateResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @since 1.2.0
 */
#[Group( 'Site Editor / Template Parts', weight: 31 )]
class TemplatePartsController extends Controller
{
    /**
     * @since 1.2.0
     */
    public function __construct(
        private TemplatePartResolver $resolver,
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * GET /api/v1/template-parts — list resolved template parts for the active theme.
     *
     * @since 1.2.0
     */
    public function index(): JsonResponse
    {
        return response()->json( TemplateResource::collection( $this->resolver->all() ) );
    }

    /**
     * GET /api/v1/template-parts/{slug} — show a single resolved template part.
     *
     * @since 1.2.0
     */
    public function show( string $slug ): JsonResponse
    {
        $entity = $this->resolver->resolve( $slug );

        if ( null === $entity ) {
            return response()->json( [ 'message' => 'Template part not found.' ], 404 );
        }

        return response()->json( TemplateResource::toArray( $entity ) );
    }

    /**
     * POST /api/v1/template-parts — create a DB-stored template part.
     *
     * @since 1.2.0
     */
    public function store( TemplatePartRequest $request ): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( [ 'message' => 'No active theme.' ], 409 );
        }

        try {
            $part = TemplatePart::create( $this->normalizeAttributes( $theme, $request->validated() ) );
        } catch ( QueryException $e ) {
            if ( $this->isUniqueViolation( $e ) ) {
                return response()->json( [
                    'message' => 'A template part with this slug already exists for the active theme.',
                    'errors'  => [ 'slug' => [ 'Slug must be unique within the theme.' ] ],
                ], 409 );
            }

            throw $e;
        }

        $entity = $this->resolver->resolve( $part->slug );

        return response()->json( TemplateResource::toArray( $entity ), 201 );
    }

    /**
     * PUT /api/v1/template-parts/{slug} — update or upsert a DB-stored template part.
     *
     * @since 1.2.0
     */
    public function update( TemplatePartRequest $request, string $slug ): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( [ 'message' => 'No active theme.' ], 409 );
        }

        $validated = $request->validated();

        if ( array_key_exists( 'slug', $validated ) && $validated['slug'] !== $slug ) {
            return response()->json( [
                'message' => 'Body slug does not match URL slug.',
                'errors'  => [ 'slug' => [ 'Slug in the request body must match the URL slug.' ] ],
            ], 422 );
        }

        $attributes         = $this->normalizeAttributes( $theme, $validated );
        $attributes['slug'] = $slug;

        $part = TemplatePart::updateOrCreate(
            [ 'theme' => $theme, 'slug' => $slug ],
            $attributes,
        );

        $entity = $this->resolver->resolve( $part->slug );

        return response()->json( TemplateResource::toArray( $entity ) );
    }

    /**
     * DELETE /api/v1/template-parts/{slug} — revert to theme file by deleting the DB row.
     *
     * @since 1.2.0
     */
    public function destroy( string $slug ): JsonResponse
    {
        $deleted = $this->resolver->revert( $slug );

        if ( ! $deleted ) {
            return response()->json( [ 'message' => 'No template part override to revert.' ], 404 );
        }

        return response()->json( null, 204 );
    }

    /**
     * @since 1.2.0
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
     * @since 1.2.0
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
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $validated
     *
     * @return array<string, mixed>
     */
    protected function normalizeAttributes( string $theme, array $validated ): array
    {
        $validated['theme']     = $theme;
        $validated['author_id'] = $validated['author_id'] ?? auth()->id();

        if ( ! array_key_exists( 'is_custom', $validated ) ) {
            $validated['is_custom'] = false;
        }

        return $validated;
    }
}

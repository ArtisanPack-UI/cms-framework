<?php

/**
 * Templates Controller
 *
 * Implements the WordPress `/wp/v2/templates` REST shape against
 * the cms-framework `TemplateResolver` + `Template` model.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\TemplateRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\TemplateResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @since 1.2.0
 */
#[Group( 'Site Editor / Templates', weight: 30 )]
class TemplatesController extends Controller
{
    /**
     * @since 1.2.0
     */
    public function __construct(
        private TemplateResolver $resolver,
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * GET /api/v1/templates — list resolved templates for the active theme.
     *
     * @since 1.2.0
     */
    public function index(): JsonResponse
    {
        return response()->json( TemplateResource::collection( $this->resolver->all() ) );
    }

    /**
     * GET /api/v1/templates/{slug} — show a single resolved template.
     *
     * @since 1.2.0
     */
    public function show( string $slug ): JsonResponse
    {
        $entity = $this->resolver->resolve( $slug );

        if ( null === $entity ) {
            return response()->json( [ 'message' => 'Template not found.' ], 404 );
        }

        return response()->json( TemplateResource::toArray( $entity ) );
    }

    /**
     * POST /api/v1/templates — create a DB-stored template (custom or override).
     *
     * @since 1.2.0
     */
    public function store( TemplateRequest $request ): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( [ 'message' => 'No active theme.' ], 409 );
        }

        $template = Template::create( $this->normalizeAttributes( $theme, $request->validated() ) );

        $entity = $this->resolver->resolve( $template->slug );

        return response()->json( TemplateResource::toArray( $entity ), 201 );
    }

    /**
     * PUT /api/v1/templates/{slug} — update or upsert a DB-stored template.
     *
     * @since 1.2.0
     */
    public function update( TemplateRequest $request, string $slug ): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( [ 'message' => 'No active theme.' ], 409 );
        }

        $attributes = $this->normalizeAttributes( $theme, $request->validated() );
        // The slug from the route always wins over any slug in the payload —
        // PUT identifies the resource.
        $attributes['slug'] = $slug;

        $template = Template::updateOrCreate(
            [ 'theme' => $theme, 'slug' => $slug ],
            $attributes,
        );

        $entity = $this->resolver->resolve( $template->slug );

        return response()->json( TemplateResource::toArray( $entity ) );
    }

    /**
     * DELETE /api/v1/templates/{slug} — revert to theme file by deleting the DB row.
     *
     * Returns 204 when a DB row was deleted, 404 when none existed.
     *
     * @since 1.2.0
     */
    public function destroy( string $slug ): JsonResponse
    {
        $deleted = $this->resolver->revert( $slug );

        if ( ! $deleted ) {
            return response()->json( [ 'message' => 'No template override to revert.' ], 404 );
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
     * Build the model attribute payload from validated request data,
     * stamping the active theme.
     *
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

<?php

/**
 * Blocks Controller (synced patterns)
 *
 * Implements the WordPress `/wp/v2/blocks` REST shape — the `wp_block` CPT
 * Gutenberg uses for synced patterns. Scoped to user-source rows with
 * `synced = true`. Theme patterns never surface here.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\Concerns\FiltersBlockContent;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\BlockPatternRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\SyncedPatternResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @since 2.0.0
 */
#[Group( 'Site Editor / Blocks (synced patterns)', weight: 33 )]
class BlocksController extends Controller
{
    use FiltersBlockContent;

    /**
     * @since 2.0.0
     */
    public function __construct(
        private PatternResolver $resolver,
    ) {
    }

    /**
     * GET /api/v1/blocks — list synced user patterns.
     *
     * @since 2.0.0
     */
    public function index(): JsonResponse
    {
        $synced = array_filter(
            $this->resolver->all(),
            static fn ( $pattern ) => $pattern->synced && BlockPattern::SOURCE_USER === $pattern->source,
        );

        return response()->json( SyncedPatternResource::collection( $synced ) );
    }

    /**
     * GET /api/v1/blocks/{slug} — show a single synced user pattern.
     *
     * @since 2.0.0
     */
    public function show( string $slug ): JsonResponse
    {
        $pattern = $this->resolver->resolve( $slug );

        if ( null === $pattern || BlockPattern::SOURCE_USER !== $pattern->source || ! $pattern->synced ) {
            return response()->json( ['message' => 'Synced pattern not found.'], 404 );
        }

        return response()->json( SyncedPatternResource::toArray( $pattern ) );
    }

    /**
     * POST /api/v1/blocks — create a synced user pattern.
     *
     * @since 2.0.0
     */
    public function store( BlockPatternRequest $request ): JsonResponse
    {
        try {
            $pattern = BlockPattern::create( $this->filterBlockContent( $this->createAttributes( $request->validated(), synced: true ), null, 'block' ) );
        } catch ( QueryException $e ) {
            if ( $this->isUniqueViolation( $e ) ) {
                return $this->slugConflictResponse();
            }

            throw $e;
        }

        $resolved = $this->resolver->resolve( $pattern->userFacingSlug() );

        return response()->json( SyncedPatternResource::toArray( $resolved ), 201 );
    }

    /**
     * PUT /api/v1/blocks/{slug} — update or upsert a synced user pattern.
     *
     * @since 2.0.0
     */
    public function update( BlockPatternRequest $request, string $slug ): JsonResponse
    {
        if ( ! SlugValidator::isValid( $slug ) ) {
            return $this->invalidSlugResponse();
        }

        $validated = $request->validated();

        if ( array_key_exists( 'slug', $validated ) && $validated['slug'] !== $slug ) {
            return response()->json( [
                'message' => 'Body slug does not match URL slug.',
                'errors'  => ['slug' => ['Slug in the request body must match the URL slug.']],
            ], 422 );
        }

        unset( $validated['slug'] );

        $pattern = $this->upsertSynced( $slug, $validated );

        return response()->json( SyncedPatternResource::toArray( $this->resolver->resolve( $pattern->userFacingSlug() ) ) );
    }

    /**
     * DELETE /api/v1/blocks/{slug} — delete a synced user pattern.
     *
     * @since 2.0.0
     */
    public function destroy( string $slug ): JsonResponse
    {
        if ( ! SlugValidator::isValid( $slug ) ) {
            return $this->invalidSlugResponse();
        }

        $deleted = BlockPattern::query()
            ->where( 'slug', BlockPattern::withUserPrefix( $slug ) )
            ->where( 'source', BlockPattern::SOURCE_USER )
            ->where( 'synced', true )
            ->delete();

        if ( $deleted < 1 ) {
            return response()->json( ['message' => 'Synced pattern not found.'], 404 );
        }

        return response()->json( null, 204 );
    }

    /**
     * Race-safe upsert for a synced user pattern.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $validated
     */
    protected function upsertSynced( string $slug, array $validated ): BlockPattern
    {
        $existing = BlockPattern::query()
            ->where( 'slug', BlockPattern::withUserPrefix( $slug ) )
            ->where( 'source', BlockPattern::SOURCE_USER )
            ->first();

        if ( null !== $existing ) {
            // Lock to synced for this endpoint — stops a PUT from accidentally
            // unsync-ing a pattern via this controller. Cross-state transitions
            // happen through the unsynced controller's POST/PUT instead.
            $validated['synced'] = true;
            $validated           = $this->filterBlockContent( $validated, $existing, 'block' );
            $existing->update( $validated );

            return $existing->refresh();
        }

        $attributes         = $this->createAttributes( $validated, synced: true );
        $attributes['slug'] = $slug;

        try {
            return BlockPattern::create( $this->filterBlockContent( $attributes, null, 'block' ) );
        } catch ( QueryException $e ) {
            if ( ! $this->isUniqueViolation( $e ) ) {
                throw $e;
            }

            $existing = BlockPattern::query()
                ->where( 'slug', BlockPattern::withUserPrefix( $slug ) )
                ->where( 'source', BlockPattern::SOURCE_USER )
                ->firstOrFail();

            unset( $validated['slug'] );
            $validated['synced'] = true;
            $validated           = $this->filterBlockContent( $validated, $existing, 'block' );
            $existing->update( $validated );

            return $existing->refresh();
        }
    }

    /**
     * Build the model attribute payload for a create call.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $validated
     *
     * @return array<string, mixed>
     */
    protected function createAttributes( array $validated, bool $synced ): array
    {
        $validated['source']    = BlockPattern::SOURCE_USER;
        $validated['synced']    = $synced;
        $validated['theme']     = null;
        $validated['author_id'] = $validated['author_id'] ?? auth()->id();

        return $validated;
    }

    /**
     * @since 2.0.0
     */
    protected function slugConflictResponse(): JsonResponse
    {
        return response()->json( [
            'message' => 'A pattern with this slug already exists.',
            'errors'  => ['slug' => ['Slug must be unique across user patterns.']],
        ], 409 );
    }

    /**
     * @since 2.0.0
     */
    protected function invalidSlugResponse(): JsonResponse
    {
        return response()->json( [
            'message' => 'URL slug is not in canonical kebab-case form.',
            'errors'  => ['slug' => ['Slug must be lowercase letters, numbers, and hyphens only.']],
        ], 422 );
    }

    /**
     * Whether the query failed on a unique constraint, by each driver's
     * own unique-violation signal: PostgreSQL SQLSTATE `23505`, MySQL /
     * MariaDB driver code 1062, SQL Server driver codes 2601 / 2627, or a
     * SQLite message that starts with `UNIQUE constraint failed`.
     *
     * SQLSTATE `23000` alone covers every integrity violation (a NOT NULL
     * or foreign-key failure too), and the exception message and some
     * driver diagnostics (PostgreSQL's `Failing row contains (…)`) carry
     * row values, so the word "unique" anywhere in them proves nothing.
     *
     * @since 2.0.0
     * @since 2.15.0 Matches only driver-specific unique-violation signals.
     */
    protected function isUniqueViolation( QueryException $e ): bool
    {
        if ( '23505' === (string) $e->getCode() ) {
            return true;
        }

        if ( in_array( $e->errorInfo[1] ?? null, [1062, 2601, 2627], true ) ) {
            return true;
        }

        return str_starts_with( strtolower( (string) ( $e->errorInfo[2] ?? '' ) ), 'unique constraint failed' );
    }
}

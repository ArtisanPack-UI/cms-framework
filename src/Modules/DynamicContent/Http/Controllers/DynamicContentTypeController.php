<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Requests\DynamicContentTypeRequest;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Resources\DynamicContentTypeResource;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class DynamicContentTypeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DynamicContentTypeManager $manager,
        protected DynamicContentTypeRegistry $registry,
    ) {
    }

    public function index(): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize( 'viewAny', DynamicContentType::class );

        return DynamicContentTypeResource::collection(
            DynamicContentType::with( 'fields' )->orderBy( 'name' )->get(),
        );
    }

    public function registered(): JsonResponse
    {
        $this->authorize( 'viewAny', DynamicContentType::class );

        $types = [];

        foreach ( $this->registry->all() as $slug => $type ) {
            $types[] = [
                'slug'        => $slug,
                'name'        => $type['name'],
                'cardinality' => $type['cardinality']->value,
                'source'      => $type['source']->value,
                'fields'      => array_map( fn ( $f ) => [
                    'slug'  => $f['slug'],
                    'label' => $f['label'],
                    'type'  => $f['type'],
                ], $type['fields'] ),
            ];
        }

        return response()->json( [ 'data' => $types ] );
    }

    public function store( DynamicContentTypeRequest $request ): DynamicContentTypeResource
    {
        $this->authorize( 'create', DynamicContentType::class );

        $type = $this->manager->create( $request->validated() );

        return new DynamicContentTypeResource( $type );
    }

    public function show( DynamicContentType $type ): DynamicContentTypeResource
    {
        $this->authorize( 'view', $type );

        return new DynamicContentTypeResource( $type->load( 'fields' ) );
    }

    public function update( DynamicContentTypeRequest $request, DynamicContentType $type ): DynamicContentTypeResource
    {
        $this->authorize( 'update', $type );

        return new DynamicContentTypeResource( $this->manager->update( $type, $request->validated() ) );
    }

    public function destroy( DynamicContentType $type ): Response
    {
        $this->authorize( 'delete', $type );

        $this->manager->delete( $type );

        return response()->noContent();
    }
}

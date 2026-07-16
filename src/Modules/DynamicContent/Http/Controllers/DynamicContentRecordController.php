<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Requests\DynamicContentRecordRequest;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Resources\DynamicContentRecordResource;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentRecordManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecord;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class DynamicContentRecordController extends Controller
{
    use AuthorizesRequests;

    public function __construct( protected DynamicContentRecordManager $manager )
    {
    }

    public function index( DynamicContentType $type ): AnonymousResourceCollection
    {
        $this->authorize( 'viewAnyForType', [ DynamicContentRecord::class, $type ] );

        $records = $type->records()->with( 'values.field', 'type.fields' )->get();

        return DynamicContentRecordResource::collection( $records );
    }

    public function store( DynamicContentRecordRequest $request, DynamicContentType $type ): DynamicContentRecordResource
    {
        $this->authorize( 'createForType', [ DynamicContentRecord::class, $type ] );

        return new DynamicContentRecordResource( $this->manager->create( $type, $request->validated() ) );
    }

    public function show( DynamicContentType $type, DynamicContentRecord $record ): DynamicContentRecordResource
    {
        $this->authorize( 'view', $record );

        abort_unless( $record->dynamic_content_type_id === $type->id, 404 );

        return new DynamicContentRecordResource( $record->load( 'values.field', 'type.fields' ) );
    }

    public function update(
        DynamicContentRecordRequest $request,
        DynamicContentType $type,
        DynamicContentRecord $record,
    ): DynamicContentRecordResource {
        $this->authorize( 'update', $record );

        abort_unless( $record->dynamic_content_type_id === $type->id, 404 );

        return new DynamicContentRecordResource( $this->manager->update( $record, $request->validated() ) );
    }

    public function destroy( DynamicContentType $type, DynamicContentRecord $record ): Response
    {
        $this->authorize( 'delete', $record );

        abort_unless( $record->dynamic_content_type_id === $type->id, 404 );

        $this->manager->delete( $record );

        return response()->noContent();
    }
}

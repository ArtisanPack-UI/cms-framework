<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services\DynamicContentResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DynamicContentResolverController extends Controller
{
    use AuthorizesRequests;

    /**
     * Cap on the size of a single preview payload.
     *
     * 65 KB is generous for post bodies + templates but short-circuits
     * any megabyte-scale token-bomb payload before the resolver's
     * `preg_replace_callback` gets to iterate.
     */
    public const MAX_CONTENT_LENGTH = 65_535;

    public function __construct( protected DynamicContentResolver $resolver )
    {
    }

    public function resolve( Request $request ): JsonResponse
    {
        $this->authorize( 'viewAny', DynamicContentType::class );

        $validated = $request->validate( [
            'content' => [ 'required', 'string', 'max:' . self::MAX_CONTENT_LENGTH ],
        ] );

        return response()->json( [
            'data' => [
                'rendered'  => $this->resolver->render( $validated['content'] ),
                'signature' => $this->resolver->signatureFor( $validated['content'] ),
            ],
        ] );
    }
}

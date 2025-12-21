<?php

declare( strict_types = 1 );

/**
 * Permission Resource for the CMS Framework Users Module.
 *
 * This resource class transforms permission model instances into JSON API responses,
 * including permission data and optionally loaded relationships such as roles.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for permission data transformation.
 *
 * Transforms permission model instances into properly formatted JSON responses
 * for API endpoints, including related role data when loaded.
 *
 * @since 1.0.0
 */
class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the permission model instance into an array suitable for JSON API responses.
     * Includes permission attributes and conditionally includes role data when the
     * roles relationship is loaded.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     *
     * @return array<string, mixed> The transformed permission data array.
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles'      => $this->whenLoaded( 'roles', function () {
                return $this->roles->map( function ( $role ) {
                    return [
                        'id'   => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ];
                } );
            } ),
        ];
    }
}

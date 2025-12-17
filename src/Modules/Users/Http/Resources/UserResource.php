<?php

/**
 * User Resource for the CMS Framework Users Module.
 *
 * This resource class transforms user model instances into JSON API responses,
 * including user data and optionally loaded relationships such as roles.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for user data transformation.
 *
 * Transforms user model instances into properly formatted JSON responses
 * for API endpoints, including related role data when loaded.
 *
 * @since 1.0.0
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the user model instance into an array suitable for JSON API responses.
     * Includes user attributes and conditionally includes role data when the roles
     * relationship is loaded.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     * @return array<string, mixed> The transformed user data array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ];
                });
            }),
        ];
    }
}

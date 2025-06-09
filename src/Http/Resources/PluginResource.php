<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Plugin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plugin */
class PluginResource extends JsonResource
{
	public function toArray( Request $request ): array
	{
		return [
			'id'                    => $this->id,
			'slug'                  => $this->slug,
			'composer_package_name' => $this->composer_package_name,
			'directory_name'        => $this->directory_name,
			'plugin_class'          => $this->plugin_class,
			'version'               => $this->version,
			'is_active'             => $this->is_active,
			'config'                => $this->config,
			'created_at'            => $this->created_at,
			'updated_at'            => $this->updated_at,
		];
	}
}

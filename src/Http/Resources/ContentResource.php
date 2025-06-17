<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Content */
class ContentResource extends JsonResource
{
    public function toArray( Request $request ): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'slug'        => $this->slug,
            'content'     => $this->content,
            'type'        => $this->type,
            'status'      => $this->status,
            'author_id'   => $this->author_id,
            'parent_id'   => $this->parent_id,
            'meta'        => $this->meta,
            'published_at'=> $this->published_at,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}

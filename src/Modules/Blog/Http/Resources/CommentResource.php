<?php

declare( strict_types=1 );

/**
 * Comment Resource for the CMS Framework Blog Module.
 *
 * Transforms `Comment` model instances into JSON API responses. The
 * envelope is shaped so the visual-editor's comment renderer can
 * consume it without further massaging — `author` is normalized to
 * `{ name, url, avatar_url }` whether the comment was left by a
 * registered user or a guest.
 *
 * @since 2.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @since 2.1.0
 */
class CommentResource extends JsonResource
{
    /**
     * @since 2.1.0
     *
     * @return array<string, mixed>
     */
    public function toArray( Request $request ): array
    {
        $user        = $request->user();
        $canModerate = null !== $user && $user->can( applyFilters( 'comments.moderate', 'comments.moderate' ) );

        return [
            'id'             => $this->id,
            'post_id'        => $this->post_id,
            'parent_id'      => $this->parent_id,
            'user_id'        => $this->user_id,
            'content'        => $this->content,
            'status'         => $this->status,
            'approved_at'    => $this->approved_at,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'author'         => [
                'name'       => $this->author->name ?? '',
                'url'        => $this->author->url ?? '',
                'avatar_url' => $this->avatar_url,
                'is_guest'   => null === $this->user_id,
            ],
            'permalink'      => $this->permalink,
            // `edit_link` exposes an admin URL — only surface it to
            // viewers that can actually moderate the comment.
            'edit_link'      => $this->when( $canModerate, fn () => $this->edit_link ),
            'reply_link'     => $this->reply_link,
            'replies'        => CommentResource::collection(
                $this->whenLoaded( 'replies' ),
            ),
        ];
    }
}

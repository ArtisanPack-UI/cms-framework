<?php

declare( strict_types=1 );

/**
 * Comment Factory for the CMS Framework Blog Module.
 *
 * Generates fake comment data for testing and seeding. States cover
 * the approval lifecycle (approved / pending / spam / trash) and the
 * threading shape (replies hung off a parent comment).
 *
 * @since 2.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Database\Factories;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment;
use ArtisanPackUI\CMSFramework\Modules\Users\Database\Factories\Concerns\ResolvesUserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    use ResolvesUserFactory;

    /**
     * @var class-string<Comment>
     */
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `post_id` is intentionally omitted from the default state.
        // Callers must supply a post via `->for( $post )` or by
        // setting `post_id` explicitly — Comments cannot exist
        // without a parent post, and the Post model's `newFactory()`
        // override is not in place (consistent with the rest of
        // the Blog tests, which `Post::create()` directly rather
        // than going through a factory).
        return [
            'parent_id'    => null,
            'user_id'      => null,
            'author_name'  => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'author_url'   => fake()->optional( 0.3 )->url(),
            'content'      => fake()->paragraph(),
            'status'       => Comment::STATUS_APPROVED,
            'approved_at'  => now(),
        ];
    }

    /**
     * Indicate the comment was left by an authenticated user. Clears
     * the guest `author_*` fields so the model's accessor falls back
     * to the related User.
     *
     * @since 2.1.0
     */
    public function byUser( ?int $userId = null ): static
    {
        return $this->state( fn ( array $attributes ) => [
            'user_id'      => $userId ?? $this->resolveUserFactory(),
            'author_name'  => null,
            'author_email' => null,
            'author_url'   => null,
        ] );
    }

    /**
     * Indicate the comment is a reply to another comment.
     *
     * @since 2.1.0
     */
    public function replyTo( Comment|int $parent ): static
    {
        $parentId = $parent instanceof Comment ? $parent->id : $parent;
        $postId   = $parent instanceof Comment ? $parent->post_id : null;

        return $this->state( fn ( array $attributes ) => array_filter( [
            'parent_id' => $parentId,
            'post_id'   => $postId,
        ], static fn ( $value ) => null !== $value ) );
    }

    /**
     * Indicate the comment is pending moderation.
     *
     * @since 2.1.0
     */
    public function pending(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'status'      => Comment::STATUS_PENDING,
            'approved_at' => null,
        ] );
    }

    /**
     * Indicate the comment has been flagged as spam.
     *
     * @since 2.1.0
     */
    public function spam(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'status'      => Comment::STATUS_SPAM,
            'approved_at' => null,
        ] );
    }

    /**
     * Indicate the comment is in the trash.
     *
     * @since 2.1.0
     */
    public function trash(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'status'      => Comment::STATUS_TRASH,
            'approved_at' => null,
        ] );
    }
}

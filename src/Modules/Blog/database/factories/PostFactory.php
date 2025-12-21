<?php

declare( strict_types = 1 );

/**
 * Post Factory for the CMS Framework Blog Module.
 *
 * This factory generates fake post data for testing purposes,
 * including published, draft, and scheduled states.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Database\Factories;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating post test data.
 *
 * Provides states for different post statuses and publication scenarios.
 *
 * @since 1.0.0
 *
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @since 1.0.0
     *
     * @var class-string<Post>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * Generates a published post by default with random content and metadata.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The default post attributes.
     */
    public function definition(): array
    {
        $title = fake()->sentence( 6, true );

        return [
            'title'        => $title,
            'slug'         => Str::slug( $title ),
            'content'      => fake()->paragraphs( 5, true ),
            'excerpt'      => fake()->paragraph(),
            'author_id'    => User::factory(),
            'status'       => 'published',
            'published_at' => now(),
            'metadata'     => [
                'seo_title'       => $title,
                'seo_description' => fake()->sentence(),
            ],
        ];
    }

    /**
     * Indicate that the post is a draft.
     *
     * Sets the status to 'draft' and clears the published_at timestamp.
     *
     * @since 1.0.0
     *
     * @return static The factory instance for method chaining.
     */
    public function draft(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'status'       => 'draft',
            'published_at' => null,
        ] );
    }

    /**
     * Indicate that the post is scheduled for future publication.
     *
     * Sets the status to 'published' with a future published_at timestamp.
     *
     * @since 1.0.0
     *
     * @return static The factory instance for method chaining.
     */
    public function scheduled(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'status'       => 'published',
            'published_at' => now()->addDays( rand( 1, 30 ) ),
        ] );
    }

    /**
     * Indicate that the post is published.
     *
     * Sets the status to 'published' with a past published_at timestamp.
     *
     * @since 1.0.0
     *
     * @return static The factory instance for method chaining.
     */
    public function published(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'status'       => 'published',
            'published_at' => now()->subDays( rand( 0, 365 ) ),
        ] );
    }

    /**
     * Indicate that the post was published on a specific date.
     *
     * @since 1.0.0
     *
     * @param  DateTimeInterface|string  $date  The publication date.
     *
     * @return static The factory instance for method chaining.
     */
    public function publishedAt( $date ): static
    {
        return $this->state( fn ( array $attributes ) => [
            'status'       => 'published',
            'published_at' => $date,
        ] );
    }

    /**
     * Indicate that the post has a specific author.
     *
     * @since 1.0.0
     *
     * @param  int  $authorId  The author's user ID.
     *
     * @return static The factory instance for method chaining.
     */
    public function byAuthor( int $authorId ): static
    {
        return $this->state( fn ( array $attributes ) => [
            'author_id' => $authorId,
        ]);
    }
}

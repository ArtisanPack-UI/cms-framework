<?php

declare( strict_types = 1 );

/**
 * PostTag Factory for the CMS Framework Blog Module.
 *
 * This factory generates fake post tag data for testing purposes.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Database\Factories;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating post tag test data.
 *
 * Provides simple tag generation for testing blog functionality.
 *
 * @since 1.0.0
 *
 * @extends Factory<PostTag>
 */
class PostTagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @since 1.0.0
     *
     * @var class-string<PostTag>
     */
    protected $model = PostTag::class;

    /**
     * Define the model's default state.
     *
     * Generates a tag with a random name, slug, and description.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The default tag attributes.
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name'        => ucfirst( $name ),
            'slug'        => Str::slug( $name ),
            'description' => fake()->sentence(),
            'order'       => 0,
            'metadata'    => [
                'seo_title'       => ucfirst( $name ),
                'seo_description' => fake()->sentence(),
            ],
        ];
    }
}

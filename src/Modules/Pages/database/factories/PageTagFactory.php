<?php

/**
 * PageTag Factory for the CMS Framework Pages Module.
 *
 * This factory generates fake page tag data for testing purposes.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Pages\Database\Factories
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Database\Factories;

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating page tag test data.
 *
 * Creates random tags with metadata for testing purposes.
 *
 * @since 2.0.0
 *
 * @extends Factory<PageTag>
 */
class PageTagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @since 2.0.0
     *
     * @var class-string<PageTag>
     */
    protected $model = PageTag::class;

    /**
     * Define the model's default state.
     *
     * Generates a tag with random name, description, and metadata.
     *
     * @since 2.0.0
     *
     * @return array<string, mixed> The default tag attributes.
     */
    public function definition(): array
    {
        $name = fake()->words(rand(1, 2), true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'order' => 0,
            'metadata' => [
                'seo_title' => ucwords($name),
                'seo_description' => fake()->sentence(),
            ],
        ];
    }

    /**
     * Set the order for the tag.
     *
     * @since 2.0.0
     *
     * @param  int  $order  The tag order.
     * @return static The factory instance for method chaining.
     */
    public function withOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }
}

<?php

declare( strict_types = 1 );

/**
 * PageCategory Factory for the CMS Framework Pages Module.
 *
 * This factory generates fake page category data for testing purposes,
 * including support for hierarchical parent-child relationships.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Database\Factories;

use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating page category test data.
 *
 * Supports creating hierarchical categories with parent-child relationships.
 *
 * @since 1.0.0
 *
 * @extends Factory<PageCategory>
 */
class PageCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @since 1.0.0
     *
     * @var class-string<PageCategory>
     */
    protected $model = PageCategory::class;

    /**
     * Define the model's default state.
     *
     * Generates a category with random name, description, and metadata.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The default category attributes.
     */
    public function definition(): array
    {
        $name = fake()->words( rand( 1, 3 ), true );

        return [
            'name'        => ucwords( $name ),
            'slug'        => Str::slug( $name ),
            'description' => fake()->sentence(),
            'parent_id'   => null,
            'order'       => 0,
            'metadata'    => [
                'seo_title'       => ucwords( $name ),
                'seo_description' => fake()->sentence(),
            ],
        ];
    }

    /**
     * Indicate that the category has a parent category.
     *
     * @since 1.0.0
     *
     * @param  int  $parentId  The parent category ID.
     *
     * @return static The factory instance for method chaining.
     */
    public function withParent( int $parentId ): static
    {
        return $this->state( fn ( array $attributes ) => [
            'parent_id' => $parentId,
        ] );
    }

    /**
     * Indicate that the category should be created as a child of another category.
     *
     * This will create a parent category and set it as the parent.
     *
     * @since 1.0.0
     *
     * @return static The factory instance for method chaining.
     */
    public function asChild(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'parent_id' => PageCategory::factory(),
        ] );
    }

    /**
     * Set the order for the category.
     *
     * @since 1.0.0
     *
     * @param  int  $order  The category order.
     *
     * @return static The factory instance for method chaining.
     */
    public function withOrder( int $order ): static
    {
        return $this->state( fn ( array $attributes ) => [
            'order' => $order,
        ] );
    }
}

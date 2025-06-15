<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\Page;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'title'       => $this->faker->sentence(3),
            'slug'        => $this->faker->unique()->slug(),
            'content'     => $this->faker->paragraphs(3, true),
            'status'      => $this->faker->randomElement(['draft', 'pending', 'published']),
            'parent_id'   => null,
            'order'       => $this->faker->numberBetween(0, 100),
            'published_at' => $this->faker->boolean(70) ? Carbon::now() : null,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ];
    }

    /**
     * Indicate that the page is published.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function published()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'published',
                'published_at' => Carbon::now(),
            ];
        });
    }

    /**
     * Indicate that the page is a draft.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function draft()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'draft',
                'published_at' => null,
            ];
        });
    }

    /**
     * Indicate that the page is pending review.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'published_at' => null,
            ];
        });
    }
}

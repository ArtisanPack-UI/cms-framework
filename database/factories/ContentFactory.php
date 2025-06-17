<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ContentFactory extends Factory
{
    protected $model = Content::class;

    public function definition(): array
    {
        return [
            'title'        => $this->faker->sentence(),
            'slug'         => $this->faker->slug(),
            'content'      => $this->faker->paragraphs(3, true),
            'type'         => $this->faker->randomElement(['post', 'page']),
            'status'       => $this->faker->randomElement(['draft', 'published', 'pending']),
            'author_id'    => 1, // Default to first user, can be overridden
            'parent_id'    => null,
            'meta'         => ['key' => $this->faker->word(), 'value' => $this->faker->sentence()],
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ];
    }
}

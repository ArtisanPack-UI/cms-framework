<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\ContentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ContentTypeFactory extends Factory
{
    protected $model = ContentType::class;

    public function definition(): array
    {
        return [
            'handle'       => $this->faker->unique()->word(),
            'label'        => $this->faker->word(),
            'label_plural' => $this->faker->word(),
            'slug'         => $this->faker->slug(),
            'definition'   => [
                'public'       => $this->faker->boolean(),
                'hierarchical' => $this->faker->boolean(),
                'supports'     => $this->faker->randomElements(['title', 'content', 'author', 'featured_image', 'status', 'categories', 'tags'], $this->faker->numberBetween(1, 7)),
                'fields'       => []
            ],
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ];
    }
}

<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TaxonomyFactory extends Factory
{
    protected $model = Taxonomy::class;

    public function definition(): array
    {
        return [
            'handle'        => $this->faker->unique()->word(),
            'label'         => $this->faker->word(),
            'label_plural'  => $this->faker->word(),
            'content_types' => $this->faker->randomElements(['post', 'page'], $this->faker->numberBetween(1, 2)),
            'hierarchical'  => $this->faker->boolean(),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ];
    }
}

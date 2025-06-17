<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->word(),
            'slug'        => $this->faker->slug(),
            'taxonomy_id' => function () {
                return \ArtisanPackUI\CMSFramework\Models\Taxonomy::factory()->create()->id;
            },
            'parent_id'   => null, // Default to null, can be overridden for hierarchical terms
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ];
    }
}

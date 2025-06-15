<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class MediaCategoryFactory extends Factory
{
	protected $model = MediaCategory::class;

	public function definition(): array
	{
		return [
			'name'       => $this->faker->name(),
			'slug'       => $this->faker->slug(),
			'created_at' => Carbon::now(),
			'updated_at' => Carbon::now(),
		];
	}
}

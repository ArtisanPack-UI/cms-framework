<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\MediaTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class MediaTagFactory extends Factory
{
	protected $model = MediaTag::class;

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

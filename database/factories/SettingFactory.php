<?php

namespace Database\Factories;

use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SettingFactory extends Factory
{
	protected $model = Setting::class;

	public function definition(): array
	{
		return [
			'name'       => $this->faker->name(),
			'value'      => $this->faker->word(),
			'category'   => $this->faker->word(),
			'created_at' => Carbon::now(),
			'updated_at' => Carbon::now(),
		];
	}
}

<?php

namespace ArtisanPackUI\Database\Factories;

use ArtisanPackUI\CMSFramework\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PluginFactory extends Factory
{
	protected $model = Plugin::class;

	public function definition(): array
	{
		return [
			'slug'                  => $this->faker->slug(),
			'composer_package_name' => $this->faker->name(),
			'directory_name'        => $this->faker->name(),
			'plugin_class'          => $this->faker->word(),
			'version'               => $this->faker->word(),
			'is_active'             => $this->faker->boolean(),
			'config'                => $this->faker->words(),
			'created_at'            => Carbon::now(),
			'updated_at'            => Carbon::now(),
		];
	}
}

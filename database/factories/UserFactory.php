<?php

namespace Database\Factories;

use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UserFactory extends Factory
{
	protected $model = User::class;

	public function definition(): array
	{
		return [
			'username'          => $this->faker->userName(),
			'email'             => $this->faker->unique()->safeEmail(),
			'email_verified_at' => Carbon::now(),
			'password'          => bcrypt( $this->faker->password() ),
			'role_id'           => $this->faker->word(),
			'first_name'        => $this->faker->firstName(),
			'last_name'         => $this->faker->lastName(),
			'website'           => $this->faker->word(),
			'bio'               => $this->faker->word(),
			'links'             => $this->faker->word(),
			'settings'          => $this->faker->word(),
			'created_at'        => Carbon::now(),
			'updated_at'        => Carbon::now(),
		];
	}
}

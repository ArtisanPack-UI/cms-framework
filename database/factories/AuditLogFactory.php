<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AuditLogFactory extends Factory
{
	protected $model = AuditLog::class;

	public function definition(): array
	{
		return [
			'user_id'    => $this->faker->randomNumber(),
			'action'     => $this->faker->word(),
			'message'    => $this->faker->word(),
			'ip_address' => $this->faker->ipv4(),
			'user_agent' => $this->faker->word(),
			'status'     => $this->faker->word(),
			'created_at' => Carbon::now(),
			'updated_at' => Carbon::now(),
		];
	}
}

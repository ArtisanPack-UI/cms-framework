<?php

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class MediaFactory extends Factory
{
	protected $model = Media::class;

	public function definition(): array
	{
		return [
			'user_id'       => 1,
			'file_name'     => $this->faker->unique()->word() . '.jpg',
			'mime_type'     => $this->faker->randomElement( [ 'image/jpeg', 'image/png', 'application/pdf' ] ),
			'path'          => $this->faker->unique()->url() . '/' . $this->faker->unique()->sha1() . '.jpg',
			'size'          => $this->faker->numberBetween( 1024, 1048576 ),
			'alt_text'      => $this->faker->text(),
			'is_decorative' => $this->faker->boolean(),
			'metadata'      => $this->faker->word(),
			'created_at'    => Carbon::now(),
			'updated_at'    => Carbon::now(),
		];
	}
}

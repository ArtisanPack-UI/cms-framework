<?php
/**
 * Media Factory
 *
 * Factory for creating test instances of Media models.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Database\Factories
 * @since      1.0.0
 */

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Factory for Media model
 *
 * Provides a factory for creating test instances of Media models with fake data.
 *
 * @since 1.0.0
 */
class MediaFactory extends Factory
{
	/**
	 * The name of the model that this factory creates.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $model = Media::class;

	/**
	 * Define the model's default state.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
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

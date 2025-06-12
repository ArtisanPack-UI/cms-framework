<?php
/**
 * Setting Factory
 *
 * Factory for creating test instances of the Setting model.
 *
 * @link          https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package       ArtisanPackUI\CMSFramework
 * @subpackage    ArtisanPackUI\CMSFramework\Database\Factories
 * @since         1.0.0
 *
 * @wordpress-plugin
 * Description:
 */

namespace ArtisanPackUI\Database\factories;

use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Factory for creating Setting model instances
 *
 * This factory is used to generate test instances of the Setting model
 * with fake data for testing purposes.
 *
 * @since 1.0.0
 */
class SettingFactory extends Factory
{
    /**
     * The model that this factory creates
     *
     * @since 1.0.0
     * @var string
     */
    protected $model = Setting::class;

    /**
     * Define the model's default state
     *
     * Generates fake data for a Setting model instance.
     *
     * @since 1.0.0
     * @return array<string, mixed> An array of attributes to set on the model
     */
    public function definition(): array
    {
        return [
            'key'        => $this->faker->unique()->word(),
            'value'      => $this->faker->word(),
            'type'       => $this->faker->randomElement( [ 'integer', 'json', 'boolean', 'string' ] ),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

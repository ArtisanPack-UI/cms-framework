<?php

namespace ArtisanPackUI\Database\Factories;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\ArtisanPackUI\CMSFramework\Modules\Users\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Role>
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst( $name ),
            'slug' => Str::slug( $name ),
        ];
    }

    /**
     * Create an admin role.
     */
    public function admin(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'name' => 'Admin',
            'slug' => 'admin',
        ] );
    }

    /**
     * Create an editor role.
     */
    public function editor(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'name' => 'Editor',
            'slug' => 'editor',
        ] );
    }

    /**
     * Create a user role.
     */
    public function user(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'name' => 'User',
            'slug' => 'user',
        ] );
    }
}

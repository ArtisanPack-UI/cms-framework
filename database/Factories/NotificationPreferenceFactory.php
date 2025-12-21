<?php

namespace ArtisanPackUI\Database\Factories;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<NotificationPreference>
     */
    protected $model = NotificationPreference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'           => 1, // Default user ID for package tests
            'notification_type' => fake()->randomElement( [
                'user.registered',
                'user.login.failed',
                'system.error',
                'backup.completed',
                'content.published',
            ] ),
            'is_enabled'    => fake()->boolean( 80 ),
            'email_enabled' => fake()->boolean( 60 ),
        ];
    }

    /**
     * Indicate that the notification preference is enabled.
     */
    public function enabled(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'is_enabled' => true,
        ] );
    }

    /**
     * Indicate that the notification preference is disabled.
     */
    public function disabled(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'is_enabled' => false,
        ] );
    }

    /**
     * Indicate that email notifications are enabled.
     */
    public function emailEnabled(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'email_enabled' => true,
        ] );
    }

    /**
     * Indicate that email notifications are disabled.
     */
    public function emailDisabled(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'email_enabled' => false,
        ]);
    }
}

<?php

namespace ArtisanPackUI\Database\Factories;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Notification>
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type'     => fake()->randomElement( NotificationType::cases() ),
            'title'    => fake()->sentence( 4 ),
            'content'  => fake()->paragraph(),
            'metadata' => [
                'category' => fake()->randomElement( ['user', 'system', 'content', 'security'] ),
                'priority' => fake()->randomElement( ['low', 'medium', 'high'] ),
            ],
            'send_email' => fake()->boolean( 30 ),
        ];
    }

    /**
     * Indicate that the notification is of type Error.
     */
    public function error(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'type' => NotificationType::Error,
        ] );
    }

    /**
     * Indicate that the notification is of type Warning.
     */
    public function warning(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'type' => NotificationType::Warning,
        ] );
    }

    /**
     * Indicate that the notification is of type Success.
     */
    public function success(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'type' => NotificationType::Success,
        ] );
    }

    /**
     * Indicate that the notification is of type Info.
     */
    public function info(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'type' => NotificationType::Info,
        ] );
    }

    /**
     * Indicate that the notification should send email.
     */
    public function withEmail(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'send_email' => true,
        ] );
    }

    /**
     * Indicate that the notification should not send email.
     */
    public function withoutEmail(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'send_email' => false,
        ]);
    }
}

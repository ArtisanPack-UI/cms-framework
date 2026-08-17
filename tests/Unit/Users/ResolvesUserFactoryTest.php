<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Users\Database\Factories\Concerns\ResolvesUserFactory;
use ArtisanPackUI\CMSFramework\Tests\Support\TestPlainContentTypeModel;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Exposes the trait's protected resolver so each guard branch can be
 * exercised directly.
 */
function makeResolvingFactory(): object
{
    return new class extends Factory {
        use ResolvesUserFactory;

        public function definition(): array
        {
            return [];
        }

        public function resolve(): ?Factory
        {
            return $this->resolveUserFactory();
        }
    };
}

test( 'it resolves the configured user model factory when available', function (): void {
    config( ['auth.providers.users.model' => TestUser::class] );

    expect( makeResolvingFactory()->resolve() )->toBeInstanceOf( Factory::class );
} );

test( 'it returns null when the configured user model class does not exist', function (): void {
    config( ['auth.providers.users.model' => 'Definitely\\Missing\\User'] );

    expect( makeResolvingFactory()->resolve() )->toBeNull();
} );

test( 'it returns null when the configured user model is not an eloquent model', function (): void {
    config( ['auth.providers.users.model' => stdClass::class] );

    expect( makeResolvingFactory()->resolve() )->toBeNull();
} );

test( 'it returns null when the configured user model has no factory', function (): void {
    config( ['auth.providers.users.model' => TestPlainContentTypeModel::class] );

    expect( makeResolvingFactory()->resolve() )->toBeNull();
} );

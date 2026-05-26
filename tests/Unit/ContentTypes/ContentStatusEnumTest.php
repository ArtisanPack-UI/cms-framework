<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use Illuminate\Validation\Rules\Enum;

test( 'content status enum has expected cases', function (): void {
    $cases = ContentStatus::cases();

    expect( $cases )->toHaveCount( 4 );
    expect( ContentStatus::Draft->value )->toBe( 'draft' );
    expect( ContentStatus::Published->value )->toBe( 'published' );
    expect( ContentStatus::Scheduled->value )->toBe( 'scheduled' );
    expect( ContentStatus::Private->value )->toBe( 'private' );
} );

test( 'content status enum can be created from string values', function (): void {
    expect( ContentStatus::from( 'draft' ) )->toBe( ContentStatus::Draft );
    expect( ContentStatus::from( 'published' ) )->toBe( ContentStatus::Published );
    expect( ContentStatus::from( 'scheduled' ) )->toBe( ContentStatus::Scheduled );
    expect( ContentStatus::from( 'private' ) )->toBe( ContentStatus::Private );
} );

test( 'content status enum tryFrom returns null for invalid value', function (): void {
    expect( ContentStatus::tryFrom( 'invalid' ) )->toBeNull();
} );

test( 'content status enum label returns translatable string', function (): void {
    app()->setLocale( 'en' );

    expect( ContentStatus::Draft->label() )->toBe( __( 'Draft' ) );
    expect( ContentStatus::Published->label() )->toBe( __( 'Published' ) );
    expect( ContentStatus::Scheduled->label() )->toBe( __( 'Scheduled' ) );
    expect( ContentStatus::Private->label() )->toBe( __( 'Private' ) );
} );

test( 'content status enum validationRule returns enum rule', function (): void {
    $rule = ContentStatus::validationRule();

    expect( $rule )->toBeInstanceOf( Enum::class );
} );

<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ColumnType;
use Illuminate\Validation\Rules\Enum;

test( 'column type enum has expected cases', function (): void {
    $cases = ColumnType::cases();

    expect( $cases )->toHaveCount( 13 );
    expect( ColumnType::String->value )->toBe( 'string' );
    expect( ColumnType::Text->value )->toBe( 'text' );
    expect( ColumnType::Integer->value )->toBe( 'integer' );
    expect( ColumnType::BigInteger->value )->toBe( 'bigInteger' );
    expect( ColumnType::Decimal->value )->toBe( 'decimal' );
    expect( ColumnType::Float->value )->toBe( 'float' );
    expect( ColumnType::Double->value )->toBe( 'double' );
    expect( ColumnType::Boolean->value )->toBe( 'boolean' );
    expect( ColumnType::Date->value )->toBe( 'date' );
    expect( ColumnType::DateTime->value )->toBe( 'dateTime' );
    expect( ColumnType::Time->value )->toBe( 'time' );
    expect( ColumnType::Json->value )->toBe( 'json' );
    expect( ColumnType::Binary->value )->toBe( 'binary' );
} );

test( 'column type enum can be created from string values', function (): void {
    expect( ColumnType::from( 'string' ) )->toBe( ColumnType::String );
    expect( ColumnType::from( 'text' ) )->toBe( ColumnType::Text );
    expect( ColumnType::from( 'integer' ) )->toBe( ColumnType::Integer );
    expect( ColumnType::from( 'bigInteger' ) )->toBe( ColumnType::BigInteger );
    expect( ColumnType::from( 'decimal' ) )->toBe( ColumnType::Decimal );
    expect( ColumnType::from( 'float' ) )->toBe( ColumnType::Float );
    expect( ColumnType::from( 'double' ) )->toBe( ColumnType::Double );
    expect( ColumnType::from( 'boolean' ) )->toBe( ColumnType::Boolean );
    expect( ColumnType::from( 'date' ) )->toBe( ColumnType::Date );
    expect( ColumnType::from( 'dateTime' ) )->toBe( ColumnType::DateTime );
    expect( ColumnType::from( 'time' ) )->toBe( ColumnType::Time );
    expect( ColumnType::from( 'json' ) )->toBe( ColumnType::Json );
    expect( ColumnType::from( 'binary' ) )->toBe( ColumnType::Binary );
} );

test( 'column type enum tryFrom returns null for invalid value', function (): void {
    expect( ColumnType::tryFrom( 'invalid' ) )->toBeNull();
} );

test( 'column type enum label returns translatable string', function (): void {
    app()->setLocale( 'en' );

    expect( ColumnType::String->label() )->toBe( __( 'String' ) );
    expect( ColumnType::Text->label() )->toBe( __( 'Text' ) );
    expect( ColumnType::Integer->label() )->toBe( __( 'Integer' ) );
    expect( ColumnType::BigInteger->label() )->toBe( __( 'Big Integer' ) );
    expect( ColumnType::Decimal->label() )->toBe( __( 'Decimal' ) );
    expect( ColumnType::Float->label() )->toBe( __( 'Float' ) );
    expect( ColumnType::Double->label() )->toBe( __( 'Double' ) );
    expect( ColumnType::Boolean->label() )->toBe( __( 'Boolean' ) );
    expect( ColumnType::Date->label() )->toBe( __( 'Date' ) );
    expect( ColumnType::DateTime->label() )->toBe( __( 'DateTime' ) );
    expect( ColumnType::Time->label() )->toBe( __( 'Time'));
    expect( ColumnType::Json->label())->toBe( __( 'JSON'));
    expect( ColumnType::Binary->label())->toBe( __( 'Binary'));
});

test( 'column type enum validationRule returns enum rule', function (): void {
    $rule = ColumnType::validationRule();

    expect( $rule)->toBeInstanceOf( Enum::class);
});

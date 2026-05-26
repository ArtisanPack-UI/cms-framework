<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\FieldType;
use Illuminate\Validation\Rules\Enum;

test( 'field type enum has expected cases', function (): void {
    $cases = FieldType::cases();

    expect( $cases )->toHaveCount( 16 );
    expect( FieldType::Text->value )->toBe( 'text' );
    expect( FieldType::Textarea->value )->toBe( 'textarea' );
    expect( FieldType::Number->value )->toBe( 'number' );
    expect( FieldType::Select->value )->toBe( 'select' );
    expect( FieldType::Checkbox->value )->toBe( 'checkbox' );
    expect( FieldType::Radio->value )->toBe( 'radio' );
    expect( FieldType::Boolean->value )->toBe( 'boolean' );
    expect( FieldType::Date->value )->toBe( 'date' );
    expect( FieldType::Datetime->value )->toBe( 'datetime' );
    expect( FieldType::Time->value )->toBe( 'time' );
    expect( FieldType::Email->value )->toBe( 'email' );
    expect( FieldType::Url->value )->toBe( 'url' );
    expect( FieldType::Tel->value )->toBe( 'tel' );
    expect( FieldType::Color->value )->toBe( 'color' );
    expect( FieldType::File->value )->toBe( 'file' );
    expect( FieldType::Image->value )->toBe( 'image' );
} );

test( 'field type enum can be created from string values', function (): void {
    expect( FieldType::from( 'text' ) )->toBe( FieldType::Text );
    expect( FieldType::from( 'textarea' ) )->toBe( FieldType::Textarea );
    expect( FieldType::from( 'number' ) )->toBe( FieldType::Number );
    expect( FieldType::from( 'select' ) )->toBe( FieldType::Select );
    expect( FieldType::from( 'checkbox' ) )->toBe( FieldType::Checkbox );
    expect( FieldType::from( 'radio' ) )->toBe( FieldType::Radio );
    expect( FieldType::from( 'boolean' ) )->toBe( FieldType::Boolean );
    expect( FieldType::from( 'date' ) )->toBe( FieldType::Date );
    expect( FieldType::from( 'datetime' ) )->toBe( FieldType::Datetime );
    expect( FieldType::from( 'time' ) )->toBe( FieldType::Time );
    expect( FieldType::from( 'email' ) )->toBe( FieldType::Email );
    expect( FieldType::from( 'url' ) )->toBe( FieldType::Url );
    expect( FieldType::from( 'tel' ) )->toBe( FieldType::Tel );
    expect( FieldType::from( 'color' ) )->toBe( FieldType::Color );
    expect( FieldType::from( 'file' ) )->toBe( FieldType::File );
    expect( FieldType::from( 'image' ) )->toBe( FieldType::Image );
} );

test( 'field type enum tryFrom returns null for invalid value', function (): void {
    expect( FieldType::tryFrom( 'invalid' ) )->toBeNull();
} );

test( 'field type enum label returns translatable string', function (): void {
    app()->setLocale( 'en' );

    expect( FieldType::Text->label() )->toBe( __( 'Text' ) );
    expect( FieldType::Textarea->label() )->toBe( __( 'Textarea' ) );
    expect( FieldType::Number->label() )->toBe( __( 'Number' ) );
    expect( FieldType::Select->label() )->toBe( __( 'Select' ) );
    expect( FieldType::Checkbox->label() )->toBe( __( 'Checkbox' ) );
    expect( FieldType::Radio->label() )->toBe( __( 'Radio' ) );
    expect( FieldType::Boolean->label() )->toBe( __( 'Boolean' ) );
    expect( FieldType::Date->label() )->toBe( __( 'Date' ) );
    expect( FieldType::Datetime->label() )->toBe( __( 'Datetime' ) );
    expect( FieldType::Time->label() )->toBe( __( 'Time' ) );
    expect( FieldType::Email->label() )->toBe( __( 'Email' ) );
    expect( FieldType::Url->label() )->toBe( __( 'URL' ) );
    expect( FieldType::Tel->label() )->toBe( __( 'Telephone'));
    expect( FieldType::Color->label())->toBe( __( 'Color'));
    expect( FieldType::File->label())->toBe( __( 'File'));
    expect( FieldType::Image->label())->toBe( __( 'Image'));
});

test( 'field type enum validationRule returns enum rule', function (): void {
    $rule = FieldType::validationRule();

    expect( $rule)->toBeInstanceOf( Enum::class);
});

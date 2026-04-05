<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;

test('setting type enum has expected cases', function (): void {
    $cases = SettingType::cases();

    expect($cases)->toHaveCount(5);
    expect(SettingType::String->value)->toBe('string');
    expect(SettingType::Integer->value)->toBe('integer');
    expect(SettingType::Boolean->value)->toBe('boolean');
    expect(SettingType::Float->value)->toBe('float');
    expect(SettingType::Json->value)->toBe('json');
});

test('setting type enum can be created from string values', function (): void {
    expect(SettingType::from('string'))->toBe(SettingType::String);
    expect(SettingType::from('integer'))->toBe(SettingType::Integer);
    expect(SettingType::from('boolean'))->toBe(SettingType::Boolean);
    expect(SettingType::from('float'))->toBe(SettingType::Float);
    expect(SettingType::from('json'))->toBe(SettingType::Json);
});

test('setting type enum tryFrom returns null for invalid value', function (): void {
    expect(SettingType::tryFrom('invalid'))->toBeNull();
});

test('fromValue detects boolean type', function (): void {
    expect(SettingType::fromValue(true))->toBe(SettingType::Boolean);
    expect(SettingType::fromValue(false))->toBe(SettingType::Boolean);
});

test('fromValue detects integer type', function (): void {
    expect(SettingType::fromValue(42))->toBe(SettingType::Integer);
    expect(SettingType::fromValue(0))->toBe(SettingType::Integer);
});

test('fromValue detects float type', function (): void {
    expect(SettingType::fromValue(3.14))->toBe(SettingType::Float);
    expect(SettingType::fromValue(0.0))->toBe(SettingType::Float);
});

test('fromValue detects json type for arrays and objects', function (): void {
    expect(SettingType::fromValue(['a' => 1]))->toBe(SettingType::Json);
    expect(SettingType::fromValue((object) ['a' => 1]))->toBe(SettingType::Json);
});

test('fromValue detects string type for strings and null', function (): void {
    expect(SettingType::fromValue('hello'))->toBe(SettingType::String);
    expect(SettingType::fromValue(''))->toBe(SettingType::String);
    expect(SettingType::fromValue(null))->toBe(SettingType::String);
});

test('cast returns correct types', function (): void {
    expect(SettingType::String->cast('hello'))->toBe('hello');
    expect(SettingType::Integer->cast('42'))->toBe(42);
    expect(SettingType::Float->cast('3.14'))->toBe(3.14);
    expect(SettingType::Boolean->cast('1'))->toBeTrue();
    expect(SettingType::Boolean->cast('0'))->toBeFalse();
    expect(SettingType::Json->cast('{"a":1}'))->toBe(['a' => 1]);
});

test('cast handles null values', function (): void {
    expect(SettingType::String->cast(null))->toBe('');
    expect(SettingType::Integer->cast(null))->toBeNull();
    expect(SettingType::Float->cast(null))->toBeNull();
    expect(SettingType::Boolean->cast(null))->toBeNull();
    expect(SettingType::Json->cast(null))->toBeNull();
});

test('serialize converts values to storage strings', function (): void {
    expect(SettingType::String->serialize('hello'))->toBe('hello');
    expect(SettingType::Integer->serialize(42))->toBe('42');
    expect(SettingType::Float->serialize(3.14))->toBe('3.14');
    expect(SettingType::Boolean->serialize(true))->toBe('1');
    expect(SettingType::Boolean->serialize(false))->toBe('0');
    expect(SettingType::Json->serialize(['a' => 1]))->toBe('{"a":1}');
});

test('serialize handles null for string type', function (): void {
    expect(SettingType::String->serialize(null))->toBe('');
});

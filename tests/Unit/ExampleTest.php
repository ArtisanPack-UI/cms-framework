<?php

test('example unit test', function () {
    expect(true)->toBe(true);
});

test('basic math operations', function () {
    expect(1 + 1)->toBe(2);
    expect(5 * 3)->toBe(15);
    expect(10 / 2)->toBe(5);
});

test('string operations', function () {
    expect('hello world')->toContain('world');
    expect('Laravel')->toStartWith('L');
    expect('Framework')->toEndWith('work');
});

test('array operations', function () {
    $array = [1, 2, 3, 4, 5];

    expect($array)->toHaveCount(5);
    expect($array)->toContain(3);
    expect($array)->not()->toContain(6);
});

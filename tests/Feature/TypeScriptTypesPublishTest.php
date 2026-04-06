<?php

declare(strict_types=1);

test('cms-types publish tag is registered', function (): void {
    $tags = Illuminate\Support\ServiceProvider::$publishGroups;

    expect($tags)->toHaveKey('cms-types');
});

test('type definition source files exist', function (string $file): void {
    $path = realpath(__DIR__.'/../../resources/types/'.$file);

    expect($path)->not()->toBeFalse()
        ->and(file_exists($path))->toBeTrue();
})->with([
    'index.d.ts',
    'common.d.ts',
    'blog.d.ts',
    'pages.d.ts',
    'content-types.d.ts',
    'users.d.ts',
    'settings.d.ts',
    'notifications.d.ts',
    'plugins.d.ts',
]);

test('type definition files are publishable to resource path', function (): void {
    $tags = Illuminate\Support\ServiceProvider::$publishGroups;

    $sourceKeys  = array_keys($tags['cms-types']);
    $sourcePath  = $sourceKeys[0];
    $destination = $tags['cms-types'][$sourcePath];

    expect($sourcePath)->toContain('resources/types')
        ->and($destination)->toContain('types/cms-framework');
});

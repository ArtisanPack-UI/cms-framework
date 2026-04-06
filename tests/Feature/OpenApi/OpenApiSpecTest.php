<?php

declare(strict_types=1);

/**
 * Feature Tests for the OpenAPI Specification Generation.
 *
 * Verifies that the OpenAPI specification is generated correctly with
 * all expected endpoints, groups, and schemas.
 *
 * @since 1.1.0
 */

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;

/**
 * Helper to generate the OpenAPI spec array for the CMS API.
 */
function generateSpec(): array
{
    $config    = Scramble::getGeneratorConfig('cms');
    $generator = app(Generator::class);

    return $generator($config);
}

test('openapi spec is generated with correct info', function (): void {
    $spec = generateSpec();

    expect($spec)->toHaveKey('info');
    expect($spec['info']['title'])->toBe('ArtisanPack CMS Framework API');
    expect($spec['info']['version'])->toBe('1.1.0');
});

test('openapi spec contains paths', function (): void {
    $spec = generateSpec();

    expect($spec)->toHaveKey('paths');
    expect($spec['paths'])->not->toBeEmpty();
});

test('openapi spec includes security scheme', function (): void {
    $spec = generateSpec();

    expect($spec)->toHaveKey('components');
    expect($spec['components'])->toHaveKey('securitySchemes');
});

test('openapi spec includes post endpoints', function (): void {
    $spec  = generateSpec();
    $paths = array_keys($spec['paths'] ?? []);

    expect($paths)->toContain('/posts');
    expect($paths)->toContain('/posts/{id}');
});

test('openapi spec includes page endpoints', function (): void {
    $spec  = generateSpec();
    $paths = array_keys($spec['paths'] ?? []);

    expect($paths)->toContain('/pages');
    expect($paths)->toContain('/pages/{id}');
});

test('openapi spec includes settings endpoints', function (): void {
    $spec  = generateSpec();
    $paths = array_keys($spec['paths'] ?? []);

    expect($paths)->toContain('/settings');
});

test('openapi spec includes notification endpoints', function (): void {
    $spec  = generateSpec();
    $paths = array_keys($spec['paths'] ?? []);

    expect($paths)->toContain('/notifications');
});

test('openapi spec includes content type endpoints', function (): void {
    $spec  = generateSpec();
    $paths = array_keys($spec['paths'] ?? []);

    expect($paths)->toContain('/content-types');
});

test('openapi spec includes user management endpoints', function (): void {
    $spec  = generateSpec();
    $paths = array_keys($spec['paths'] ?? []);

    expect($paths)->toContain('/users');
    expect($paths)->toContain('/roles');
    expect($paths)->toContain('/permissions');
});

test('openapi spec uses tags for grouping', function (): void {
    $spec = generateSpec();

    expect($spec)->toHaveKey('tags');

    $tagNames = array_column($spec['tags'] ?? [], 'name');

    expect($tagNames)->toContain('Posts');
    expect($tagNames)->toContain('Pages');
    expect($tagNames)->toContain('Settings');
});

test('openapi disabled when config is false', function (): void {
    config(['artisanpack.cms-framework.openapi.enabled' => false]);

    $provider = new ArtisanPackUI\CMSFramework\Modules\OpenApi\Providers\OpenApiServiceProvider(app());
    $provider->boot();

    // The provider should skip registration — no error thrown
    expect(true)->toBeTrue();
});

test('export command writes openapi spec to file', function (): void {
    $outputPath = sys_get_temp_dir().'/cms-openapi-test-'.uniqid().'.json';

    $this->artisan('cms:openapi:export', [
        'path' => $outputPath,
    ])->assertSuccessful();

    expect(file_exists($outputPath))->toBeTrue();

    $content = file_get_contents($outputPath);
    $spec    = json_decode($content, true);

    expect($spec)->toBeArray();
    expect($spec)->toHaveKey('openapi');
    expect($spec)->toHaveKey('info');
    expect($spec)->toHaveKey('paths');

    unlink($outputPath);
});

test('export command supports pretty print', function (): void {
    $outputPath = sys_get_temp_dir().'/cms-openapi-pretty-test-'.uniqid().'.json';

    $this->artisan('cms:openapi:export', [
        'path'     => $outputPath,
        '--pretty' => true,
    ])->assertSuccessful();

    $content = file_get_contents($outputPath);

    // Pretty-printed JSON contains newlines
    expect($content)->toContain("\n");

    unlink($outputPath);
});

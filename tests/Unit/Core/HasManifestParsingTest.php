<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns\HasManifestParsing;
use Illuminate\Support\Facades\File;

/**
 * Anonymous class that uses the trait so we can test the trait methods directly.
 */
beforeEach(function (): void {
    $this->parser = new class {
        use HasManifestParsing {
            parseManifest as public;
            validateSlug as public;
            resolveSecurePath as public;
        }
    };
});

describe('parseManifest', function (): void {
    it('parses valid JSON manifest', function (): void {
        $tempFile = storage_path('app/test-manifest.json');
        File::put($tempFile, json_encode([
            'slug'    => 'test-package',
            'name'    => 'Test Package',
            'version' => '1.0.0',
        ]));

        $manifest = $this->parser->parseManifest($tempFile);

        expect($manifest)->toBeArray()
            ->and($manifest['slug'])->toBe('test-package')
            ->and($manifest['name'])->toBe('Test Package')
            ->and($manifest['version'])->toBe('1.0.0');

        File::delete($tempFile);
    });

    it('returns null for non-existent manifest', function (): void {
        $manifest = $this->parser->parseManifest('/non/existent/path/manifest.json');

        expect($manifest)->toBeNull();
    });

    it('returns null for invalid JSON', function (): void {
        $tempFile = storage_path('app/invalid-manifest.json');
        File::put($tempFile, '{invalid json content}');

        $manifest = $this->parser->parseManifest($tempFile);

        expect($manifest)->toBeNull();

        File::delete($tempFile);
    });

    it('returns null for empty file', function (): void {
        $tempFile = storage_path('app/empty-manifest.json');
        File::put($tempFile, '');

        $manifest = $this->parser->parseManifest($tempFile);

        expect($manifest)->toBeNull();

        File::delete($tempFile);
    });

    it('handles nested JSON structures', function (): void {
        $tempFile = storage_path('app/nested-manifest.json');
        File::put($tempFile, json_encode([
            'slug'     => 'nested-package',
            'name'     => 'Nested Package',
            'version'  => '2.0.0',
            'autoload' => [
                'psr-4' => [
                    'NestedPackage\\' => 'src/',
                ],
            ],
        ]));

        $manifest = $this->parser->parseManifest($tempFile);

        expect($manifest)->toBeArray()
            ->and($manifest['autoload']['psr-4'])->toBeArray()
            ->and($manifest['autoload']['psr-4']['NestedPackage\\'])->toBe('src/');

        File::delete($tempFile);
    });
});

describe('validateSlug', function (): void {
    it('accepts valid slugs', function (string $slug): void {
        expect($this->parser->validateSlug($slug))->toBeTrue();
    })->with([
        'lowercase'          => 'my-plugin',
        'uppercase'          => 'MyPlugin',
        'mixed case'         => 'My-Plugin',
        'underscores'        => 'my_plugin',
        'hyphens'            => 'my-plugin',
        'numbers'            => 'plugin123',
        'mixed'              => 'my-Plugin_123',
        'single character'   => 'a',
        'all numbers'        => '123',
    ]);

    it('rejects invalid slugs', function (string $slug): void {
        expect($this->parser->validateSlug($slug))->toBeFalse();
    })->with([
        'path traversal'     => '../../../etc/passwd',
        'slashes'            => 'plugin/with/slashes',
        'spaces'             => 'plugin with spaces',
        'special characters' => 'plugin@special',
        'dots'               => 'plugin.name',
        'dot dot'            => '../../vendor',
        'empty string'       => '',
        'backslash'          => 'plugin\\name',
        'semicolon'          => 'plugin;name',
        'pipe'               => 'plugin|name',
    ]);
});

describe('resolveSecurePath', function (): void {
    it('resolves a valid path within base directory', function (): void {
        $basePath = storage_path('app');
        $itemPath = $basePath.'/test-item';

        // Create the directory so realpath works
        File::ensureDirectoryExists($itemPath);

        $result = $this->parser->resolveSecurePath($itemPath, $basePath);

        expect($result)->not->toBeNull()
            ->and($result)->toContain('test-item');

        File::deleteDirectory($itemPath);
    });

    it('returns null for non-existent path', function (): void {
        $basePath = storage_path('app');
        $itemPath = $basePath.'/non-existent-directory';

        $result = $this->parser->resolveSecurePath($itemPath, $basePath);

        expect($result)->toBeNull();
    });

    it('returns null for path traversal attempt', function (): void {
        $basePath = storage_path('app/plugins');
        File::ensureDirectoryExists($basePath);

        // Try to escape the base directory
        $itemPath = $basePath.'/../../';

        $result = $this->parser->resolveSecurePath($itemPath, $basePath);

        expect($result)->toBeNull();

        File::deleteDirectory($basePath);
    });

    it('returns null when base path does not exist', function (): void {
        $basePath = storage_path('app/non-existent-base');
        $itemPath = $basePath.'/some-item';

        $result = $this->parser->resolveSecurePath($itemPath, $basePath);

        expect($result)->toBeNull();
    });
});

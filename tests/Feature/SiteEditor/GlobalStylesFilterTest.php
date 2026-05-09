<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Providers\SiteEditorServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->themesPath = base_path('themes');
    $this->themeSlug  = 'test-theme';
    $this->themeRoot  = $this->themesPath.'/'.$this->themeSlug;

    File::ensureDirectoryExists($this->themeRoot);
    File::put($this->themeRoot.'/theme.json', json_encode([
        'name'    => 'Test',
        'slug'    => $this->themeSlug,
        'version' => '1.0.0',
    ]));

    config()->set('cms.themes.cacheEnabled', false);

    $this->mock(ThemeManager::class, function ($mock): void {
        $mock->shouldReceive('getActiveTheme')->andReturn([
            'name'     => 'Test',
            'slug'     => $this->themeSlug,
            'settings' => ['color' => ['palette' => [['slug' => 'primary', 'color' => '#000000']]]],
            'styles'   => ['color' => ['background' => '#ffffff']],
        ]);
    });

    require_once __DIR__.'/../../Support/VisualEditorClassStub.php';

    removeAllFilters('ap.visual-editor.global-styles');

    (new SiteEditorServiceProvider(app()))->registerVisualEditorSiteEditorFilters();
});

afterEach(function (): void {
    File::deleteDirectory($this->themeRoot);
    removeAllFilters('ap.visual-editor.global-styles');
});

describe('ap.visual-editor.global-styles filter wiring', function (): void {
    it('returns the resolved global styles entry as a singleton (file-only)', function (): void {
        $entry = applyFilters('ap.visual-editor.global-styles', null);

        expect($entry)->toBeArray()
            ->and($entry)->toHaveKeys([
                'theme',
                'settings',
                'styles',
                'variation',
                'has_user_customization',
                'wp_id',
                'content_hash',
            ])
            ->and($entry['theme'])->toBe($this->themeSlug)
            ->and($entry['has_user_customization'])->toBeFalse()
            ->and($entry['wp_id'])->toBe(0);
    });

    it('reflects DB customization in the filter entry', function (): void {
        GlobalStyles::create([
            'theme'  => $this->themeSlug,
            'styles' => ['color' => ['background' => '#abcdef']],
        ]);

        $entry = applyFilters('ap.visual-editor.global-styles', null);

        expect($entry['has_user_customization'])->toBeTrue()
            ->and($entry['styles']['color']['background'])->toBe('#abcdef')
            ->and($entry['wp_id'])->toBeInt()->toBeGreaterThan(0);
    });

    it('preserves the prior filter value when there is no active theme', function (): void {
        $this->mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('getActiveTheme')->andReturn(null);
        });

        // Re-register against the new mock.
        removeAllFilters('ap.visual-editor.global-styles');
        (new SiteEditorServiceProvider(app()))->registerVisualEditorSiteEditorFilters();

        $entry = applyFilters('ap.visual-editor.global-styles', null);

        expect($entry)->toBeNull();
    });
});

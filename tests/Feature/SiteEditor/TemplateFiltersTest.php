<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Providers\SiteEditorServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->themesPath  = base_path('themes');
    $this->themeSlug   = 'test-theme';
    $this->templates   = $this->themesPath.'/'.$this->themeSlug.'/templates';
    $this->parts       = $this->themesPath.'/'.$this->themeSlug.'/parts';

    File::ensureDirectoryExists($this->templates);
    File::ensureDirectoryExists($this->parts);
    File::put(
        $this->themesPath.'/'.$this->themeSlug.'/theme.json',
        json_encode(['name' => 'Test', 'slug' => $this->themeSlug, 'version' => '1.0.0']),
    );

    config()->set('cms.themes.cacheEnabled', false);

    $this->mock(ThemeManager::class, function ($mock): void {
        $mock->shouldReceive('getActiveTheme')->andReturn([
            'name' => 'Test',
            'slug' => $this->themeSlug,
        ]);
    });

    // Filters are registered behind `class_exists(VisualEditor::class)` at
    // SiteEditorServiceProvider::boot(); visual-editor isn't a dev dep of
    // cms-framework, so the gate is false at boot. Load the stub and manually
    // re-trigger registration. Mirrors PatternsApiTest's filter wiring suite
    // and VisualEditorBridgeTest's pattern.
    require_once __DIR__.'/../../Support/VisualEditorClassStub.php';

    removeAllFilters('ap.visual-editor.templates');
    removeAllFilters('ap.visual-editor.template-parts');

    (new SiteEditorServiceProvider(app()))->registerVisualEditorSiteEditorFilters();
});

afterEach(function (): void {
    File::deleteDirectory($this->themesPath.'/'.$this->themeSlug);
    removeAllFilters('ap.visual-editor.templates');
    removeAllFilters('ap.visual-editor.template-parts');
});

describe('ap.visual-editor.templates filter wiring', function (): void {
    it('merges file + DB templates into the filter map keyed by slug', function (): void {
        File::put($this->templates.'/single.html', '<!-- file -->');
        Template::create([
            'theme'         => $this->themeSlug,
            'slug'          => 'archive',
            'title'         => 'Custom Archive',
            'is_custom'     => true,
            'block_content' => [['blockName' => 'core/heading']],
        ]);

        $merged = applyFilters('ap.visual-editor.templates', []);

        expect($merged)->toHaveKey('single')
            ->and($merged)->toHaveKey('archive')
            ->and($merged['single']['source'])->toBe('theme')
            ->and($merged['archive']['source'])->toBe('db')
            ->and($merged['archive']['blocks'])->toBe([['blockName' => 'core/heading']]);
    });

    it('emits the contract fields visual-editor ResolvedTemplate::fromArray expects', function (): void {
        File::put($this->templates.'/page.html', '<!-- wp:paragraph -->');

        $merged = applyFilters('ap.visual-editor.templates', []);

        expect($merged['page'])->toHaveKeys([
            'slug',
            'theme',
            'title',
            'description',
            'status',
            'source',
            'raw_content',
            'blocks',
            'has_theme_file',
            'is_custom',
            'wp_id',
        ]);
    });

    it('lets static config / earlier contributors win on slug collision', function (): void {
        File::put($this->templates.'/page.html', '<!-- file -->');

        $merged = applyFilters('ap.visual-editor.templates', [
            'page' => ['slug' => 'page', 'theme' => 'override', 'source' => 'theme'],
        ]);

        expect($merged['page']['theme'])->toBe('override');
    });
});

describe('ap.visual-editor.template-parts filter wiring', function (): void {
    it('surfaces an `area` field on each entry (header / footer / sidebar / general)', function (): void {
        File::put($this->parts.'/header.html', '<!-- header -->');
        File::put($this->parts.'/footer.html', '<!-- footer -->');
        TemplatePart::create([
            'theme' => $this->themeSlug,
            'slug'  => 'sidebar-menu',
            'title' => 'Sidebar Menu',
            'area'  => 'sidebar',
        ]);

        $merged = applyFilters('ap.visual-editor.template-parts', []);

        expect($merged)->toHaveKey('header')
            ->and($merged)->toHaveKey('footer')
            ->and($merged)->toHaveKey('sidebar-menu')
            ->and($merged['header']['area'])->toBe('header')
            ->and($merged['footer']['area'])->toBe('footer')
            ->and($merged['sidebar-menu']['area'])->toBe('sidebar');
    });

    it('is independent of the templates filter (different keyspace)', function (): void {
        File::put($this->templates.'/page.html', '<!-- template -->');
        File::put($this->parts.'/header.html', '<!-- part -->');

        $templates = applyFilters('ap.visual-editor.templates', []);
        $parts     = applyFilters('ap.visual-editor.template-parts', []);

        expect($templates)->toHaveKey('page')
            ->and($templates)->not->toHaveKey('header')
            ->and($parts)->toHaveKey('header')
            ->and($parts)->not->toHaveKey('page');
    });
});

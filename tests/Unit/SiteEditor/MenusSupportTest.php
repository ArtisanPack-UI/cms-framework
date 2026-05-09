<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\Menus;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->themeSlug = 'test-theme';

    config()->set('cms.menus.locations', [
        'primary' => 'Primary Menu',
        'footer'  => 'Footer Menu',
    ]);
});

describe('Menus::locations()', function (): void {
    it('returns app config defaults when there is no active theme', function (): void {
        $this->mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('getActiveTheme')->andReturn(null);
        });

        expect(Menus::locations())->toBe([
            'primary' => 'Primary Menu',
            'footer'  => 'Footer Menu',
        ]);
    });

    it('merges theme.json overrides on top of app defaults — theme wins on collision', function (): void {
        $this->mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('getActiveTheme')->andReturn([
                'name'  => 'Test',
                'slug'  => $this->themeSlug,
                'menus' => [
                    'locations' => [
                        'primary' => 'Theme Primary',
                        'sidebar' => 'Sidebar',
                    ],
                ],
            ]);
        });

        $resolved = Menus::locations();

        expect($resolved)->toBe([
            'primary' => 'Theme Primary',
            'footer'  => 'Footer Menu',
            'sidebar' => 'Sidebar',
        ]);
    });

    it('logs a warning when the theme overrides an app-config location key', function (): void {
        $this->mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('getActiveTheme')->andReturn([
                'name'  => 'Test',
                'slug'  => $this->themeSlug,
                'menus' => [
                    'locations' => ['primary' => 'Theme Primary'],
                ],
            ]);
        });

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Theme `theme.json` menus.locations key overrides app config(\'cms.menus.locations\').',
                Mockery::on(static function (array $context): bool {
                    return 'primary' === $context['location']
                        && 'Primary Menu' === $context['app_label'];
                }),
            );

        Menus::locations();
    });

    it('does not warn when theme adds a key the app does not provide', function (): void {
        $this->mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('getActiveTheme')->andReturn([
                'slug'  => $this->themeSlug,
                'menus' => [
                    'locations' => ['sidebar' => 'Sidebar'],
                ],
            ]);
        });

        Log::shouldReceive('warning')->never();

        $resolved = Menus::locations();

        expect($resolved)->toHaveKey('sidebar', 'Sidebar');
    });

    it('ignores malformed locations entries (non-string keys or values)', function (): void {
        $this->mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('getActiveTheme')->andReturn([
                'slug'  => $this->themeSlug,
                'menus' => [
                    'locations' => [
                        ''        => 'Empty Key',
                        'sidebar' => null,
                        'banner'  => 42,
                    ],
                ],
            ]);
        });

        $resolved = Menus::locations();

        expect($resolved)->toHaveKey('banner', '42')
            ->and($resolved)->not->toHaveKey('')
            ->and($resolved)->not->toHaveKey('sidebar');
    });
});

describe('Menus::assign() / unassign() / assigned()', function (): void {
    beforeEach(function (): void {
        $this->mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('getActiveTheme')->andReturn([
                'slug' => $this->themeSlug,
            ]);
        });

        $this->menu = Menu::create([
            'theme' => $this->themeSlug,
            'slug'  => 'main',
            'name'  => 'Main',
        ]);
    });

    it('creates an assignment row on first assign', function (): void {
        Menus::assign('primary', $this->menu->id);

        $row = MenuLocationAssignment::query()->where('theme', $this->themeSlug)->where('location', 'primary')->first();

        expect($row)->not->toBeNull()
            ->and((int) $row->menu_id)->toBe($this->menu->id);
    });

    it('reassigns by upserting the row in place', function (): void {
        $other = Menu::create([
            'theme' => $this->themeSlug,
            'slug'  => 'alt',
            'name'  => 'Alt',
        ]);

        Menus::assign('primary', $this->menu->id);
        Menus::assign('primary', $other->id);

        expect(MenuLocationAssignment::query()->where('theme', $this->themeSlug)->where('location', 'primary')->count())->toBe(1)
            ->and(Menus::assigned('primary')?->id)->toBe($other->id);
    });

    it('unassign removes the row and returns true', function (): void {
        Menus::assign('primary', $this->menu->id);

        expect(Menus::unassign('primary'))->toBeTrue()
            ->and(Menus::assigned('primary'))->toBeNull();
    });

    it('unassign returns false when nothing was assigned', function (): void {
        expect(Menus::unassign('primary'))->toBeFalse();
    });

    it('assigned returns the menu model when assigned', function (): void {
        Menus::assign('primary', $this->menu->id);

        expect(Menus::assigned('primary')?->id)->toBe($this->menu->id);
    });
});

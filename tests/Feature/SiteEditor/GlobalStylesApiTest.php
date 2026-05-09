<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->user        = TestUser::factory()->create();
    $this->themesPath  = base_path('themes');
    $this->themeSlug   = 'test-theme';
    $this->themeRoot   = $this->themesPath.'/'.$this->themeSlug;
    $this->stylesDir   = $this->themeRoot.'/styles';

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
            'settings' => [
                'color' => [
                    'palette' => [['slug' => 'primary', 'color' => '#000000']],
                ],
            ],
            'styles' => [
                'color' => ['background' => '#ffffff'],
            ],
        ]);
    });
});

afterEach(function (): void {
    File::deleteDirectory($this->themeRoot);
});

describe('GET /api/v1/global-styles', function (): void {
    it('requires authentication', function (): void {
        $this->getJson('/api/v1/global-styles')->assertUnauthorized();
    });

    it('returns the resolved styles in WP shape with no DB row', function (): void {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/global-styles');

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'theme',
            'settings',
            'styles',
            'variation',
            'has_user_customization',
            'content_hash',
        ]);

        expect($response->json('theme'))->toBe($this->themeSlug)
            ->and($response->json('has_user_customization'))->toBeFalse()
            ->and($response->json('id'))->toBe(0)
            ->and($response->json('styles.color.background'))->toBe('#ffffff');
    });

    it('reflects DB customization when present', function (): void {
        GlobalStyles::create([
            'theme'  => $this->themeSlug,
            'styles' => ['color' => ['background' => '#000000']],
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/global-styles');

        $response->assertOk();
        expect($response->json('has_user_customization'))->toBeTrue()
            ->and($response->json('styles.color.background'))->toBe('#000000')
            ->and($response->json('id'))->toBeInt()->toBeGreaterThan(0);
    });
});

describe('PUT /api/v1/global-styles', function (): void {
    it('creates the DB row on first call and updates on subsequent calls', function (): void {
        $this->actingAs($this->user);

        $first = $this->putJson('/api/v1/global-styles', [
            'styles' => ['color' => ['background' => '#abcdef']],
        ]);

        $first->assertOk();
        expect(GlobalStyles::query()->count())->toBe(1);

        $second = $this->putJson('/api/v1/global-styles', [
            'styles' => ['color' => ['background' => '#fedcba']],
        ]);

        $second->assertOk();
        expect(GlobalStyles::query()->count())->toBe(1)
            ->and($second->json('styles.color.background'))->toBe('#fedcba');
    });

    it('rejects an invalid variation slug', function (): void {
        $this->actingAs($this->user);

        $this->putJson('/api/v1/global-styles', [
            'variation' => '../traversal',
        ])->assertUnprocessable();
    });

    it('allows PUT { variation: null } to clear a stored variation', function (): void {
        GlobalStyles::create([
            'theme'     => $this->themeSlug,
            'variation' => 'dark',
            'styles'    => ['color' => ['background' => '#000000']],
        ]);

        $this->actingAs($this->user);

        $this->putJson('/api/v1/global-styles', ['variation' => null])->assertOk();

        expect(GlobalStyles::query()->where('theme', $this->themeSlug)->first()->variation)->toBeNull();
    });
});

describe('DELETE /api/v1/global-styles', function (): void {
    it('deletes the DB row and restores file-only resolution', function (): void {
        GlobalStyles::create([
            'theme'  => $this->themeSlug,
            'styles' => ['color' => ['background' => '#000000']],
        ]);

        $this->actingAs($this->user);

        $response = $this->deleteJson('/api/v1/global-styles');

        $response->assertOk();
        expect(GlobalStyles::query()->count())->toBe(0)
            ->and($response->json('has_user_customization'))->toBeFalse()
            ->and($response->json('styles.color.background'))->toBe('#ffffff');
    });

    it('returns 404 when there is no DB row to delete', function (): void {
        $this->actingAs($this->user);

        $this->deleteJson('/api/v1/global-styles')->assertNotFound();
    });

    it('returns 409 when no theme is active', function (): void {
        $this->mock(ThemeManager::class, function ($mock): void {
            $mock->shouldReceive('getActiveTheme')->andReturn(null);
        });

        $this->actingAs($this->user);

        $this->deleteJson('/api/v1/global-styles')
            ->assertStatus(409)
            ->assertJson(['message' => 'No active theme.']);
    });
});

describe('GET /api/v1/global-styles/variations', function (): void {
    it('returns variations declared in the active theme styles directory', function (): void {
        File::ensureDirectoryExists($this->stylesDir);
        File::put($this->stylesDir.'/dark.json', json_encode([
            'slug'   => 'dark',
            'title'  => 'Dark',
            'styles' => ['color' => ['background' => '#111111']],
        ]));

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/global-styles/variations');

        $response->assertOk();
        expect($response->json())->toHaveCount(1)
            ->and($response->json('0.slug'))->toBe('dark')
            ->and($response->json('0.title'))->toBe('Dark');
    });
});

describe('GET /api/v1/global-styles/css', function (): void {
    it('emits CSS with the expected content type', function (): void {
        GlobalStyles::create([
            'theme'  => $this->themeSlug,
            'styles' => ['color' => ['background' => '#abcdef']],
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/api/v1/global-styles/css');

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('text/css');
        expect($response->getContent())->toContain('background-color: #abcdef;');
    });
});

describe('theme isolation', function (): void {
    it('scopes DB rows to the active theme', function (): void {
        GlobalStyles::create([
            'theme'  => 'other-theme',
            'styles' => ['color' => ['background' => '#dead00']],
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/global-styles');

        $response->assertOk();
        expect($response->json('has_user_customization'))->toBeFalse()
            ->and($response->json('styles.color.background'))->toBe('#ffffff');
    });
});

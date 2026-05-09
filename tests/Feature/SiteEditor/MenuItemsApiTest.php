<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach(function (): void {
    $this->user      = TestUser::factory()->create();
    $this->themeSlug = 'test-theme';

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

describe('GET /api/v1/menu-items', function (): void {
    it('requires authentication', function (): void {
        $this->getJson('/api/v1/menu-items')->assertUnauthorized();
    });

    it('orders results by (parent_id, position)', function (): void {
        $about = MenuItem::create([
            'menu_id'  => $this->menu->id,
            'position' => 1,
            'type'     => MenuItem::TYPE_SUBMENU,
            'label'    => 'About',
        ]);

        // Created out of order on purpose to verify ordering.
        MenuItem::create([
            'menu_id'   => $this->menu->id,
            'parent_id' => $about->id,
            'position'  => 0,
            'type'      => MenuItem::TYPE_LINK,
            'label'     => 'Team',
        ]);

        MenuItem::create([
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Home',
            'url'      => '/',
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/menu-items?menus='.$this->menu->id);

        $response->assertOk();

        $labels = collect($response->json())->pluck('title.raw')->all();

        // Top-level (parent_id=null) sorted to the front by position; then
        // children sorted under by position.
        expect($labels)->toBe(['Home', 'About', 'Team']);
    });

    it('rejects non-numeric ?menus filters', function (): void {
        $this->actingAs($this->user);

        $this->getJson('/api/v1/menu-items?menus=abc')->assertStatus(422);
    });

    it('rejects scientific-notation ?menus filters that pass is_numeric', function (): void {
        $this->actingAs($this->user);

        // `is_numeric("1e3")` is true but `(int) "1e3"` is 1, not 1000.
        // The strict positive-integer-string check rejects it.
        $this->getJson('/api/v1/menu-items?menus=1e3')->assertStatus(422);
    });

    it('rejects ?menus=0 (menu ids are 1-based)', function (): void {
        $this->actingAs($this->user);

        $this->getJson('/api/v1/menu-items?menus=0')->assertStatus(422);
    });

    it('rejects leading-zero ?menus filters that would silently miscast', function (): void {
        $this->actingAs($this->user);

        // `(int) "01"` is 1, masking the client's intent. Surface as 422
        // instead of silently coercing.
        $this->getJson('/api/v1/menu-items?menus=01')->assertStatus(422);
    });

    it('does not surface items from menus in other themes', function (): void {
        $foreign = Menu::create([
            'theme' => 'other-theme',
            'slug'  => 'foreign',
            'name'  => 'Foreign',
        ]);

        MenuItem::create([
            'menu_id'  => $foreign->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Foreign Item',
        ]);

        MenuItem::create([
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Local Item',
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/menu-items');

        $response->assertOk();

        $labels = collect($response->json())->pluck('title.raw')->all();

        expect($labels)->toBe(['Local Item']);
    });

    it('tiebreaks equal positions by id for deterministic order', function (): void {
        // Two siblings with the same `position` — without the `id` tiebreaker
        // the relative order would be implementation-defined.
        $first = MenuItem::create([
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'First',
        ]);

        $second = MenuItem::create([
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Second',
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/menu-items?menus='.$this->menu->id);

        $ids = collect($response->json())->pluck('id')->all();

        expect($ids)->toBe([$first->id, $second->id]);
    });
});

describe('POST /api/v1/menu-items', function (): void {
    it('creates a link item', function (): void {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/menu-items', [
            'menus' => $this->menu->id,
            'title' => 'Home',
            'url'   => '/',
            'type'  => MenuItem::TYPE_LINK,
        ]);

        $response->assertCreated();
        expect($response->json('title.raw'))->toBe('Home')
            ->and($response->json('menus'))->toBe($this->menu->id)
            ->and($response->json('link_type'))->toBe(MenuItem::TYPE_LINK);
    });

    it('rejects an invalid link type', function (): void {
        $this->actingAs($this->user);

        $this->postJson('/api/v1/menu-items', [
            'menus' => $this->menu->id,
            'title' => 'Home',
            'type'  => 'invalid',
        ])->assertStatus(422);
    });

    it('rejects unpaired (object, object_id) fields', function (): void {
        $this->actingAs($this->user);

        $this->postJson('/api/v1/menu-items', [
            'menus'  => $this->menu->id,
            'title'  => 'Post Link',
            'type'   => MenuItem::TYPE_LINK,
            'object' => 'post',
        ])->assertStatus(422);
    });

    it('rejects parent referencing an item in a different menu', function (): void {
        $other = Menu::create([
            'theme' => $this->themeSlug,
            'slug'  => 'other',
            'name'  => 'Other',
        ]);

        $alien = MenuItem::create([
            'menu_id'  => $other->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Alien',
        ]);

        $this->actingAs($this->user);

        $this->postJson('/api/v1/menu-items', [
            'menus'  => $this->menu->id,
            'title'  => 'Bad Parent',
            'type'   => MenuItem::TYPE_LINK,
            'parent' => $alien->id,
        ])->assertStatus(422);
    });

    it('rejects items targeting menus in other themes (validation layer)', function (): void {
        $foreign = Menu::create([
            'theme' => 'other-theme',
            'slug'  => 'foreign',
            'name'  => 'Foreign',
        ]);

        $this->actingAs($this->user);

        // Foreign-theme menus and truly-nonexistent menus both yield 422
        // through the theme-scoped `exists` rule on the `menus` field —
        // unifying the response so existence in other themes isn't
        // distinguishable from non-existence.
        $this->postJson('/api/v1/menu-items', [
            'menus' => $foreign->id,
            'title' => 'Sneaky',
            'type'  => MenuItem::TYPE_LINK,
        ])->assertJsonValidationErrors(['menus']);
    });

    it('returns the same 422 for a truly-nonexistent menus id', function (): void {
        $this->actingAs($this->user);

        $this->postJson('/api/v1/menu-items', [
            'menus' => 999_999,
            'title' => 'Item',
            'type'  => MenuItem::TYPE_LINK,
        ])->assertJsonValidationErrors(['menus']);
    });

    it('rejects parent referencing an item in another theme', function (): void {
        $foreign = Menu::create([
            'theme' => 'other-theme',
            'slug'  => 'foreign',
            'name'  => 'Foreign',
        ]);

        $alien = MenuItem::create([
            'menu_id'  => $foreign->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Alien',
        ]);

        $this->actingAs($this->user);

        // Same theme-scoped exists fix on the `parent` field.
        $this->postJson('/api/v1/menu-items', [
            'menus'  => $this->menu->id,
            'title'  => 'Bad Parent',
            'type'   => MenuItem::TYPE_LINK,
            'parent' => $alien->id,
        ])->assertJsonValidationErrors(['parent']);
    });

    it('normalizes WP sentinel parent=0 to a root item', function (): void {
        $this->actingAs($this->user);

        // WP REST sends `parent: 0` for top-level items. Without the
        // prepareForValidation() normalization, this would fail the
        // `exists:menu_items,id` rule (no item has id 0).
        $response = $this->postJson('/api/v1/menu-items', [
            'menus'  => $this->menu->id,
            'title'  => 'Root',
            'type'   => MenuItem::TYPE_LINK,
            'parent' => 0,
        ]);

        $response->assertCreated();
        expect($response->json('parent'))->toBe(0);
    });

    it('normalizes WP sentinel object="" + object_id=0 (custom-link payload)', function (): void {
        $this->actingAs($this->user);

        // WP REST sends `object: ""` and `object_id: 0` for custom links.
        // Without prepareForValidation() the pairing rule would fire
        // because `0` is "filled" but `""` is not.
        $response = $this->postJson('/api/v1/menu-items', [
            'menus'     => $this->menu->id,
            'title'     => 'Custom',
            'type'      => MenuItem::TYPE_LINK,
            'object'    => '',
            'object_id' => 0,
        ]);

        $response->assertCreated();
        expect($response->json('object'))->toBe('')
            ->and($response->json('object_id'))->toBe(0);
    });

    it('rejects an unknown kind value', function (): void {
        $this->actingAs($this->user);

        $this->postJson('/api/v1/menu-items', [
            'menus' => $this->menu->id,
            'title' => 'Item',
            'type'  => MenuItem::TYPE_LINK,
            'kind'  => 'unknown-kind',
        ])->assertStatus(422);
    });

    it('accepts known kind values', function (): void {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/menu-items', [
            'menus' => $this->menu->id,
            'title' => 'Post Link',
            'type'  => MenuItem::TYPE_LINK,
            'kind'  => 'post-type',
        ]);

        $response->assertCreated();
        expect($response->json('type'))->toBe('post_type');
    });

    it('normalizes whitespace in classes and xfn for the WP-shape response', function (): void {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/menu-items', [
            'menus'   => $this->menu->id,
            'title'   => 'Item',
            'type'    => MenuItem::TYPE_LINK,
            'classes' => 'foo  bar   baz',
            'xfn'     => '  nofollow   noopener  ',
        ]);

        $response->assertCreated();
        expect($response->json('classes'))->toBe(['foo', 'bar', 'baz'])
            ->and($response->json('xfn'))->toBe(['nofollow', 'noopener']);
    });
});

describe('PUT /api/v1/menu-items/{id}', function (): void {
    it('updates fields without reassigning the parent menu', function (): void {
        $item = MenuItem::create([
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Old',
            'url'      => '/old',
        ]);

        $this->actingAs($this->user);

        $response = $this->putJson('/api/v1/menu-items/'.$item->id, [
            'title' => 'New',
            'url'   => '/new',
        ]);

        $response->assertOk();
        expect($response->json('title.raw'))->toBe('New')
            ->and($response->json('url'))->toBe('/new');
    });

    it('prohibits the menus field on update', function (): void {
        $item = MenuItem::create([
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Item',
        ]);

        $this->actingAs($this->user);

        $this->putJson('/api/v1/menu-items/'.$item->id, [
            'menus' => $this->menu->id,
            'title' => 'Item',
        ])->assertStatus(422);
    });
});

describe('DELETE /api/v1/menu-items/{id}', function (): void {
    it('cascades to children', function (): void {
        $parent = MenuItem::create([
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_SUBMENU,
            'label'    => 'Parent',
        ]);

        $child = MenuItem::create([
            'menu_id'   => $this->menu->id,
            'parent_id' => $parent->id,
            'position'  => 0,
            'type'      => MenuItem::TYPE_LINK,
            'label'     => 'Child',
        ]);

        $this->actingAs($this->user);

        $this->deleteJson('/api/v1/menu-items/'.$parent->id)->assertNoContent();

        expect(MenuItem::query()->find($parent->id))->toBeNull()
            ->and(MenuItem::query()->find($child->id))->toBeNull();
    });
});

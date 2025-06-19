<?php

use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesManager;
use Illuminate\Support\Facades\Route;
use TorMorten\Eventy\Facades\Eventy;

it('can register a page', function () {
    $manager = new AdminPagesManager();
    
    $manager->registerPage(
        'Test Page',
        'test-page',
        'icon-test',
        'test.view',
        null,
        'test.permission'
    );
    
    $menuItems = $manager->getMenuItems();
    
    expect($menuItems)->toHaveKey('test-page');
    expect($menuItems['test-page']['title'])->toBe('Test Page');
    expect($menuItems['test-page']['slug'])->toBe('test-page');
    expect($menuItems['test-page']['icon'])->toBe('icon-test');
    expect($menuItems['test-page']['view'])->toBe('test.view');
    expect($menuItems['test-page']['component'])->toBeNull();
    expect($menuItems['test-page']['permission'])->toBe('test.permission');
    expect($menuItems['test-page']['subpages'])->toBeArray();
    expect($menuItems['test-page']['subpages'])->toBeEmpty();
});

it('can register a page with a component', function () {
    $manager = new AdminPagesManager();
    
    $manager->registerPage(
        'Test Page',
        'test-page',
        'icon-test',
        null,
        'App\\Http\\Livewire\\TestComponent',
        'test.permission'
    );
    
    $menuItems = $manager->getMenuItems();
    
    expect($menuItems)->toHaveKey('test-page');
    expect($menuItems['test-page']['component'])->toBe('App\\Http\\Livewire\\TestComponent');
    expect($menuItems['test-page']['view'])->toBeNull();
});

it('can register a subpage', function () {
    $manager = new AdminPagesManager();
    
    // Register parent page first
    $manager->registerPage(
        'Test Page',
        'test-page',
        'icon-test'
    );
    
    // Register subpage
    $manager->registerSubPage(
        'test-page',
        'Test Subpage',
        'test-subpage',
        'test.subpage.view',
        null,
        'test.subpage.permission'
    );
    
    $menuItems = $manager->getMenuItems();
    
    expect($menuItems['test-page']['subpages'])->toHaveKey('test-subpage');
    expect($menuItems['test-page']['subpages']['test-subpage']['title'])->toBe('Test Subpage');
    expect($menuItems['test-page']['subpages']['test-subpage']['slug'])->toBe('test-subpage');
    expect($menuItems['test-page']['subpages']['test-subpage']['view'])->toBe('test.subpage.view');
    expect($menuItems['test-page']['subpages']['test-subpage']['component'])->toBeNull();
    expect($menuItems['test-page']['subpages']['test-subpage']['permission'])->toBe('test.subpage.permission');
});

it('can register a subpage with a component', function () {
    $manager = new AdminPagesManager();
    
    // Register parent page first
    $manager->registerPage(
        'Test Page',
        'test-page',
        'icon-test'
    );
    
    // Register subpage with component
    $manager->registerSubPage(
        'test-page',
        'Test Subpage',
        'test-subpage',
        null,
        'App\\Http\\Livewire\\TestSubpageComponent',
        'test.subpage.permission'
    );
    
    $menuItems = $manager->getMenuItems();
    
    expect($menuItems['test-page']['subpages']['test-subpage']['component'])
        ->toBe('App\\Http\\Livewire\\TestSubpageComponent');
    expect($menuItems['test-page']['subpages']['test-subpage']['view'])->toBeNull();
});

it('ignores subpage registration for non-existent parent', function () {
    $manager = new AdminPagesManager();
    
    // Try to register a subpage for a non-existent parent
    $manager->registerSubPage(
        'non-existent-page',
        'Test Subpage',
        'test-subpage',
        'test.subpage.view'
    );
    
    $menuItems = $manager->getMenuItems();
    
    expect($menuItems)->toBeEmpty();
});

it('filters menu items through Eventy', function () {
    $manager = new AdminPagesManager();
    
    $manager->registerPage(
        'Test Page',
        'test-page',
        'icon-test'
    );
    
    // Mock Eventy::filter to modify the menu items
    $modifiedMenuItems = [
        'test-page' => [
            'title' => 'Modified Test Page',
            'slug' => 'test-page',
            'icon' => 'icon-test',
            'view' => null,
            'component' => null,
            'permission' => null,
            'subpages' => [],
        ],
        'new-page' => [
            'title' => 'New Page',
            'slug' => 'new-page',
            'icon' => 'icon-new',
            'view' => 'new.view',
            'component' => null,
            'permission' => null,
            'subpages' => [],
        ],
    ];
    
    Eventy::shouldReceive('filter')
        ->with('ap.cms.admin.menuItems', Mockery::any())
        ->once()
        ->andReturn($modifiedMenuItems);
    
    $menuItems = $manager->getMenuItems();
    
    expect($menuItems)->toHaveKey('test-page');
    expect($menuItems)->toHaveKey('new-page');
    expect($menuItems['test-page']['title'])->toBe('Modified Test Page');
    expect($menuItems['new-page']['title'])->toBe('New Page');
});

it('registers routes for pages and subpages', function () {
    $manager = new AdminPagesManager();
    
    // Register pages
    $manager->registerPage(
        'View Page',
        'view-page',
        'icon-view',
        'test.view'
    );
    
    $manager->registerPage(
        'Component Page',
        'component-page',
        'icon-component',
        null,
        'App\\Http\\Livewire\\TestComponent'
    );
    
    $manager->registerSubPage(
        'view-page',
        'View Subpage',
        'view-subpage',
        'test.subpage.view'
    );
    
    $manager->registerSubPage(
        'component-page',
        'Component Subpage',
        'component-subpage',
        null,
        'App\\Http\\Livewire\\TestSubpageComponent'
    );
    
    // Mock config to return admin path
    config(['cms.admin_path' => 'admin']);
    
    // Mock Route facade
    Route::shouldReceive('group')
        ->times(4) // 2 pages + 2 subpages
        ->andReturnUsing(function ($attributes, $callback) {
            $callback();
            return Mockery::mock('Illuminate\Routing\RouteRegistrar');
        });
    
    // For view-based page
    Route::shouldReceive('get')
        ->with('view-page', Mockery::type('Closure'))
        ->once()
        ->andReturn(Mockery::mock('Illuminate\Routing\Route')
            ->shouldReceive('name')
            ->with('admin.view-page')
            ->once()
            ->getMock());
    
    // For component-based page
    Route::shouldReceive('get')
        ->with('component-page', 'App\\Http\\Livewire\\TestComponent')
        ->once()
        ->andReturn(Mockery::mock('Illuminate\Routing\Route')
            ->shouldReceive('name')
            ->with('admin.component-page')
            ->once()
            ->getMock());
    
    // For view-based subpage
    Route::shouldReceive('get')
        ->with('view-subpage', Mockery::type('Closure'))
        ->once()
        ->andReturn(Mockery::mock('Illuminate\Routing\Route')
            ->shouldReceive('name')
            ->with('admin.view-page.view-subpage')
            ->once()
            ->getMock());
    
    // For component-based subpage
    Route::shouldReceive('get')
        ->with('component-subpage', 'App\\Http\\Livewire\\TestSubpageComponent')
        ->once()
        ->andReturn(Mockery::mock('Illuminate\Routing\Route')
            ->shouldReceive('name')
            ->with('admin.component-page.component-subpage')
            ->once()
            ->getMock());
    
    $manager->registerRoutes();
    
    // Verification is done through the shouldReceive expectations
    expect(true)->toBeTrue(); // Dummy assertion to avoid empty test
});

// Clean up Mockery after each test
afterEach(function () {
    Mockery::close();
});
<?php

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

// Create a concrete implementation of the abstract DashboardWidget for testing
class TestDashboardWidget extends DashboardWidget
{
    protected function define(): void
    {
        $this->type = 'test-widget';
        $this->name = 'Test Widget';
        $this->slug = 'test-widget';
        $this->description = 'A widget for testing';
        $this->view = 'test-view';
    }
}

it('initializes with correct properties', function () {
    $widget = new TestDashboardWidget();
    $widget->init();

    expect($widget->getType())->toBe('test-widget');
    expect($widget->getName())->toBe('Test Widget');
    expect($widget->getSlug())->toBe('test-widget');
    expect($widget->getDescription())->toBe('A widget for testing');
});

it('can render a view', function () {
    $widget = new TestDashboardWidget();
    $widget->init();

    // Mock the view rendering
    $view = Mockery::mock('Illuminate\Contracts\View\View');
    $view->shouldReceive('render')->once()->andReturn('<div>Test Widget Content</div>');

    // Mock the view() helper
    $viewFactory = Mockery::mock('Illuminate\Contracts\View\Factory');
    $viewFactory->shouldReceive('make')
        ->with('test-view', Mockery::on(function ($data) {
            return isset($data['widgetInstanceId']) && $data['widgetInstanceId'] === 'test-instance-id';
        }), [])
        ->once()
        ->andReturn($view);

    app()->instance('view', $viewFactory);

    $result = $widget->render('test-instance-id');
    expect($result)->toBe('<div>Test Widget Content</div>');
});

it('can get widget settings', function () {
    $widget = new TestDashboardWidget();
    $widget->init();

    // Mock the DashboardWidgetsManager
    $manager = Mockery::mock(DashboardWidgetsManager::class);
    $manager->shouldReceive('getUserWidgetInstanceSettings')
        ->with('test-instance-id', null, [])
        ->once()
        ->andReturn(['order' => 5, 'custom_setting' => 'value']);

    // Bind the mock to the container
    App::instance(DashboardWidgetsManager::class, $manager);

    $settings = $widget->getSettings('test-instance-id');
    expect($settings)->toBe(['order' => 5, 'custom_setting' => 'value']);
});

it('can save widget settings', function () {
    $widget = new TestDashboardWidget();
    $widget->init();

    // Mock the DashboardWidgetsManager
    $manager = Mockery::mock(DashboardWidgetsManager::class);
    $manager->shouldReceive('saveUserWidgetInstanceSettings')
        ->with('test-instance-id', ['order' => 10, 'custom_setting' => 'new_value'], null)
        ->once();

    // Bind the mock to the container
    App::instance(DashboardWidgetsManager::class, $manager);

    $widget->saveSettings('test-instance-id', ['order' => 10, 'custom_setting' => 'new_value']);

    // Verification is done through the shouldReceive expectations
    expect(true)->toBeTrue(); // Dummy assertion to avoid empty test
});

it('handles Livewire components when available', function () {
    // Skip this test if Livewire is not available
    if (!class_exists(Livewire::class)) {
        $this->markTestSkipped('Livewire is not available.');
    }

    // Create a widget with a Livewire component
    $widget = new class extends DashboardWidget {
        protected function define(): void
        {
            $this->type = 'livewire-widget';
            $this->name = 'Livewire Widget';
            $this->slug = 'livewire-widget';
            $this->component = 'test-component';
        }
    };
    $widget->init();

    // Mock Livewire::mount
    $livewireComponent = Mockery::mock('stdClass');
    $livewireComponent->shouldReceive('html')->once()->andReturn('<div>Livewire Component</div>');

    Livewire::shouldReceive('mount')
        ->with('test-component', Mockery::on(function ($data) {
            return isset($data['widgetInstanceId']) && $data['widgetInstanceId'] === 'test-instance-id';
        }))
        ->once()
        ->andReturn($livewireComponent);

    $result = $widget->render('test-instance-id');
    expect($result)->toBe('<div>Livewire Component</div>');
});

// Clean up Mockery after each test
afterEach(function () {
    Mockery::close();
});

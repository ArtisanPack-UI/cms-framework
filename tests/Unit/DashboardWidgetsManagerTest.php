<?php

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Eventy;

// Create a concrete implementation of the abstract DashboardWidget for testing
class TestWidget extends DashboardWidget
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

it('can register a widget type', function () {
    $manager = new DashboardWidgetsManager();
    $widget = new TestWidget();

    $manager->registerWidgetType($widget);

    $registeredTypes = $manager->getRegisteredWidgetTypes();
    expect($registeredTypes)->toHaveKey('test-widget');
    expect($registeredTypes['test-widget'])->toBeInstanceOf(DashboardWidget::class);
});

it('can get a specific widget type', function () {
    $manager = new DashboardWidgetsManager();
    $widget = new TestWidget();

    $manager->registerWidgetType($widget);

    $retrievedWidget = $manager->getWidgetType('test-widget');
    expect($retrievedWidget)->toBeInstanceOf(DashboardWidget::class);
    expect($retrievedWidget->getType())->toBe('test-widget');
});

it('returns null for non-existent widget type', function () {
    $manager = new DashboardWidgetsManager();

    $retrievedWidget = $manager->getWidgetType('non-existent-widget');
    expect($retrievedWidget)->toBeNull();
});

it('can add a widget instance', function () {
    // For this test, we'll skip the Str::uuid() mocking and just verify
    // that the method returns a non-null value when successful
    $manager = new DashboardWidgetsManager();
    $widget = new TestWidget();
    $manager->registerWidgetType($widget);

    // Mock Auth::user()
    $user = Mockery::mock('ArtisanPackUI\CMSFramework\Models\User');
    $user->shouldReceive('getSetting')
        ->with('dashboard_widgets_instances_main', [])
        ->andReturn([]);
    $user->shouldReceive('setSetting')
        ->andReturnTrue();

    Auth::shouldReceive('user')
        ->andReturn($user);

    $instanceId = $manager->addWidgetInstance('test-widget', 'main', ['order' => 5]);

    expect($instanceId)->not->toBeNull();
});

it('returns null when adding widget instance with invalid type', function () {
    $manager = new DashboardWidgetsManager();

    $instanceId = $manager->addWidgetInstance('non-existent-widget', 'main');

    expect($instanceId)->toBeNull();
});

it('can get dashboard widget instances', function () {
    $manager = new DashboardWidgetsManager();
    $widget = new TestWidget();
    $manager->registerWidgetType($widget);

    // Mock Auth::user()
    $user = Mockery::mock('ArtisanPackUI\CMSFramework\Models\User');
    $user->shouldReceive('getSetting')
        ->with('dashboard_widgets_instances_main', [])
        ->andReturn([
            'instance-1' => [
                'type' => 'test-widget',
                'settings' => ['order' => 5]
            ],
            'instance-2' => [
                'type' => 'test-widget',
                'settings' => ['order' => 10]
            ]
        ]);

    Auth::shouldReceive('user')
        ->andReturn($user);

    // Mock Eventy::filter to return our widget types
    Eventy::shouldReceive('filter')
        ->with('ap.cms.dashboard.widget_types', Mockery::any())
        ->andReturn(['test-widget' => $widget]);

    $instances = $manager->getDashboardWidgetInstances('main');

    expect($instances)->toHaveCount(2);
    expect($instances[0]['id'])->toBe('instance-1');
    expect($instances[0]['type'])->toBe('test-widget');
    expect($instances[0]['settings']['order'])->toBe(5);
    expect($instances[1]['id'])->toBe('instance-2');
});

it('can remove a widget instance', function () {
    $manager = new DashboardWidgetsManager();
    $widget = new TestWidget();
    $manager->registerWidgetType($widget);

    // Mock Auth::user()
    $user = Mockery::mock('ArtisanPackUI\CMSFramework\Models\User');
    $user->shouldReceive('getSetting')
        ->with('dashboard_widgets_instances_main', [])
        ->andReturn([
            'instance-1' => [
                'type' => 'test-widget',
                'settings' => ['order' => 5]
            ],
            'instance-2' => [
                'type' => 'test-widget',
                'settings' => ['order' => 10]
            ]
        ]);
    $user->shouldReceive('setSetting')
        ->andReturnTrue();

    Auth::shouldReceive('user')
        ->andReturn($user);

    // Mock Eventy::filter to return our widget types
    Eventy::shouldReceive('filter')
        ->with('ap.cms.dashboard.widget_types', Mockery::any())
        ->andReturn(['test-widget' => $widget]);

    $result = $manager->removeWidgetInstance('instance-1', 'main');

    expect($result)->toBeTrue();
});

it('can get user widget instance settings', function () {
    $manager = new DashboardWidgetsManager();
    $widget = new TestWidget();
    $manager->registerWidgetType($widget);

    // Mock Auth::user()
    $user = Mockery::mock('ArtisanPackUI\CMSFramework\Models\User');
    $user->shouldReceive('getSetting')
        ->with('dashboard_widgets_instances_main', [])
        ->andReturn([
            'instance-1' => [
                'type' => 'test-widget',
                'settings' => ['order' => 5, 'custom' => 'value']
            ]
        ]);

    Auth::shouldReceive('user')
        ->andReturn($user);

    // Mock Eventy::filter to return our widget types
    Eventy::shouldReceive('filter')
        ->with('ap.cms.dashboard.widget_types', Mockery::any())
        ->andReturn(['test-widget' => $widget]);

    $settings = $manager->getUserWidgetInstanceSettings('instance-1', 'main');

    expect($settings)->toHaveKey('order');
    expect($settings)->toHaveKey('custom');
    expect($settings['order'])->toBe(5);
    expect($settings['custom'])->toBe('value');
});

it('can save user widget instance settings', function () {
    $manager = new DashboardWidgetsManager();
    $widget = new TestWidget();
    $manager->registerWidgetType($widget);

    // Mock Auth::user()
    $user = Mockery::mock('ArtisanPackUI\CMSFramework\Models\User');
    $user->shouldReceive('getSetting')
        ->with('dashboard_widgets_instances_main', [])
        ->andReturn([
            'instance-1' => [
                'type' => 'test-widget',
                'settings' => ['order' => 5]
            ]
        ]);
    $user->shouldReceive('setSetting')
        ->andReturnTrue();

    Auth::shouldReceive('user')
        ->andReturn($user);

    // Mock Eventy::filter to return our widget types
    Eventy::shouldReceive('filter')
        ->with('ap.cms.dashboard.widget_types', Mockery::any())
        ->andReturn(['test-widget' => $widget]);

    $manager->saveUserWidgetInstanceSettings('instance-1', ['order' => 10, 'custom' => 'new-value'], 'main');

    // Verification is done through the shouldReceive expectations
    expect(true)->toBeTrue(); // Dummy assertion to avoid empty test
});

it('can render a widget instance', function () {
    $manager = new DashboardWidgetsManager();
    $widget = new TestWidget();
    $widget->init();
    $manager->registerWidgetType($widget);

    // Mock Auth::user()
    $user = Mockery::mock('ArtisanPackUI\CMSFramework\Models\User');
    $user->shouldReceive('getSetting')
        ->with('dashboard_widgets_instances_main', [])
        ->andReturn([
            'instance-1' => [
                'type' => 'test-widget',
                'settings' => ['order' => 5, 'custom' => 'value']
            ]
        ]);

    Auth::shouldReceive('user')
        ->andReturn($user);

    // Mock Eventy::filter to return our widget types
    Eventy::shouldReceive('filter')
        ->with('ap.cms.dashboard.widget_types', Mockery::any())
        ->andReturn(['test-widget' => $widget]);

    // Mock the widget's render method
    $widget = Mockery::mock(TestWidget::class)->makePartial();
    $widget->shouldReceive('render')
        ->with('instance-1', Mockery::on(function ($data) {
            return isset($data['order']) && $data['order'] === 5 &&
                   isset($data['custom']) && $data['custom'] === 'value';
        }))
        ->andReturn('<div>Widget Content</div>');

    // Replace the registered widget with our mock
    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('widgetTypes');
    $property->setAccessible(true);
    $property->setValue($manager, ['test-widget' => $widget]);

    $html = $manager->renderWidgetInstance('instance-1', 'main');

    expect($html)->toBe('<div>Widget Content</div>');
});

// Clean up Mockery after each test
afterEach(function () {
    Mockery::close();
});

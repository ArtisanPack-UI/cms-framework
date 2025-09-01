<?php

/**
 * PHPBench Bootstrap File for CMS Framework Performance Testing
 * 
 * This file initializes the Laravel application environment for performance testing
 * and provides necessary setup for benchmarking operations.
 */

require __DIR__ . '/../vendor/autoload.php';

use Orchestra\Testbench\TestCase;
use ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider;

// Create a minimal application instance for benchmarking
$app = (new class extends TestCase {
    protected function getPackageProviders($app)
    {
        return [
            CMSFrameworkServiceProvider::class,
        ];
    }
    
    protected function getEnvironmentSetUp($app)
    {
        // Set up in-memory SQLite database for performance testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        
        // Set up cache configuration for testing
        $app['config']->set('cache.default', 'array');
        
        // Disable debug mode for accurate performance measurements
        $app['config']->set('app.debug', false);
        
        // Set session driver to array for performance
        $app['config']->set('session.driver', 'array');
        
        // Disable logging during benchmarks
        $app['config']->set('logging.default', 'null');
    }
})->createApplication();

// Run migrations
$app['artisan']->call('migrate:fresh');

// Make the application globally available for benchmarks
$GLOBALS['laravel_app'] = $app;

// Helper function to get the application instance
function app($abstract = null)
{
    if (is_null($abstract)) {
        return $GLOBALS['laravel_app'];
    }
    
    return $GLOBALS['laravel_app'][$abstract];
}
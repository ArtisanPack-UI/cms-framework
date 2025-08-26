<?php

declare(strict_types=1);

namespace Tests\Feature;

use ArtisanPackUI\CMSFramework\Exceptions\AuthorizationException;
use ArtisanPackUI\CMSFramework\Exceptions\CMSException;
use ArtisanPackUI\CMSFramework\Exceptions\ContentException;
use ArtisanPackUI\CMSFramework\Exceptions\MediaException;
use ArtisanPackUI\CMSFramework\Exceptions\PluginException;
use ArtisanPackUI\CMSFramework\Exceptions\UserException;
use ArtisanPackUI\CMSFramework\Http\Middleware\ErrorHandlingMiddleware;
use ArtisanPackUI\CMSFramework\Services\AuditLoggerService;
use ArtisanPackUI\CMSFramework\Services\ErrorTrackingService;
use ArtisanPackUI\CMSFramework\Services\StructuredLoggerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Comprehensive error handling feature tests
 */
class ErrorHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing logs
        $this->clearTestLogs();

        // Set up error handling configuration for testing
        Config::set('cms-error-handling.development.testing.capture_test_errors', true);
        Config::set('cms-error-handling.development.testing.disable_external_tracking', true);
        Config::set('cms-error-handling.tracking.enabled', true);
        Config::set('cms-error-handling.logging.default_channel', 'testing');
    }

    protected function tearDown(): void
    {
        $this->clearTestLogs();
        parent::tearDown();
    }

    /**
     * Test CMS exception handling
     */
    public function test_cms_exception_handling(): void
    {
        $context = ['test_id' => 'cms_test_'.uniqid()];
        $exception = new CMSException('Test CMS error', 500, null, $context);

        $this->assertInstanceOf(CMSException::class, $exception);
        $this->assertEquals('Test CMS error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals($context, $exception->getContext());

        // Test error response format
        $response = $exception->render(request());
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     * Test authorization exception handling
     */
    public function test_authorization_exception_handling(): void
    {
        $context = ['user_id' => 123, 'resource' => 'test_resource'];
        $exception = new AuthorizationException(
            'Access denied to test resource',
            'test_permission',
            403,
            null,
            $context
        );

        $this->assertInstanceOf(AuthorizationException::class, $exception);
        $this->assertEquals('test_permission', $exception->getPermission());
        $this->assertEquals(403, $exception->getCode());
        $this->assertEquals($context, $exception->getContext());

        // Test JSON response format
        $response = $exception->render(request());
        $this->assertEquals(403, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('permission', $responseData);
        $this->assertEquals('test_permission', $responseData['permission']);
    }

    /**
     * Test content exception handling
     */
    public function test_content_exception_handling(): void
    {
        $context = ['validation_errors' => ['title' => 'Title is required']];
        $exception = new ContentException(
            'Content validation failed',
            'test_content',
            'validation_failed',
            422,
            null,
            $context
        );

        $this->assertInstanceOf(ContentException::class, $exception);
        $this->assertEquals('test_content', $exception->getContentId());
        $this->assertEquals('validation_failed', $exception->getErrorType());
        $this->assertEquals(422, $exception->getCode());

        $response = $exception->render(request());
        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * Test media exception handling
     */
    public function test_media_exception_handling(): void
    {
        $context = ['file_size' => 1024000, 'max_size' => 512000];
        $exception = new MediaException(
            'File too large',
            'test_file.jpg',
            'file_too_large',
            422,
            null,
            $context
        );

        $this->assertInstanceOf(MediaException::class, $exception);
        $this->assertEquals('test_file.jpg', $exception->getFileName());
        $this->assertEquals('file_too_large', $exception->getErrorType());
        $this->assertEquals($context, $exception->getContext());

        $response = $exception->render(request());
        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * Test plugin exception handling
     */
    public function test_plugin_exception_handling(): void
    {
        $context = ['plugin_version' => '1.0.0', 'required_version' => '1.2.0'];
        $exception = new PluginException(
            'Plugin version incompatible',
            'test_plugin',
            'version_incompatible',
            500,
            null,
            $context
        );

        $this->assertInstanceOf(PluginException::class, $exception);
        $this->assertEquals('test_plugin', $exception->getPluginName());
        $this->assertEquals('version_incompatible', $exception->getErrorType());

        $response = $exception->render(request());
        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     * Test user exception handling
     */
    public function test_user_exception_handling(): void
    {
        $context = ['user_data' => ['email' => 'test@example.com']];
        $exception = new UserException(
            'User action failed',
            'update_profile',
            400,
            null,
            $context
        );

        $this->assertInstanceOf(UserException::class, $exception);
        $this->assertEquals('update_profile', $exception->getAction());
        $this->assertEquals(400, $exception->getCode());

        $response = $exception->render(request());
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * Test error tracking service
     */
    public function test_error_tracking_service(): void
    {
        $service = app(ErrorTrackingService::class);
        $exception = new CMSException('Test tracking error');
        $context = ['test_tracking' => true];

        // Track the error
        $result = $service->trackError($exception, $context);

        $this->assertTrue($result);

        // Verify error was tracked
        $errorId = $service->getLastTrackedErrorId();
        $this->assertNotNull($errorId);

        // Test error retrieval
        $trackedError = $service->getTrackedError($errorId);
        $this->assertNotNull($trackedError);
        $this->assertEquals('Test tracking error', $trackedError['message']);
        $this->assertEquals(CMSException::class, $trackedError['exception_class']);
    }

    /**
     * Test structured logger service
     */
    public function test_structured_logger_service(): void
    {
        $service = app(StructuredLoggerService::class);
        $exception = new CMSException('Test structured logging');
        $context = ['structured_test' => true, 'user_id' => 123];

        // Log the error
        $service->logError($exception, $context, 'error');

        // Verify log entry was created
        $this->assertLogContains('Test structured logging');
        $this->assertLogContains('"structured_test":true');
        $this->assertLogContains('"user_id":123');
    }

    /**
     * Test audit logger service
     */
    public function test_audit_logger_service(): void
    {
        $service = app(AuditLoggerService::class);

        $auditData = [
            'event' => 'error_occurred',
            'user_id' => 123,
            'error_type' => 'cms_exception',
            'severity' => 'error',
            'handled' => true,
        ];

        // Create audit log entry
        $service->logErrorHandling($auditData);

        // Verify audit log entry
        $this->assertAuditLogContains('error_occurred');
        $this->assertAuditLogContains('"user_id":123');
        $this->assertAuditLogContains('"handled":true');
    }

    /**
     * Test error handling middleware
     */
    public function test_error_handling_middleware(): void
    {
        $middleware = new ErrorHandlingMiddleware(
            app(ErrorTrackingService::class),
            app(StructuredLoggerService::class),
            app(AuditLoggerService::class)
        );

        $request = Request::create('/test', 'GET');

        // Test middleware handles exceptions properly
        $next = function ($req) {
            throw new CMSException('Middleware test error');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertLogContains('Middleware test error');
    }

    /**
     * Test error log cleanup command
     */
    public function test_error_log_cleanup_command(): void
    {
        // Create some test log files
        $this->createTestLogFiles();

        // Run cleanup command with dry-run
        $this->artisan('cms:error-logs:cleanup', [
            '--dry-run' => true,
            '--days' => 1,
        ])
            ->assertExitCode(0)
            ->expectsOutput('Starting error log cleanup...')
            ->expectsOutput('DRY RUN MODE - No files will be modified');
    }

    /**
     * Test error log viewing command
     */
    public function test_error_log_view_command(): void
    {
        // Create a test log entry
        Log::error('Test error for viewing', ['test_context' => true]);

        $this->artisan('cms:error-logs:view', [
            '--lines' => 5,
            '--level' => 'error',
        ])
            ->assertExitCode(0);
    }

    /**
     * Test error analysis command
     */
    public function test_error_analysis_command(): void
    {
        // Create some test errors
        Log::error('Test error 1', ['type' => 'test']);
        Log::error('Test error 2', ['type' => 'test']);
        Log::warning('Test warning', ['type' => 'test']);

        $this->artisan('cms:error-logs:analyze', [
            '--period' => '1h',
        ])
            ->assertExitCode(0);
    }

    /**
     * Test error testing command
     */
    public function test_error_testing_command(): void
    {
        $this->artisan('cms:error-logs:test', [
            '--type' => 'cms',
            '--count' => 1,
            '--quiet' => true,
        ])
            ->assertExitCode(0);
    }

    /**
     * Test error recovery mechanisms
     */
    public function test_error_recovery_mechanisms(): void
    {
        $service = app(ErrorTrackingService::class);

        // Test cache clearing recovery
        Cache::put('test_cache_key', 'test_value');
        $this->assertTrue(Cache::has('test_cache_key'));

        $exception = new CMSException('Cache error test');
        $context = ['recovery_type' => 'cache_clearing'];

        $service->trackError($exception, $context);
        $service->attemptRecovery($exception, $context);

        // Verify recovery was attempted (cache should be cleared)
        $this->assertFalse(Cache::has('test_cache_key'));
    }

    /**
     * Test error notification system
     */
    public function test_error_notification_system(): void
    {
        // Mock notification settings
        Config::set('cms-error-handling.notifications.enabled', true);
        Config::set('cms-error-handling.notifications.triggers.critical_errors', true);

        $service = app(ErrorTrackingService::class);
        $exception = new CMSException('Critical test error');
        $context = ['severity' => 'critical'];

        // This should trigger a notification
        $result = $service->trackError($exception, $context);
        $this->assertTrue($result);

        // Verify notification was queued (in a real scenario)
        // For testing, we just verify the error was tracked
        $this->assertNotNull($service->getLastTrackedErrorId());
    }

    /**
     * Test error sanitization
     */
    public function test_error_sanitization(): void
    {
        $service = app(StructuredLoggerService::class);

        $sensitiveContext = [
            'password' => 'secret123',
            'api_key' => 'abc123xyz',
            'normal_data' => 'safe_value',
            'token' => 'sensitive_token',
        ];

        $exception = new CMSException('Error with sensitive data');
        $service->logError($exception, $sensitiveContext, 'error');

        // Verify sensitive data was sanitized
        $this->assertLogContains('Error with sensitive data');
        $this->assertLogContains('"normal_data":"safe_value"');
        $this->assertLogDoesNotContain('secret123');
        $this->assertLogDoesNotContain('abc123xyz');
        $this->assertLogDoesNotContain('sensitive_token');
    }

    /**
     * Test performance monitoring
     */
    public function test_performance_monitoring(): void
    {
        $service = app(ErrorTrackingService::class);

        $startTime = microtime(true);

        $exception = new CMSException('Performance test error');
        $service->trackError($exception, ['performance_test' => true]);

        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Verify error handling was reasonably fast
        $this->assertLessThan(100, $duration, 'Error handling took too long');
    }

    /**
     * Helper method to clear test logs
     */
    protected function clearTestLogs(): void
    {
        $logPaths = [
            storage_path('logs/testing.log'),
            storage_path('logs/cms/errors.log'),
            storage_path('logs/cms/audit.log'),
        ];

        foreach ($logPaths as $path) {
            if (file_exists($path)) {
                file_put_contents($path, '');
            }
        }
    }

    /**
     * Helper method to create test log files
     */
    protected function createTestLogFiles(): void
    {
        $logDir = storage_path('logs/test');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Create old log file
        $oldFile = $logDir.'/old.log';
        file_put_contents($oldFile, 'Old log content');
        touch($oldFile, time() - (48 * 60 * 60)); // 2 days old

        // Create large log file
        $largeFile = $logDir.'/large.log';
        file_put_contents($largeFile, str_repeat('Large log content'.PHP_EOL, 10000));

        // Create empty log file
        $emptyFile = $logDir.'/empty.log';
        touch($emptyFile);
    }

    /**
     * Assert log contains specific content
     */
    protected function assertLogContains(string $content): void
    {
        $logFile = storage_path('logs/testing.log');
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $this->assertStringContainsString($content, $logContent);
        } else {
            $this->fail('Log file does not exist');
        }
    }

    /**
     * Assert log does not contain specific content
     */
    protected function assertLogDoesNotContain(string $content): void
    {
        $logFile = storage_path('logs/testing.log');
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $this->assertStringNotContainsString($content, $logContent);
        }
    }

    /**
     * Assert audit log contains specific content
     */
    protected function assertAuditLogContains(string $content): void
    {
        $logFile = storage_path('logs/cms/audit.log');
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $this->assertStringContainsString($content, $logContent);
        } else {
            $this->fail('Audit log file does not exist');
        }
    }
}

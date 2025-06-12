<?php

namespace Tests\Feature;

use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;
use ArtisanPackUI\CMSFramework\Models\AuditLog;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    protected AuditLogger $auditLogger;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create an instance of AuditLogger
        $this->auditLogger = new AuditLogger();
    }

    #[Test]
    public function it_logs_successful_login()
    {
        // Act
        $log = $this->auditLogger->logLogin($this->user);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals('login_success', $log->action);
        $this->assertEquals('success', $log->status);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertStringContainsString($this->user->email, $log->message);
        $this->assertStringContainsString((string)$this->user->id, $log->message);
    }

    #[Test]
    public function it_logs_failed_login()
    {
        // Arrange
        $email = 'test@example.com';

        // Act
        $log = $this->auditLogger->logLoginFailed($email);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals('login_failed', $log->action);
        $this->assertEquals('failed', $log->status);
        $this->assertNull($log->user_id);
        $this->assertStringContainsString($email, $log->message);
    }

    #[Test]
    public function it_logs_logout()
    {
        // Act
        $log = $this->auditLogger->logLogout($this->user);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals('logout_success', $log->action);
        $this->assertEquals('success', $log->status);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertStringContainsString($this->user->email, $log->message);
        $this->assertStringContainsString((string)$this->user->id, $log->message);
    }

    #[Test]
    public function it_logs_password_change()
    {
        // Act
        $log = $this->auditLogger->logPasswordChange($this->user);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals('password_changed', $log->action);
        $this->assertEquals('success', $log->status);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertStringContainsString($this->user->email, $log->message);
        $this->assertStringContainsString((string)$this->user->id, $log->message);
    }

    #[Test]
    public function it_logs_generic_activity()
    {
        // Arrange
        $action = 'custom_action';
        $description = 'This is a custom action description';
        $status = 'info';

        // Act
        $log = $this->auditLogger->logActivity($action, $description, $status, $this->user->id);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals($action, $log->action);
        $this->assertEquals($description, $log->message);
        $this->assertEquals($status, $log->status);
        $this->assertEquals($this->user->id, $log->user_id);
    }

    #[Test]
    public function it_logs_generic_activity_without_user()
    {
        // Arrange
        $action = 'system_action';
        $description = 'This is a system action description';
        $status = 'info';

        // Act
        $log = $this->auditLogger->logActivity($action, $description, $status);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals($action, $log->action);
        $this->assertEquals($description, $log->message);
        $this->assertEquals($status, $log->status);
        $this->assertNull($log->user_id);
    }
}
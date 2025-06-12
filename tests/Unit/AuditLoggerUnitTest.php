<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogger;
use ArtisanPackUI\CMSFramework\Models\AuditLog;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLoggerUnitTest extends TestCase
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
    public function it_creates_audit_log_with_correct_data()
    {
        // Act
        $log = $this->auditLogger->logLogin($this->user);

        // Assert
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'login_success',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function it_captures_request_information()
    {
        // Act
        $log = $this->auditLogger->logLogin($this->user);

        // Assert
        $this->assertNotEmpty($log->ip_address);
        $this->assertNotEmpty($log->user_agent);
    }

    #[Test]
    public function it_sanitizes_input_data()
    {
        // Arrange
        $unsafeAction = '<script>alert("XSS")</script>';
        $unsafeDescription = '<img src="x" onerror="alert(\'XSS\')">';

        // Act
        $log = $this->auditLogger->logActivity($unsafeAction, $unsafeDescription, 'info');

        // Assert
        $this->assertNotEquals($unsafeAction, $log->action);
        $this->assertNotEquals($unsafeDescription, $log->message);
    }

    #[Test]
    public function it_handles_null_user_id()
    {
        // Act
        $log = $this->auditLogger->logActivity('test_action', 'Test message', 'info');

        // Assert
        $this->assertNull($log->user_id);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'action' => 'test_action',
            'message' => 'Test message',
            'status' => 'info',
        ]);
    }
}

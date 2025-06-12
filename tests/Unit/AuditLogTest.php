<?php

namespace Tests\Unit;

use ArtisanPackUI\CMSFramework\Models\AuditLog;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_can_create_audit_log()
    {
        // Arrange
        $data = [
            'user_id' => $this->user->id,
            'action' => 'test_action',
            'message' => 'Test message',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test User Agent',
            'status' => 'success',
        ];

        // Act
        $log = AuditLog::create($data);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals($data['user_id'], $log->user_id);
        $this->assertEquals($data['action'], $log->action);
        $this->assertEquals($data['message'], $log->message);
        $this->assertEquals($data['ip_address'], $log->ip_address);
        $this->assertEquals($data['user_agent'], $log->user_agent);
        $this->assertEquals($data['status'], $log->status);
    }

    #[Test]
    public function it_belongs_to_user()
    {
        // Arrange
        $log = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'test_action',
            'message' => 'Test message',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test User Agent',
            'status' => 'success',
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $log->user);
        $this->assertEquals($this->user->id, $log->user->id);
    }

    #[Test]
    public function it_can_create_log_without_user()
    {
        // Arrange
        $data = [
            'action' => 'system_action',
            'message' => 'System message',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test User Agent',
            'status' => 'info',
        ];

        // Act
        $log = AuditLog::create($data);

        // Assert
        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertNull($log->user_id);
        $this->assertEquals($data['action'], $log->action);
        $this->assertEquals($data['message'], $log->message);
    }

}

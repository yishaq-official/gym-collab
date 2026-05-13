<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\ModelsTest;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use Yishaq\Server\Models\Notification;
use Yishaq\Server\Database;

class NotificationTest extends TestCase
{
    private Database $db;
    private Notification $notification;
    private $mockPdo;
    private $mockStmt;

    protected function setUp(): void
    {
        parent::setUp();
        $config = [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ];
        $this->db = new Database($config);
        
        $this->mockPdo = $this->createMock(PDO::class);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($this->db, $this->mockPdo);
        
        $this->mockStmt = $this->createMock(PDOStatement::class);
        
        $this->notification = new Notification($this->db);
    }

    /** @test */
    public function it_returns_table_name()
    {
        $reflection = new \ReflectionMethod($this->notification, 'table');
        $reflection->setAccessible(true);
        $this->assertEquals('notifications', $reflection->invoke($this->notification));
    }

    /** @test */
    public function it_creates_a_notification()
    {
        $payload = [
            'user_id' => 10,
            'content' => 'Welcome to the gym!',
            'type' => 'welcome',
            'sent_datetime' => '2025-01-01 10:00:00',
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'insert into notifications') !== false &&
                       strpos($normalized, 'values (:user_id, :content, :type, :sent_datetime, now(), now())') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) use ($payload) {
                return $bindings['user_id'] === $payload['user_id']
                    && $bindings['content'] === $payload['content']
                    && $bindings['type'] === $payload['type']
                    && $bindings['sent_datetime'] === $payload['sent_datetime'];
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('42');
        
        $id = $this->notification->create($payload);
        $this->assertEquals(42, $id);
    }

    /** @test */
    public function it_creates_notification_with_default_sent_datetime()
    {
        $payload = [
            'user_id' => 5,
            'content' => 'Your membership is expiring soon',
            'type' => 'reminder',
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) {
                // sent_datetime should be set automatically
                return $bindings['user_id'] === 5
                    && $bindings['content'] === 'Your membership is expiring soon'
                    && $bindings['type'] === 'reminder'
                    && isset($bindings['sent_datetime']);
            }));
        
        $this->mockStmt->expects($this->once())->method('rowCount')->willReturn(1);
        $this->mockPdo->method('lastInsertId')->willReturn('99');
        
        $id = $this->notification->create($payload);
        $this->assertEquals(99, $id);
    }

    /** @test */
    public function it_creates_notification_without_user_id()
    {
        $payload = [
            'content' => 'System maintenance tonight',
            'type' => 'announcement',
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) {
                return $bindings['user_id'] === null
                    && $bindings['content'] === 'System maintenance tonight'
                    && $bindings['type'] === 'announcement';
            }));
        
        $this->mockStmt->expects($this->once())->method('rowCount')->willReturn(1);
        $this->mockPdo->method('lastInsertId')->willReturn('77');
        
        $id = $this->notification->create($payload);
        $this->assertEquals(77, $id);
    }

    /** @test */
    public function it_returns_latest_notifications_by_user_id()
    {
        $userId = 20;
        $expectedRows = [
            ['id' => 3, 'content' => 'Message 1'],
            ['id' => 2, 'content' => 'Message 2'],
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) use ($userId) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'select id, user_id, content, type, sent_datetime from notifications where user_id = :user_id order by sent_datetime desc, id desc limit 10') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['user_id' => $userId]);
        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);
        
        $result = $this->notification->latestByUserId($userId);
        $this->assertEquals($expectedRows, $result);
    }

    /** @test */
    public function it_returns_latest_notifications_with_custom_limit()
    {
        $userId = 15;
        $limit = 5;
        $expectedRows = [['id' => 10, 'content' => 'Test']];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) use ($limit) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, "limit {$limit}") !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())->method('execute');
        $this->mockStmt->expects($this->once())->method('fetchAll')->willReturn($expectedRows);
        
        $result = $this->notification->latestByUserId($userId, $limit);
        $this->assertEquals($expectedRows, $result);
    }

    /** @test */
    public function it_detects_recent_notification_exists()
    {
        $userId = 1;
        $type = 'reminder';
        $withinHours = 24;
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) use ($withinHours) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, "select id from notifications where user_id = :user_id and type = :type and sent_datetime >= date_sub(now(), interval {$withinHours} hour) order by sent_datetime desc, id desc limit 1") !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['user_id' => $userId, 'type' => $type]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['id' => 123]);
        
        $exists = $this->notification->existsRecentByType($userId, $type, $withinHours);
        $this->assertTrue($exists);
    }

    /** @test */
    public function it_returns_false_when_no_recent_notification()
    {
        $userId = 2;
        $type = 'alert';
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())->method('execute');
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);
        
        $exists = $this->notification->existsRecentByType($userId, $type);
        $this->assertFalse($exists);
    }
}
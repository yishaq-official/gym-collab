<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\ModelsTest;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use Yishaq\Server\Models\Membership;
use Yishaq\Server\Database;

class MembershipTest extends TestCase
{
    private Database $db;
    private Membership $membership;
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
        $this->membership = new Membership($this->db);
    }

    /** @test */
    public function it_returns_table_name()
    {
        $reflection = new \ReflectionMethod($this->membership, 'table');
        $reflection->setAccessible(true);
        $this->assertEquals('memberships', $reflection->invoke($this->membership));
    }

    /** @test */
    public function it_finds_membership_by_id()
    {
        $expected = ['id' => 1, 'user_id' => 5, 'membership_type' => 'gold'];
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expected);
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM memberships WHERE id = :id LIMIT 1'))
            ->willReturn($this->mockStmt);
        
        $result = $this->membership->findById(1);
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_returns_null_when_membership_not_found()
    {
        $this->mockStmt->expects($this->once())->method('execute');
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        
        $result = $this->membership->findById(999);
        $this->assertNull($result);
    }

    /** @test */
    public function it_finds_latest_membership_by_user_id()
    {
        $expected = ['id' => 3, 'user_id' => 10, 'plan_expires_at' => '2025-01-01'];
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['user_id' => 10]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expected);
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('ORDER BY id DESC LIMIT 1'))
            ->willReturn($this->mockStmt);
        
        $result = $this->membership->findLatestByUserId(10);
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_finds_latest_paid_membership_by_user_id()
    {
        $expected = ['id' => 5, 'user_id' => 20, 'payment_status' => 'paid'];
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['user_id' => 20]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expected);
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains("payment_status = 'paid'"))
            ->willReturn($this->mockStmt);
        
        $result = $this->membership->findLatestPaidByUserId(20);
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_creates_new_membership_with_defaults()
    {
        $payload = [
            'user_id' => 100,
            'membership_type' => 'basic',
            'plan_cost' => 5000,
            'currency' => 'USD',
            'membership_status' => 'active',
            'payment_status' => 'pending',
            'plan_start_at' => '2025-01-01',
            'plan_expires_at' => '2025-12-31',
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO memberships'))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) use ($payload) {
                return $bindings['user_id'] === $payload['user_id']
                    && $bindings['membership_type'] === $payload['membership_type']
                    && $bindings['plan_cost'] === $payload['plan_cost']
                    && $bindings['currency'] === $payload['currency']
                    && $bindings['membership_status'] === $payload['membership_status']
                    && $bindings['payment_status'] === $payload['payment_status']
                    && $bindings['plan_start_at'] === $payload['plan_start_at']
                    && $bindings['plan_expires_at'] === $payload['plan_expires_at'];
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('42');
        
        $id = $this->membership->create($payload);
        $this->assertEquals(42, $id);
    }

    /** @test */
    public function it_creates_membership_with_optional_fields_defaulting()
    {
        $payload = [
            'user_id' => 200,
            'membership_type' => 'premium',
            'plan_cost' => 10000,
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) {
                return $bindings['currency'] === 'ETB'
                    && $bindings['membership_status'] === 'pending'
                    && $bindings['payment_status'] === 'pending'
                    && $bindings['plan_start_at'] === null
                    && $bindings['plan_expires_at'] === null;
            }));
        
        $this->mockStmt->expects($this->once())->method('rowCount')->willReturn(1);
        $this->mockPdo->method('lastInsertId')->willReturn('99');
        
        $id = $this->membership->create($payload);
        $this->assertEquals(99, $id);
    }

    /** @test */
    public function it_marks_payment_as_paid()
    {
        $id = 10;
        $startAt = '2025-01-01';
        $expiresAt = '2025-12-31';
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                return strpos($sql, 'UPDATE memberships') !== false
                    && strpos($sql, "payment_status = 'paid'") !== false
                    && strpos($sql, 'plan_start_at = :plan_start_at') !== false
                    && strpos($sql, 'plan_expires_at = :plan_expires_at') !== false
                    && strpos($sql, 'WHERE id = :id') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => $id, 'plan_start_at' => $startAt, 'plan_expires_at' => $expiresAt]);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->membership->markPaymentPaid($id, $startAt, $expiresAt);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_marks_payment_as_failed()
    {
        $id = 5;
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                return strpos($sql, 'UPDATE memberships') !== false
                    && strpos($sql, "payment_status = 'failed'") !== false
                    && strpos($sql, 'updated_at = NOW()') !== false
                    && strpos($sql, 'WHERE id = :id') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => $id]);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->membership->markPaymentFailed($id);
        $this->assertEquals(1, $result);
    }
}
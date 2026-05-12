<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\ModelsTest;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use Yishaq\Server\Models\User;
use Yishaq\Server\Database;

class UserTest extends TestCase
{
    private Database $db;
    private User $user;
    private $mockPdo;
    private $mockStmt;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a real Database with dummy config
        $config = [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ];
        $this->db = new Database($config);
        
        // Replace internal PDO with a mock
        $this->mockPdo = $this->createMock(PDO::class);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($this->db, $this->mockPdo);
        
        $this->mockStmt = $this->createMock(PDOStatement::class);
        
        $this->user = new User($this->db);
    }

    /** @test */
    public function it_returns_table_name()
    {
        $reflection = new \ReflectionMethod($this->user, 'table');
        $reflection->setAccessible(true);
        $this->assertEquals('users', $reflection->invoke($this->user));
    }

    /** @test */
    public function it_finds_by_id()
    {
        $expectedUser = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedUser);
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM users WHERE id = :id'))
            ->willReturn($this->mockStmt);
        
        $result = $this->user->findById(1);
        $this->assertEquals($expectedUser, $result);
    }

    /** @test */
    public function it_returns_null_when_user_not_found_by_id()
    {
        $this->mockStmt->expects($this->once())->method('execute');
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        
        $result = $this->user->findById(999);
        $this->assertNull($result);
    }

    /** @test */
    public function it_finds_by_email()
    {
        $expectedUser = ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'];
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['email' => 'jane@example.com']);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedUser);
        
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        
        $result = $this->user->findByEmail('jane@example.com');
        $this->assertEquals($expectedUser, $result);
    }

    /** @test */
    public function it_creates_user_and_returns_last_insert_id()
    {
        $payload = [
            'name' => 'New User',
            'username' => 'newuser',
            'email' => 'new@example.com',
            'phone' => '1234567890',
            'password' => 'hashed_password',
            'role' => 'admin',
            'account_status' => 'active'
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO users'))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) use ($payload) {
                return $bindings['name'] === $payload['name']
                    && $bindings['username'] === $payload['username']
                    && $bindings['email'] === $payload['email']
                    && $bindings['phone'] === $payload['phone']
                    && $bindings['password'] === $payload['password']
                    && $bindings['role'] === $payload['role']
                    && $bindings['account_status'] === $payload['account_status'];
            }));
        
        // rowCount is required because Database::statement() returns $statement->rowCount()
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('42');
        
        $id = $this->user->create($payload);
        $this->assertEquals(42, $id);
    }

    /** @test */
    public function it_creates_user_with_defaults()
    {
        $payload = [
            'name' => 'Default User',
            'email' => 'default@example.com',
            'password' => 'hashed'
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) {
                return $bindings['username'] === null
                    && $bindings['phone'] === ''
                    && $bindings['role'] === 'member'
                    && $bindings['account_status'] === 'pending_approval';
            }));
        
        // rowCount is required because Database::statement() returns $statement->rowCount()
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $this->mockPdo->method('lastInsertId')->willReturn('10');
        
        $id = $this->user->create($payload);
        $this->assertEquals(10, $id);
    }

    /** @test */
    public function it_updates_user_by_id()
    {
        $attributes = ['name' => 'Updated Name', 'phone' => '9876543210'];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with("UPDATE users SET name = :name, phone = :phone, updated_at = NOW() WHERE id = :id")
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1, 'name' => 'Updated Name', 'phone' => '9876543210']);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->user->updateById(1, $attributes);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_returns_zero_when_no_attributes_to_update()
    {
        $result = $this->user->updateById(1, []);
        $this->assertEquals(0, $result);
        $this->mockPdo->expects($this->never())->method('prepare');
    }

    /** @test */
    public function it_updates_password_by_email()
    {
        $email = 'user@example.com';
        $hashedPassword = 'new_hashed_password';
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with("UPDATE users SET password = :password, updated_at = NOW() WHERE email = :email")
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['email' => $email, 'password' => $hashedPassword]);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->user->updatePasswordByEmail($email, $hashedPassword);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_updates_password_by_id()
    {
        $id = 5;
        $hashedPassword = 'new_hashed_password';
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id")
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => $id, 'password' => $hashedPassword]);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->user->updatePasswordById($id, $hashedPassword);
        $this->assertEquals(1, $result ,"");
    }
}
<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\ServicesTest;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Services\UserService;
use Yishaq\Server\Models\User;
use Yishaq\Server\Database;
use PDO;
use PDOStatement;
use RuntimeException;

class UserServiceTest extends TestCase
{
    private Database $db;
    private $pdoMock;
    private UserService $userService;

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

        $this->pdoMock = $this->createMock(PDO::class);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($this->db, $this->pdoMock);

        // Use real User model with our db
        $userModel = new User($this->db);
        $this->userService = new UserService($userModel);
    }

    private function createSelectStmt($bindings, $returnRow): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($bindings);
        $stmt->expects($this->once())->method('fetch')->willReturn($returnRow);
        return $stmt;
    }

    private function createInsertStmt($bindings, $rowCount = 1): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($bindings);
        $stmt->expects($this->once())->method('rowCount')->willReturn($rowCount);
        return $stmt;
    }

    /** @test */
    public function it_finds_by_id()
    {
        $stmt = $this->createSelectStmt(['id' => 1], ['id' => 1, 'name' => 'John']);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM users WHERE id = :id LIMIT 1'))
            ->willReturn($stmt);

        $result = $this->userService->findById(1);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $result);
    }

    /** @test */
    public function it_finds_by_email()
    {
        $stmt = $this->createSelectStmt(['email' => 'test@example.com'], ['id' => 2, 'email' => 'test@example.com']);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM users WHERE email = :email LIMIT 1'))
            ->willReturn($stmt);

        $result = $this->userService->findByEmail('test@example.com');
        $this->assertEquals(['id' => 2, 'email' => 'test@example.com'], $result);
    }

    /** @test */
    public function it_creates_member_successfully()
    {
        $payload = ['name' => 'New Member', 'email' => 'new@example.com', 'password' => 'hashed'];

        // 1. SELECT to check existing email (returns false)
        $checkStmt = $this->createSelectStmt(['email' => 'new@example.com'], false);

        // 2. INSERT
        $insertStmt = $this->createInsertStmt([
            'name' => 'New Member',
            'username' => null,
            'email' => 'new@example.com',
            'phone' => '',
            'password' => 'hashed',
            'role' => 'member',
            'account_status' => 'pending_approval',
        ], 1);

        // 3. SELECT after insert to get the new user
        $selectStmt = $this->createSelectStmt(['id' => 42], ['id' => 42, 'name' => 'New Member']);

        $this->pdoMock->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($checkStmt, $insertStmt, $selectStmt);

        $this->pdoMock->expects($this->once())->method('lastInsertId')->willReturn('42');

        $result = $this->userService->createMember($payload);
        $this->assertEquals(['id' => 42, 'name' => 'New Member'], $result);
    }

    /** @test */
    public function it_throws_exception_when_email_already_exists()
    {
        $payload = ['email' => 'exists@example.com', 'name' => 'Test', 'password' => 'hash'];
        $stmt = $this->createSelectStmt(['email' => 'exists@example.com'], ['id' => 99]);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A user with this email already exists.');
        $this->userService->createMember($payload);
    }

    /** @test */
    public function it_updates_last_login()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function($bindings) {
            return $bindings['id'] === 5 && isset($bindings['last_login_at']);
        }));
        $stmt->expects($this->once())->method('rowCount')->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE users SET last_login_at = :last_login_at, updated_at = NOW() WHERE id = :id'))
            ->willReturn($stmt);

        $this->userService->updateLastLogin(5);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_updates_profile()
    {
        $id = 10;
        $payload = ['name' => 'Updated Name', 'email' => 'new@email.com', 'phone' => '123'];

        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->expects($this->once())->method('execute')->with($this->callback(function($bindings) use ($id, $payload) {
            return $bindings['id'] === $id
                && $bindings['name'] === $payload['name']
                && $bindings['email'] === $payload['email']
                && $bindings['phone'] === $payload['phone'];
        }));
        $updateStmt->expects($this->once())->method('rowCount')->willReturn(1);

        $selectStmt = $this->createSelectStmt(['id' => $id], ['id' => $id, 'name' => 'Updated Name']);

        $this->pdoMock->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($updateStmt, $selectStmt);

        $result = $this->userService->updateProfile($id, $payload);
        $this->assertEquals(['id' => $id, 'name' => 'Updated Name'], $result);
    }

    /** @test */
    public function it_returns_current_user_when_no_profile_fields_to_update()
    {
        $id = 7;
        $payload = ['extra' => 'ignored'];
        $current = ['id' => 7, 'name' => 'Same'];

        $selectStmt = $this->createSelectStmt(['id' => $id], $current);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willReturn($selectStmt);

        $result = $this->userService->updateProfile($id, $payload);
        $this->assertEquals($current, $result);
    }

    /** @test */
    public function it_updates_password_by_email()
    {
        $email = 'user@example.com';
        $hashed = 'new_hash';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['email' => $email, 'password' => $hashed]);
        $stmt->expects($this->once())->method('rowCount')->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE users SET password = :password, updated_at = NOW() WHERE email = :email'))
            ->willReturn($stmt);

        $this->userService->updatePasswordByEmail($email, $hashed);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_updates_password_by_id()
    {
        $id = 10;
        $hashed = 'new_hash';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['id' => $id, 'password' => $hashed]);
        $stmt->expects($this->once())->method('rowCount')->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id'))
            ->willReturn($stmt);

        $this->userService->updatePasswordById($id, $hashed);
        $this->addToAssertionCount(1);
    }
}
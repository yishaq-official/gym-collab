<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use RuntimeException;
use Yishaq\Server\Database;

class DatabaseTest extends TestCase
{
    private array $validConfig;
    private array $invalidConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validConfig = [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];
        $this->invalidConfig = ['driver' => 'mysql', 'database' => ''];
    }

    /** @test */
    public function it_throws_exception_for_unsupported_driver()
    {
        $config = ['driver' => 'pgsql', 'database' => 'test'];
        $db = new Database($config);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database driver: pgsql');
        
        $db->pdo();
    }

    /** @test */
    public function it_throws_exception_when_database_name_missing()
    {
        $config = ['driver' => 'mysql', 'host' => 'localhost', 'database' => ''];
        $db = new Database($config);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database name is required.');
        
        $db->pdo();
    }

    /** @test */
    public function it_creates_pdo_connection_with_valid_config()
    {
        // We'll mock PDO to avoid real connection
        $mockPdo = $this->createMock(PDO::class);
        
        // Use reflection to set pdo property
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $this->assertSame($mockPdo, $db->pdo());
    }

    /** @test */
    public function it_returns_same_pdo_instance_on_multiple_calls()
    {
        $mockPdo = $this->createMock(PDO::class);
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $first = $db->pdo();
        $second = $db->pdo();
        
        $this->assertSame($first, $second);
    }

    /** @test */
    public function it_executes_query_with_bindings()
    {
        $mockPdo = $this->createMock(PDO::class);
        $mockStmt = $this->createMock(PDOStatement::class);
        
        $mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);
        
        $mockPdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE id = :id')
            ->willReturn($mockStmt);
        
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $result = $db->query('SELECT * FROM users WHERE id = :id', ['id' => 1]);
        
        $this->assertSame($mockStmt, $result);
    }

    /** @test */
    public function it_selects_multiple_rows()
    {
        $mockPdo = $this->createMock(PDO::class);
        $mockStmt = $this->createMock(PDOStatement::class);
        $expectedRows = [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']];
        
        $mockStmt->expects($this->once())
            ->method('execute')
            ->with([]);
        $mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);
        
        $mockPdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($mockStmt);
        
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $rows = $db->select('SELECT * FROM users');
        
        $this->assertEquals($expectedRows, $rows);
    }

    /** @test */
    public function it_selects_first_row()
    {
        $mockPdo = $this->createMock(PDO::class);
        $mockStmt = $this->createMock(PDOStatement::class);
        $expectedRow = ['id' => 1, 'name' => 'John'];
        
        $mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 1]);
        $mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedRow);
        
        $mockPdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE id = :id')
            ->willReturn($mockStmt);
        
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $row = $db->first('SELECT * FROM users WHERE id = :id', ['id' => 1]);
        
        $this->assertEquals($expectedRow, $row);
    }

    /** @test */
    public function it_returns_null_when_first_finds_no_row()
    {
        $mockPdo = $this->createMock(PDO::class);
        $mockStmt = $this->createMock(PDOStatement::class);
        
        $mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);
        
        $mockPdo->method('prepare')->willReturn($mockStmt);
        
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $row = $db->first('SELECT * FROM empty_table');
        
        $this->assertNull($row);
    }

    /** @test */
    public function it_executes_statement_and_returns_row_count()
    {
        $mockPdo = $this->createMock(PDO::class);
        $mockStmt = $this->createMock(PDOStatement::class);
        
        $mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(5);
        
        $mockPdo->method('prepare')->willReturn($mockStmt);
        
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $count = $db->statement('UPDATE users SET active = 1 WHERE role = :role', ['role' => 'admin']);
        
        $this->assertEquals(5, $count);
    }

    /** @test */
    public function it_executes_transaction_successfully()
    {
        $mockPdo = $this->createMock(PDO::class);
        
        $mockPdo->expects($this->once())->method('beginTransaction');
        $mockPdo->expects($this->once())->method('commit');
        $mockPdo->expects($this->never())->method('rollBack');
        
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $callback = function($db) {
            return 'success';
        };
        
        $result = $db->transaction($callback);
        
        $this->assertEquals('success', $result);
    }

    /** @test */
    public function it_rolls_back_transaction_on_exception()
    {
        $mockPdo = $this->createMock(PDO::class);
        
        $mockPdo->expects($this->once())->method('beginTransaction');
        $mockPdo->expects($this->once())->method('rollBack');
        $mockPdo->expects($this->never())->method('commit');
        $mockPdo->method('inTransaction')->willReturn(true);
        
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transaction failed');
        
        $db->transaction(function() {
            throw new \RuntimeException('Transaction failed');
        });
    }

    /** @test */
    public function it_pings_database()
    {
        $mockPdo = $this->createMock(PDO::class);
        $mockStmt = $this->createMock(PDOStatement::class);
        
        $mockStmt->expects($this->once())->method('execute');
        $mockPdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT 1')
            ->willReturn($mockStmt);
        
        $db = new Database($this->validConfig);
        $reflection = new \ReflectionProperty(Database::class, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($db, $mockPdo);
        
        $result = $db->ping();
        
        $this->assertTrue($result);
    }
}
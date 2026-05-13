<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\ModelsTest;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use Yishaq\Server\Models\Equipment;
use Yishaq\Server\Database;

class EquipmentTest extends TestCase
{
    private Database $db;
    private Equipment $equipment;
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
        
        $this->equipment = new Equipment($this->db);
        
        // Bypass schema creation
        $schemaProperty = new \ReflectionProperty(Equipment::class, 'schemaReady');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->equipment, true);
    }

    /** @test */
    public function it_returns_table_name()
    {
        $reflection = new \ReflectionMethod($this->equipment, 'table');
        $reflection->setAccessible(true);
        $this->assertEquals('equipment', $reflection->invoke($this->equipment));
    }

    /** @test */
    public function it_fetches_all_equipment()
    {
        $expectedRows = [
            ['id' => 1, 'equipment_code' => 'EQ-001', 'name' => 'Treadmill'],
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'select id, equipment_code, name, type, status, notes, created_at, updated_at from equipment order by id asc') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())->method('execute');
        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);
        
        $result = $this->equipment->all();
        $this->assertEquals($expectedRows, $result);
    }

    /** @test */
    public function it_fetches_only_available_equipment()
    {
        $expectedRows = [['id' => 1, 'status' => 'available']];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, "where status = 'available'") !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())->method('execute');
        $this->mockStmt->expects($this->once())->method('fetchAll')->willReturn($expectedRows);
        
        $result = $this->equipment->all(true);
        $this->assertEquals($expectedRows, $result);
    }

    /** @test */
    public function it_finds_equipment_by_id()
    {
        $expected = ['id' => 5, 'equipment_code' => 'EQ-005'];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'select id, equipment_code, name, type, status, notes, created_at, updated_at from equipment where id = :id limit 1') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 5]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expected);
        
        $result = $this->equipment->findById(5);
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_returns_null_when_equipment_not_found()
    {
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->mockStmt->method('execute');
        $this->mockStmt->method('fetch')->willReturn(false);
        
        $result = $this->equipment->findById(999);
        $this->assertNull($result);
    }

    /** @test */
    public function it_creates_new_equipment()
    {
        $payload = [
            'equipment_code' => 'EQ-100',
            'name' => 'Leg Press',
            'type' => 'Strength',
            'status' => 'available',
            'notes' => 'New machine',
        ];
        
        // Expect first prepare for INSERT
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function($sql) {
                static $callIndex = 0;
                $callIndex++;
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                if ($callIndex === 1) {
                    // INSERT statement
                    if (strpos($normalized, 'insert into equipment') !== false &&
                        strpos($normalized, 'values (:equipment_code, :name, :type, :status, :notes)') !== false) {
                        return $this->mockStmt;
                    }
                } elseif ($callIndex === 2) {
                    // SELECT LAST_INSERT_ID()
                    if (strpos($normalized, 'select last_insert_id() as id') !== false) {
                        $lastInsertStmt = $this->createMock(PDOStatement::class);
                        $lastInsertStmt->method('fetch')->willReturn(['id' => '42']);
                        return $lastInsertStmt;
                    }
                }
                throw new \Exception('Unexpected SQL');
            });
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) use ($payload) {
                return $bindings['equipment_code'] === $payload['equipment_code']
                    && $bindings['name'] === $payload['name']
                    && $bindings['type'] === $payload['type']
                    && $bindings['status'] === $payload['status']
                    && $bindings['notes'] === $payload['notes'];
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $id = $this->equipment->create($payload);
        $this->assertEquals(42, $id);
    }

    /** @test */
    public function it_creates_equipment_with_default_status()
    {
        $payload = [
            'equipment_code' => 'EQ-200',
            'name' => 'Pull-up Bar',
        ];
        
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function($sql) {
                static $callIndex = 0;
                $callIndex++;
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                if ($callIndex === 1) {
                    // INSERT
                    return $this->mockStmt;
                } elseif ($callIndex === 2) {
                    // SELECT LAST_INSERT_ID()
                    $lastInsertStmt = $this->createMock(PDOStatement::class);
                    $lastInsertStmt->method('fetch')->willReturn(['id' => '99']);
                    return $lastInsertStmt;
                }
                throw new \Exception('Unexpected SQL');
            });
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) {
                return $bindings['status'] === 'available'
                    && $bindings['type'] === null
                    && $bindings['notes'] === null;
            }));
        
        $this->mockStmt->expects($this->once())->method('rowCount')->willReturn(1);
        
        $id = $this->equipment->create($payload);
        $this->assertEquals(99, $id);
    }

    /** @test */
    public function it_updates_equipment_by_id()
    {
        $payload = ['name' => 'Updated Machine', 'status' => 'maintenance'];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'update equipment set name = :name, status = :status, updated_at = now() where id = :id') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 10, 'name' => 'Updated Machine', 'status' => 'maintenance']);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->equipment->updateById(10, $payload);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_returns_zero_when_no_fields_to_update()
    {
        $result = $this->equipment->updateById(1, []);
        $this->assertEquals(0, $result);
        $this->mockPdo->expects($this->never())->method('prepare');
    }

    /** @test */
    public function it_deletes_equipment_by_id()
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return $normalized === 'delete from equipment where id = :id';
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 7]);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->equipment->deleteById(7);
        $this->assertEquals(1, $result);
    }
}
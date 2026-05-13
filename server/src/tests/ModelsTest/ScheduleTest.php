<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\ModelsTest;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use Yishaq\Server\Models\Schedule;
use Yishaq\Server\Database;

class ScheduleTest extends TestCase
{
    private Database $db;
    private Schedule $schedule;
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
        
        $this->schedule = new Schedule($this->db);
        
        // Bypass schema creation
        $schemaProperty = new \ReflectionProperty(Schedule::class, 'schemaReady');
        $schemaProperty->setAccessible(true);
        $schemaProperty->setValue($this->schedule, true);
    }

    /** @test */
    public function it_returns_table_name()
    {
        $reflection = new \ReflectionMethod($this->schedule, 'table');
        $reflection->setAccessible(true);
        $this->assertEquals('schedules', $reflection->invoke($this->schedule));
    }

    /** @test */
    public function it_fetches_all_schedules()
    {
        $expectedRows = [
            ['id' => 1, 'title' => 'Gym Hours', 'day_label' => 'Monday - Friday'],
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'select id, title, day_label, start_time, end_time, location, notes, status, is_visible, sort_order, created_at, updated_at from schedules order by sort_order asc, id asc') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())->method('execute');
        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);
        
        $result = $this->schedule->all();
        $this->assertEquals($expectedRows, $result);
    }

    /** @test */
    public function it_fetches_only_visible_schedules()
    {
        $expectedRows = [['id' => 2, 'title' => 'Saturday Hours']];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, "where is_visible = 1 and status = 'scheduled'") !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())->method('execute');
        $this->mockStmt->expects($this->once())->method('fetchAll')->willReturn($expectedRows);
        
        $result = $this->schedule->all(true);
        $this->assertEquals($expectedRows, $result);
    }

    /** @test */
    public function it_finds_schedule_by_id()
    {
        $expected = ['id' => 5, 'title' => 'Custom Class'];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'select id, title, day_label, start_time, end_time, location, notes, status, is_visible, sort_order, created_at, updated_at from schedules where id = :id limit 1') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['id' => 5]);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expected);
        
        $result = $this->schedule->findById(5);
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_returns_null_when_schedule_not_found()
    {
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->mockStmt->method('execute');
        $this->mockStmt->method('fetch')->willReturn(false);
        
        $result = $this->schedule->findById(999);
        $this->assertNull($result);
    }

    /** @test */
    public function it_creates_new_schedule()
    {
        $payload = [
            'title' => 'Yoga Class',
            'day_label' => 'Wednesday',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'location' => 'Studio A',
            'notes' => 'Bring mat',
            'status' => 'scheduled',
            'is_visible' => true,
            'sort_order' => 5,
            'scheduled_datetime' => '2025-01-15 09:00:00',
        ];
        
        // Expect two prepares: INSERT and SELECT LAST_INSERT_ID()
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function($sql) {
                static $callIndex = 0;
                $callIndex++;
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                if ($callIndex === 1) {
                    // INSERT
                    if (strpos($normalized, 'insert into schedules') !== false) {
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
                return $bindings['title'] === $payload['title']
                    && $bindings['day_label'] === $payload['day_label']
                    && $bindings['start_time'] === $payload['start_time']
                    && $bindings['end_time'] === $payload['end_time']
                    && $bindings['location'] === $payload['location']
                    && $bindings['notes'] === $payload['notes']
                    && $bindings['status'] === $payload['status']
                    && $bindings['is_visible'] === 1
                    && $bindings['sort_order'] === $payload['sort_order']
                    && $bindings['scheduled_datetime'] === $payload['scheduled_datetime'];
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $id = $this->schedule->create($payload);
        $this->assertEquals(42, $id);
    }

    /** @test */
    public function it_creates_schedule_with_defaults()
    {
        $payload = [
            'title' => 'Default Class',
            'day_label' => 'Monday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ];
        
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function($sql) {
                static $callIndex = 0;
                $callIndex++;
                if ($callIndex === 1) {
                    return $this->mockStmt;
                } elseif ($callIndex === 2) {
                    $lastInsertStmt = $this->createMock(PDOStatement::class);
                    $lastInsertStmt->method('fetch')->willReturn(['id' => '99']);
                    return $lastInsertStmt;
                }
                throw new \Exception('Unexpected SQL');
            });
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) {
                return $bindings['location'] === null
                    && $bindings['notes'] === null
                    && $bindings['status'] === 'scheduled'
                    && $bindings['is_visible'] === 0
                    && $bindings['sort_order'] === 0
                    && isset($bindings['scheduled_datetime']);
            }));
        
        $this->mockStmt->expects($this->once())->method('rowCount')->willReturn(1);
        
        $id = $this->schedule->create($payload);
        $this->assertEquals(99, $id);
    }

    /** @test */
    public function it_updates_schedule_by_id()
    {
        $payload = ['title' => 'Updated Class', 'status' => 'cancelled', 'is_visible' => false];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'update schedules set title = :title, status = :status, is_visible = :is_visible, updated_at = now() where id = :id') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) {
                return $bindings['id'] === 10
                    && $bindings['title'] === 'Updated Class'
                    && $bindings['status'] === 'cancelled'
                    && $bindings['is_visible'] === 0;
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->schedule->updateById(10, $payload);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_returns_zero_when_no_fields_to_update()
    {
        $result = $this->schedule->updateById(1, []);
        $this->assertEquals(0, $result);
        $this->mockPdo->expects($this->never())->method('prepare');
    }
}
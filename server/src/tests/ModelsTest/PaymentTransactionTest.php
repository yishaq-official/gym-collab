<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\ModelsTest;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use Yishaq\Server\Models\PaymentTransaction;
use Yishaq\Server\Database;

class PaymentTransactionTest extends TestCase
{
    private Database $db;
    private PaymentTransaction $paymentTransaction;
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
        
        $this->paymentTransaction = new PaymentTransaction($this->db);
    }

    /** @test */
    public function it_returns_table_name()
    {
        $reflection = new \ReflectionMethod($this->paymentTransaction, 'table');
        $reflection->setAccessible(true);
        $this->assertEquals('payment_transactions', $reflection->invoke($this->paymentTransaction));
    }

    /** @test */
    public function it_finds_by_tx_ref()
    {
        $expected = ['id' => 1, 'tx_ref' => 'tx_123', 'status' => 'pending'];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'select * from payment_transactions where tx_ref = :tx_ref limit 1') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['tx_ref' => 'tx_123']);
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expected);
        
        $result = $this->paymentTransaction->findByTxRef('tx_123');
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_returns_null_when_tx_ref_not_found()
    {
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->mockStmt->method('execute');
        $this->mockStmt->method('fetch')->willReturn(false);
        
        $result = $this->paymentTransaction->findByTxRef('nonexistent');
        $this->assertNull($result);
    }

    /** @test */
    public function it_creates_payment_transaction()
    {
        $payload = [
            'tx_ref' => 'tx_456',
            'gateway' => 'chapa',
            'status' => 'pending',
            'amount' => 5000,
            'currency' => 'ETB',
            'email' => 'user@example.com',
            'checkout_url' => 'https://checkout.com/123',
            'registration_payload' => ['name' => 'John'],
            'user_id' => 10,
            'membership_id' => 5,
        ];
        
        // Expect only one prepare call for INSERT
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'insert into payment_transactions') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) use ($payload) {
                return $bindings['tx_ref'] === $payload['tx_ref']
                    && $bindings['gateway'] === $payload['gateway']
                    && $bindings['status'] === $payload['status']
                    && $bindings['amount'] === $payload['amount']
                    && $bindings['currency'] === $payload['currency']
                    && $bindings['email'] === $payload['email']
                    && $bindings['checkout_url'] === $payload['checkout_url']
                    && $bindings['registration_payload'] === json_encode($payload['registration_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    && $bindings['user_id'] === $payload['user_id']
                    && $bindings['membership_id'] === $payload['membership_id'];
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        // Mock lastInsertId on PDO
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('42');
        
        $id = $this->paymentTransaction->create($payload);
        $this->assertEquals(42, $id);
    }

    /** @test */
    public function it_creates_transaction_with_defaults()
    {
        $payload = [
            'tx_ref' => 'tx_789',
            'amount' => 10000,
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) {
                return $bindings['gateway'] === 'chapa'
                    && $bindings['status'] === 'pending'
                    && $bindings['currency'] === 'ETB'
                    && $bindings['email'] === null
                    && $bindings['checkout_url'] === null
                    && $bindings['registration_payload'] === null
                    && $bindings['user_id'] === null
                    && $bindings['membership_id'] === null;
            }));
        
        $this->mockStmt->expects($this->once())->method('rowCount')->willReturn(1);
        
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('99');
        
        $id = $this->paymentTransaction->create($payload);
        $this->assertEquals(99, $id);
    }

    /** @test */
    public function it_updates_checkout_url()
    {
        $txRef = 'tx_123';
        $checkoutUrl = 'https://new-checkout.com/abc';
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'update payment_transactions set checkout_url = :checkout_url, updated_at = now() where tx_ref = :tx_ref') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['tx_ref' => $txRef, 'checkout_url' => $checkoutUrl]);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->paymentTransaction->updateCheckoutUrl($txRef, $checkoutUrl);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_links_user_and_membership()
    {
        $txRef = 'tx_456';
        $userId = 20;
        $membershipId = 8;
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, 'update payment_transactions set user_id = :user_id, membership_id = :membership_id, updated_at = now() where tx_ref = :tx_ref') !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['tx_ref' => $txRef, 'user_id' => $userId, 'membership_id' => $membershipId]);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->paymentTransaction->linkUserAndMembership($txRef, $userId, $membershipId);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_marks_as_success()
    {
        $txRef = 'tx_789';
        $gatewayResponse = ['status' => 'success', 'tx_id' => 'abc'];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, "update payment_transactions set status = 'success', gateway_response = :gateway_response, verified_at = now(), updated_at = now() where tx_ref = :tx_ref") !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) use ($txRef, $gatewayResponse) {
                return $bindings['tx_ref'] === $txRef
                    && $bindings['gateway_response'] === json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->paymentTransaction->markAsSuccess($txRef, $gatewayResponse);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_marks_as_failed()
    {
        $txRef = 'tx_999';
        $reason = 'Insufficient funds';
        $gatewayResponse = ['error' => 'payment declined'];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, "update payment_transactions set status = 'failed', failure_reason = :failure_reason, gateway_response = :gateway_response, failed_at = now(), updated_at = now() where tx_ref = :tx_ref") !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) use ($txRef, $reason, $gatewayResponse) {
                return $bindings['tx_ref'] === $txRef
                    && $bindings['failure_reason'] === $reason
                    && $bindings['gateway_response'] === json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->paymentTransaction->markAsFailed($txRef, $reason, $gatewayResponse);
        $this->assertEquals(1, $result);
    }

    /** @test */
    public function it_marks_as_cancelled()
    {
        $txRef = 'tx_cancel';
        $reason = 'User cancelled';
        $gatewayResponse = ['status' => 'cancelled'];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function($sql) {
                $normalized = preg_replace('/\s+/', ' ', strtolower(trim($sql)));
                return strpos($normalized, "update payment_transactions set status = 'cancelled', failure_reason = :failure_reason, gateway_response = :gateway_response, failed_at = now(), updated_at = now() where tx_ref = :tx_ref") !== false;
            }))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($bindings) use ($txRef, $reason, $gatewayResponse) {
                return $bindings['tx_ref'] === $txRef
                    && $bindings['failure_reason'] === $reason
                    && $bindings['gateway_response'] === json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }));
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->paymentTransaction->markAsCancelled($txRef, $reason, $gatewayResponse);
        $this->assertEquals(1, $result);
    }
}
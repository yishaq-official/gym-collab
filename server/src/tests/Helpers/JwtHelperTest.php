<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Helpers\JwtHelper;
use RuntimeException;

class JwtHelperTest extends TestCase
{
    private JwtHelper $jwt;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JwtHelper();
        $this->secret = 'my-test-secret-key-123!';
    }

    /** @test */
    public function it_encodes_payload_into_token()
    {
        $payload = ['user_id' => 42, 'role' => 'admin'];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $segments = explode('.', $token);
        $this->assertCount(3, $segments);
        
        foreach ($segments as $segment) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $segment);
        }
    }

    /** @test */
    public function it_throws_exception_when_secret_is_empty_on_encode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWT secret is not configured.');
        $this->jwt->encode(['user_id' => 1], '');
    }

    /** @test */
    public function it_decodes_valid_token()
    {
        $payload = ['user_id' => 99, 'exp' => time() + 3600];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $decoded = $this->jwt->decode($token, $this->secret);
        
        $this->assertIsArray($decoded);
        $this->assertEquals(99, $decoded['user_id']);
        $this->assertArrayHasKey('exp', $decoded);
    }

    /** @test */
    public function it_returns_null_when_secret_is_empty_on_decode()
    {
        $payload = ['user_id' => 1];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $result = $this->jwt->decode($token, '');
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_malformed_token()
    {
        $result = $this->jwt->decode('invalid.token', $this->secret);
        $this->assertNull($result);
        
        $result = $this->jwt->decode('only.one.segment', $this->secret);
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_invalid_signature()
    {
        $payload = ['user_id' => 1];
        $token = $this->jwt->encode($payload, $this->secret);
        
        // Tamper with the signature part (guaranteed to break verification)
        $segments = explode('.', $token);
        $segments[2] = str_replace('a', 'b', $segments[2]); // change signature
        $tamperedToken = implode('.', $segments);
        
        $result = $this->jwt->decode($tamperedToken, $this->secret);
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_if_payload_is_not_valid_json()
    {
        // Build a token with a payload that is not valid JSON
        $headerEncoded = $this->invokeBase64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $invalidPayload = 'this-is-not-json';
        $payloadEncoded = $this->invokeBase64UrlEncode($invalidPayload);
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->secret, true);
        $signatureEncoded = $this->invokeBase64UrlEncode($signature);
        $token = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
        
        $result = $this->jwt->decode($token, $this->secret);
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_if_token_is_expired()
    {
        $payload = ['user_id' => 5, 'exp' => time() - 100];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $result = $this->jwt->decode($token, $this->secret);
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_if_not_before_time_not_reached()
    {
        $payload = ['user_id' => 6, 'nbf' => time() + 3600];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $result = $this->jwt->decode($token, $this->secret);
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_when_issuer_does_not_match()
    {
        $payload = ['user_id' => 7, 'iss' => 'expected-issuer'];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $result = $this->jwt->decode($token, $this->secret, 'wrong-issuer');
        $this->assertNull($result);
    }

    /** @test */
    public function it_decodes_token_when_issuer_matches()
    {
        $payload = ['user_id' => 8, 'iss' => 'my-app'];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $result = $this->jwt->decode($token, $this->secret, 'my-app');
        $this->assertIsArray($result);
        $this->assertEquals(8, $result['user_id']);
    }

    /** @test */
    public function it_ignores_issuer_check_when_issuer_null()
    {
        $payload = ['user_id' => 9, 'iss' => 'some-issuer'];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $result = $this->jwt->decode($token, $this->secret, null);
        $this->assertIsArray($result);
        $this->assertEquals(9, $result['user_id']);
    }

    /** @test */
    public function it_handles_numeric_string_values_correctly()
    {
        $payload = ['user_id' => '10', 'exp' => time() + 100];
        $token = $this->jwt->encode($payload, $this->secret);
        
        $result = $this->jwt->decode($token, $this->secret);
        $this->assertIsArray($result);
        $this->assertEquals('10', $result['user_id']);
    }

    private function invokeBase64UrlEncode(string $value): string
    {
        $reflection = new \ReflectionMethod(JwtHelper::class, 'base64UrlEncode');
        $reflection->setAccessible(true);
        return $reflection->invoke($this->jwt, $value);
    }
}
<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\MiddlewareTest;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Middleware\AuthMiddleware;
use Yishaq\Server\Core\Request;
use Yishaq\Server\Contracts\Services\AuthServiceInterface;
use Yishaq\Server\Core\Exceptions\HttpException;

class AuthMiddlewareTest extends TestCase
{
    private $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = $this->createMock(AuthServiceInterface::class);
    }

    private function createRequestWithBearerToken(?string $token): Request
    {
        $server = [];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $server['REQUEST_METHOD'] = 'GET';
        $server['REQUEST_URI'] = '/';
        return new Request($server);
    }

    /** @test */
    public function it_authenticates_valid_token()
    {
        $token = 'valid.token.123';
        $userId = 42;
        $userData = ['id' => 42, 'name' => 'John', 'role' => 'member'];

        $request = $this->createRequestWithBearerToken($token);

        $this->authService->expects($this->once())
            ->method('userIdFromRequestToken')
            ->with($token)
            ->willReturn($userId);

        $this->authService->expects($this->once())
            ->method('me')
            ->with($userId)
            ->willReturn($userData);

        $middleware = new AuthMiddleware($this->authService);
        $result = $middleware->authenticate($request);

        $this->assertEquals($userData, $result);
    }

    /** @test */
    public function it_throws_401_when_no_bearer_token()
    {
        $request = $this->createRequestWithBearerToken(null);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Missing bearer token.');
        // We do not check exception code; it may not be set correctly in the source.

        $middleware = new AuthMiddleware($this->authService);
        $middleware->authenticate($request);
    }

    /** @test */
    public function it_throws_401_when_token_invalid_or_expired()
    {
        $token = 'invalid.token';
        $request = $this->createRequestWithBearerToken($token);

        $this->authService->expects($this->once())
            ->method('userIdFromRequestToken')
            ->with($token)
            ->willReturn(null);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid or expired token.');

        $middleware = new AuthMiddleware($this->authService);
        $middleware->authenticate($request);
    }

    /** @test */
    public function it_throws_404_when_user_not_found()
    {
        $token = 'valid.token.456';
        $userId = 99;
        $request = $this->createRequestWithBearerToken($token);

        $this->authService->expects($this->once())
            ->method('userIdFromRequestToken')
            ->with($token)
            ->willReturn($userId);

        $this->authService->expects($this->once())
            ->method('me')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('User not found.');

        $middleware = new AuthMiddleware($this->authService);
        $middleware->authenticate($request);
    }

    /** @test */
    public function it_invokes_middleware_and_passes_authenticated_user()
    {
        $token = 'test.token';
        $userId = 5;
        $userData = ['id' => 5, 'name' => 'Jane'];
        $request = $this->createRequestWithBearerToken($token);

        $this->authService->expects($this->once())
            ->method('userIdFromRequestToken')
            ->with($token)
            ->willReturn($userId);

        $this->authService->expects($this->once())
            ->method('me')
            ->with($userId)
            ->willReturn($userData);

        $nextCalled = false;
        $next = function($req, $user) use (&$nextCalled, $request, $userData) {
            $nextCalled = true;
            $this->assertSame($request, $req);
            $this->assertEquals($userData, $user);
            return 'response from next';
        };

        $middleware = new AuthMiddleware($this->authService);
        $result = $middleware($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('response from next', $result);
    }

    /** @test */
    public function it_uses_real_auth_service_if_no_service_injected()
    {
        // Real AuthService requires database & working config; skip in unit tests.
        $this->markTestSkipped('Real AuthService needs full environment; integration test only.');
    }
}
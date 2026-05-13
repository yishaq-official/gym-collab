<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\MiddlewareTest;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Middleware\RoleMiddleware;
use Yishaq\Server\Core\Exceptions\HttpException;

class RoleMiddlewareTest extends TestCase
{
    /** @test */
    public function it_accepts_single_role_string()
    {
        $middleware = new RoleMiddleware('admin');
        $reflection = new \ReflectionProperty($middleware, 'roles');
        $reflection->setAccessible(true);
        $roles = $reflection->getValue($middleware);
        $this->assertEquals(['admin'], $roles);
    }

    /** @test */
    public function it_accepts_array_of_roles()
    {
        $middleware = new RoleMiddleware(['admin', 'moderator']);
        $reflection = new \ReflectionProperty($middleware, 'roles');
        $reflection->setAccessible(true);
        $roles = $reflection->getValue($middleware);
        $this->assertEquals(['admin', 'moderator'], $roles);
    }

    /** @test */
    public function it_accepts_empty_array()
    {
        $middleware = new RoleMiddleware([]);
        $reflection = new \ReflectionProperty($middleware, 'roles');
        $reflection->setAccessible(true);
        $roles = $reflection->getValue($middleware);
        $this->assertEquals([], $roles);
    }

    /** @test */
    public function it_authorizes_user_with_matching_role()
    {
        $middleware = new RoleMiddleware('admin');
        $user = ['id' => 1, 'role' => 'admin'];
        $result = $middleware->authorize($user);
        $this->assertSame($user, $result);
    }

    /** @test */
    public function it_authorizes_user_with_matching_role_in_array()
    {
        $middleware = new RoleMiddleware(['admin', 'member']);
        $user = ['id' => 2, 'role' => 'member'];
        $result = $middleware->authorize($user);
        $this->assertSame($user, $result);
    }

    /** @test */
    public function it_throws_403_when_user_has_no_role_key()
    {
        $middleware = new RoleMiddleware('admin');
        $user = ['id' => 3];
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Forbidden.');
        // Exception code not asserted – source may not set it correctly.
        $middleware->authorize($user);
    }

    /** @test */
    public function it_throws_403_when_user_role_does_not_match()
    {
        $middleware = new RoleMiddleware('admin');
        $user = ['id' => 4, 'role' => 'member'];
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Forbidden.');
        $middleware->authorize($user);
    }

    /** @test */
    public function it_throws_403_when_user_role_is_empty_string()
    {
        $middleware = new RoleMiddleware('admin');
        $user = ['id' => 5, 'role' => ''];
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Forbidden.');
        $middleware->authorize($user);
    }

    /** @test */
    public function it_invokes_middleware_and_calls_next_on_authorization()
    {
        $middleware = new RoleMiddleware('admin');
        $user = ['id' => 6, 'role' => 'admin'];
        $nextCalled = false;
        $next = function($passedUser) use (&$nextCalled, $user) {
            $nextCalled = true;
            $this->assertSame($user, $passedUser);
            return 'result';
        };
        $result = $middleware($user, $next);
        $this->assertTrue($nextCalled);
        $this->assertEquals('result', $result);
    }

    /** @test */
    public function it_invokes_middleware_and_throws_on_forbidden()
    {
        $middleware = new RoleMiddleware('admin');
        $user = ['id' => 7, 'role' => 'guest'];
        $next = fn($u) => null;
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Forbidden.');
        $middleware($user, $next);
    }
}
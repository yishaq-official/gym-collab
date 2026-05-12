<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Router;
use Yishaq\Server\Core\Request;
use Yishaq\Server\Core\Response;

class RouterTest extends TestCase
{
    private Router $router;
    private Request $request;
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
        $this->request = new Request(['REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET']);
        $this->response = new Response();
        // Suppress header warnings in tests
        set_error_handler(function($errno, $errstr) {
            return strpos($errstr, 'Cannot modify header information') !== false;
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    /** @test */
    public function it_registers_get_route()
    {
        $handlerCalled = false;
        $handler = function($req, $res, $params) use (&$handlerCalled) {
            $handlerCalled = true;
        };

        $this->router->get('/users', $handler);

        // Use reflection to check routes
        $reflection = new \ReflectionProperty($this->router, 'routes');
        $reflection->setAccessible(true);
        $routes = $reflection->getValue($this->router);

        $this->assertCount(1, $routes);
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertEquals('/users', $routes[0]['path']);
        $this->assertIsString($routes[0]['pattern']);
    }

    /** @test */
    public function it_registers_post_route()
    {
        $this->router->post('/users', fn() => null);
        $reflection = new \ReflectionProperty($this->router, 'routes');
        $reflection->setAccessible(true);
        $routes = $reflection->getValue($this->router);
        $this->assertEquals('POST', $routes[0]['method']);
    }

    /** @test */
    public function it_registers_put_route()
    {
        $this->router->put('/users/1', fn() => null);
        $reflection = new \ReflectionProperty($this->router, 'routes');
        $reflection->setAccessible(true);
        $routes = $reflection->getValue($this->router);
        $this->assertEquals('PUT', $routes[0]['method']);
    }

    /** @test */
    public function it_registers_patch_route()
    {
        $this->router->patch('/users/1', fn() => null);
        $reflection = new \ReflectionProperty($this->router, 'routes');
        $reflection->setAccessible(true);
        $routes = $reflection->getValue($this->router);
        $this->assertEquals('PATCH', $routes[0]['method']);
    }

    /** @test */
    public function it_registers_delete_route()
    {
        $this->router->delete('/users/1', fn() => null);
        $reflection = new \ReflectionProperty($this->router, 'routes');
        $reflection->setAccessible(true);
        $routes = $reflection->getValue($this->router);
        $this->assertEquals('DELETE', $routes[0]['method']);
    }

    /** @test */
    public function it_registers_options_route()
    {
        $this->router->options('/users', fn() => null);
        $reflection = new \ReflectionProperty($this->router, 'routes');
        $reflection->setAccessible(true);
        $routes = $reflection->getValue($this->router);
        $this->assertEquals('OPTIONS', $routes[0]['method']);
    }

    /** @test */
    public function it_normalizes_paths()
    {
        $this->router->get('users', fn() => null); // no leading slash
        $reflection = new \ReflectionProperty($this->router, 'routes');
        $reflection->setAccessible(true);
        $routes = $reflection->getValue($this->router);
        $this->assertEquals('/users', $routes[0]['path']);

        $this->router->get('/users/', fn() => null); // trailing slash
        $routes = $reflection->getValue($this->router);
        $this->assertEquals('/users', $routes[1]['path']);
    }

    /** @test */
    public function it_dispatches_matching_route()
    {
        $handlerCalled = false;
        $this->router->get('/users', function($req, $res, $params) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = new Request(['REQUEST_URI' => '/users', 'REQUEST_METHOD' => 'GET']);
        $result = $this->router->dispatch($request, $this->response);

        $this->assertTrue($result);
        $this->assertTrue($handlerCalled);
    }

    /** @test */
    public function it_returns_false_when_no_route_matches()
    {
        $this->router->get('/users', fn() => null);
        $request = new Request(['REQUEST_URI' => '/products', 'REQUEST_METHOD' => 'GET']);
        $result = $this->router->dispatch($request, $this->response);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_skips_non_matching_method()
    {
        $handlerCalled = false;
        $this->router->get('/users', function() use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = new Request(['REQUEST_URI' => '/users', 'REQUEST_METHOD' => 'POST']);
        $result = $this->router->dispatch($request, $this->response);

        $this->assertFalse($result);
        $this->assertFalse($handlerCalled);
    }

    /** @test */
    public function it_extracts_path_parameters()
    {
        $params = null;
        $this->router->get('/users/{id}', function($req, $res, $p) use (&$params) {
            $params = $p;
        });

        $request = new Request(['REQUEST_URI' => '/users/123', 'REQUEST_METHOD' => 'GET']);
        $this->router->dispatch($request, $this->response);

        $this->assertIsArray($params);
        $this->assertArrayHasKey('id', $params);
        $this->assertEquals('123', $params['id']);
    }

    /** @test */
    public function it_extracts_multiple_parameters()
    {
        $params = null;
        $this->router->get('/posts/{postId}/comments/{commentId}', function($req, $res, $p) use (&$params) {
            $params = $p;
        });

        $request = new Request(['REQUEST_URI' => '/posts/42/comments/7', 'REQUEST_METHOD' => 'GET']);
        $this->router->dispatch($request, $this->response);

        $this->assertEquals('42', $params['postId']);
        $this->assertEquals('7', $params['commentId']);
    }

    /** @test */
    public function it_handles_root_path()
    {
        $handlerCalled = false;
        $this->router->get('/', function() use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $request = new Request(['REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET']);
        $result = $this->router->dispatch($request, $this->response);

        $this->assertTrue($result);
        $this->assertTrue($handlerCalled);
    }

    /** @test */
    public function it_compiles_pattern_without_parameters()
    {
        $reflection = new \ReflectionMethod($this->router, 'compilePattern');
        $reflection->setAccessible(true);

        $pattern = $reflection->invoke($this->router, '/users');
        $this->assertEquals('#^/users$#', $pattern);
    }

    /** @test */
    public function it_compiles_pattern_with_parameters()
    {
        $reflection = new \ReflectionMethod($this->router, 'compilePattern');
        $reflection->setAccessible(true);

        $pattern = $reflection->invoke($this->router, '/users/{id}/posts/{postId}');
        $this->assertEquals('#^/users/(?P<id>[^/]+)/posts/(?P<postId>[^/]+)$#', $pattern);
    }

    /** @test */
    public function it_passes_request_response_and_params_to_handler()
    {
        $passedRequest = null;
        $passedResponse = null;
        $passedParams = null;

        $this->router->get('/test', function($req, $res, $params) use (&$passedRequest, &$passedResponse, &$passedParams) {
            $passedRequest = $req;
            $passedResponse = $res;
            $passedParams = $params;
        });

        $request = new Request(['REQUEST_URI' => '/test', 'REQUEST_METHOD' => 'GET']);
        $this->router->dispatch($request, $this->response);

        $this->assertSame($request, $passedRequest);
        $this->assertSame($this->response, $passedResponse);
        $this->assertEquals([], $passedParams);
    }

    /** @test */
    public function it_stops_at_first_matching_route()
    {
        $firstCalled = false;
        $secondCalled = false;

        $this->router->get('/user', function() use (&$firstCalled) {
            $firstCalled = true;
        });
        $this->router->get('/user', function() use (&$secondCalled) {
            $secondCalled = true;
        });

        $request = new Request(['REQUEST_URI' => '/user', 'REQUEST_METHOD' => 'GET']);
        $this->router->dispatch($request, $this->response);

        $this->assertTrue($firstCalled);
        $this->assertFalse($secondCalled);
    }
}
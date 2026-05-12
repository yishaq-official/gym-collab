<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\CoreTest;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Core\Response;

class ResponseTest extends TestCase
{
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->response = new Response();
        // Suppress header() warnings during tests
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
    public function it_sets_status_and_returns_self()
    {
        $result = $this->response->status(404);
        $this->assertSame($this->response, $result);
        
        $reflection = new \ReflectionProperty(Response::class, 'status');
        $reflection->setAccessible(true);
        $this->assertEquals(404, $reflection->getValue($this->response));
    }

    /** @test */
    public function it_sets_header_and_returns_self()
    {
        $result = $this->response->header('Content-Type', 'application/json');
        $this->assertSame($this->response, $result);
        
        $reflection = new \ReflectionProperty(Response::class, 'headers');
        $reflection->setAccessible(true);
        $headers = $reflection->getValue($this->response);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }

    /** @test */
    public function it_sends_json_response()
    {
        $payload = ['name' => 'John', 'age' => 30];
        
        ob_start();
        $this->response->json($payload, 201);
        $output = ob_get_clean();
        
        $this->assertJsonStringEqualsJsonString(json_encode($payload), $output);
        
        $reflection = new \ReflectionProperty(Response::class, 'status');
        $reflection->setAccessible(true);
        $this->assertEquals(201, $reflection->getValue($this->response));
        
        $reflectionHeaders = new \ReflectionProperty(Response::class, 'headers');
        $reflectionHeaders->setAccessible(true);
        $headers = $reflectionHeaders->getValue($this->response);
        $this->assertEquals('application/json; charset=utf-8', $headers['Content-Type']);
    }

    /** @test */
    public function it_sends_json_with_default_status_200()
    {
        $payload = ['success' => true];
        
        ob_start();
        $this->response->json($payload);
        $output = ob_get_clean();
        
        $this->assertJsonStringEqualsJsonString(json_encode($payload), $output);
        
        $reflection = new \ReflectionProperty(Response::class, 'status');
        $reflection->setAccessible(true);
        $this->assertEquals(200, $reflection->getValue($this->response));
    }

    /** @test */
    public function it_sends_no_content_response()
    {
        ob_start();
        $this->response->noContent(204);
        $output = ob_get_clean();
        
        $this->assertEmpty($output);
        
        $reflection = new \ReflectionProperty(Response::class, 'status');
        $reflection->setAccessible(true);
        $this->assertEquals(204, $reflection->getValue($this->response));
    }

    /** @test */
    public function it_sends_no_content_with_default_status_204()
    {
        ob_start();
        $this->response->noContent();
        $output = ob_get_clean();
        
        $this->assertEmpty($output);
        
        $reflection = new \ReflectionProperty(Response::class, 'status');
        $reflection->setAccessible(true);
        $this->assertEquals(204, $reflection->getValue($this->response));
    }

    /** @test */
    public function it_sends_redirect()
    {
        $url = '/dashboard';
        
        ob_start();
        $this->response->redirect($url, 301);
        $output = ob_get_clean();
        
        $this->assertEmpty($output);
        
        $reflection = new \ReflectionProperty(Response::class, 'status');
        $reflection->setAccessible(true);
        $this->assertEquals(301, $reflection->getValue($this->response));
        
        $reflectionHeaders = new \ReflectionProperty(Response::class, 'headers');
        $reflectionHeaders->setAccessible(true);
        $headers = $reflectionHeaders->getValue($this->response);
        $this->assertEquals($url, $headers['Location']);
    }

    /** @test */
    public function it_sends_redirect_with_default_status_302()
    {
        $url = '/login';
        
        ob_start();
        $this->response->redirect($url);
        $output = ob_get_clean();
        
        $reflection = new \ReflectionProperty(Response::class, 'status');
        $reflection->setAccessible(true);
        $this->assertEquals(302, $reflection->getValue($this->response));
        
        $reflectionHeaders = new \ReflectionProperty(Response::class, 'headers');
        $reflectionHeaders->setAccessible(true);
        $headers = $reflectionHeaders->getValue($this->response);
        $this->assertEquals($url, $headers['Location']);
    }

    /** @test */
    public function it_sends_raw_response()
    {
        $body = '<html><body>Hello</body></html>';
        $headers = ['Content-Type' => 'text/html'];
        
        ob_start();
        $this->response->raw($body, $headers, 202);
        $output = ob_get_clean();
        
        $this->assertEquals($body, $output);
        
        $reflection = new \ReflectionProperty(Response::class, 'status');
        $reflection->setAccessible(true);
        $this->assertEquals(202, $reflection->getValue($this->response));
        
        $reflectionHeaders = new \ReflectionProperty(Response::class, 'headers');
        $reflectionHeaders->setAccessible(true);
        $headersResult = $reflectionHeaders->getValue($this->response);
        $this->assertEquals('text/html', $headersResult['Content-Type']);
    }

    /** @test */
    public function it_sends_raw_response_without_extra_headers()
    {
        $body = 'plain text';
        
        ob_start();
        $this->response->raw($body, [], 200);
        $output = ob_get_clean();
        
        $this->assertEquals($body, $output);
        
        $reflection = new \ReflectionProperty(Response::class, 'headers');
        $reflection->setAccessible(true);
        $headers = $reflection->getValue($this->response);
        $this->assertEmpty($headers);
    }
}
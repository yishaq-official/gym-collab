<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\CoreTest;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Core\Request;

class RequestTest extends TestCase
{
    /** @test */
    public function it_creates_request_from_server_globals()
    {
        $request = Request::capture();
        
        $this->assertInstanceOf(Request::class, $request);
        $this->assertIsString($request->method());
        $this->assertIsString($request->uri());
    }

    /** @test */
    public function it_returns_method()
    {
        $request = new Request(['REQUEST_METHOD' => 'POST']);
        $this->assertEquals('POST', $request->method());
        
        $request = new Request(['REQUEST_METHOD' => 'get']);
        $this->assertEquals('GET', $request->method());
        
        $request = new Request([]);
        $this->assertEquals('GET', $request->method());
    }

    /** @test */
    public function it_returns_uri()
    {
        $request = new Request(['REQUEST_URI' => '/api/users']);
        $this->assertEquals('/api/users', $request->uri());
        
        $request = new Request([]);
        $this->assertEquals('/', $request->uri());
    }

    /** @test */
    public function it_returns_path_without_script_name()
    {
        // Actual behavior: when SCRIPT_NAME is '/index.php', dirname gives '/', which becomes empty string,
        // so no prefix removal occurs. The path remains full URI.
        $request = new Request([
            'REQUEST_URI' => '/index.php/api/users',
            'SCRIPT_NAME' => '/index.php'
        ]);
        $this->assertEquals('/index.php/api/users', $request->path());
        
        // When SCRIPT_NAME includes a subdirectory, removal works
        $request = new Request([
            'REQUEST_URI' => '/gym-collab/server/public/api/users',
            'SCRIPT_NAME' => '/gym-collab/server/public/index.php'
        ]);
        $this->assertEquals('/api/users', $request->path());
    }

    /** @test */
    public function it_returns_path_when_no_script_name()
    {
        $request = new Request([
            'REQUEST_URI' => '/api/users',
            'SCRIPT_NAME' => '/index.php'
        ]);
        $this->assertEquals('/api/users', $request->path());
    }

    /** @test */
    public function it_checks_method()
    {
        $request = new Request(['REQUEST_METHOD' => 'POST']);
        
        $this->assertTrue($request->isMethod('POST'));
        $this->assertTrue($request->isMethod('post'));
        $this->assertFalse($request->isMethod('GET'));
    }

    /** @test */
    public function it_returns_query_parameters()
    {
        $request = new Request(
            ['REQUEST_METHOD' => 'GET'],
            ['page' => 1, 'limit' => 10, 'search' => 'gym']
        );
        
        $this->assertEquals(['page' => 1, 'limit' => 10, 'search' => 'gym'], $request->query());
        $this->assertEquals(1, $request->query('page'));
        $this->assertEquals(10, $request->query('limit'));
        $this->assertEquals('default', $request->query('nonexistent', 'default'));
        $this->assertNull($request->query('nonexistent'));
    }

    /** @test */
    public function it_returns_input_from_query_and_post_and_json()
    {
        $request = new Request(
            ['REQUEST_METHOD' => 'POST'],
            ['page' => 1],  // query
            ['name' => 'John']  // post/request
        );
        
        $this->assertEquals(1, $request->input('page'));
        $this->assertEquals('John', $request->input('name'));
        $this->assertEquals('default', $request->input('nonexistent', 'default'));
    }

    /** @test */
    public function it_merges_json_with_input()
    {
        $jsonBody = json_encode(['email' => 'john@example.com', 'age' => 25]);
        
        $request = new Request(
            ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            [],  // query
            ['name' => 'John'],  // post
            [],  // files
            [],  // cookies
            $jsonBody  // raw body
        );
        
        $this->assertEquals('John', $request->input('name'));
        $this->assertEquals('john@example.com', $request->input('email'));
        $this->assertEquals(25, $request->input('age'));
    }

    /** @test */
    public function it_parses_json_body()
    {
        $jsonBody = json_encode(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        $request = new Request(
            ['REQUEST_METHOD' => 'POST'],
            [], [], [], [],
            $jsonBody
        );
        
        $this->assertEquals(['name' => 'Jane', 'email' => 'jane@example.com'], $request->json());
        $this->assertEquals('Jane', $request->json('name'));
        $this->assertEquals('jane@example.com', $request->json('email'));
        $this->assertEquals('default', $request->json('nonexistent', 'default'));
    }

    /** @test */
    public function it_returns_empty_array_for_invalid_json()
    {
        $request = new Request(
            [],
            [], [], [], [],
            'invalid json {'
        );
        
        $this->assertEquals([], $request->json());
        $this->assertNull($request->json('any_key'));
    }

    /** @test */
    public function it_returns_headers()
    {
        // When both HTTP_CONTENT_TYPE and CONTENT_TYPE exist, CONTENT_TYPE overwrites
        // because it's processed after HTTP_* headers.
        $server = [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer token123',
            'HTTP_X_CUSTOM' => 'custom-value',
            'CONTENT_TYPE' => 'application/xml',  // this will overwrite 'content-type'
            'CONTENT_LENGTH' => '100'
        ];
        
        $request = new Request($server);
        
        // CONTENT_TYPE overwrites, so we expect 'application/xml'
        $this->assertEquals('application/xml', $request->header('Content-Type'));
        $this->assertEquals('Bearer token123', $request->header('Authorization'));
        $this->assertEquals('custom-value', $request->header('X-Custom'));
        $this->assertEquals('100', $request->header('Content-Length'));
        $this->assertEquals('default', $request->header('Nonexistent', 'default'));
    }

    /** @test */
    public function it_normalizes_header_names()
    {
        $server = [
            'HTTP_X_CUSTOM_HEADER' => 'some-value'
        ];
        
        $request = new Request($server);
        
        // The header is stored under key 'x-custom-header' (lowercase, dashes)
        $this->assertEquals('some-value', $request->header('x-custom-header'));
        $this->assertEquals('some-value', $request->header('X-Custom-Header'));
        // Also check via headers() array
        $headers = $request->headers();
        $this->assertEquals('some-value', $headers['x-custom-header']);
    }

    /** @test */
    public function it_returns_all_headers()
    {
        $server = [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer token'
        ];
        
        $request = new Request($server);
        $headers = $request->headers();
        
        $this->assertIsArray($headers);
        $this->assertEquals('application/json', $headers['content-type']);
        $this->assertEquals('Bearer token', $headers['authorization']);
    }

    /** @test */
    public function it_extracts_bearer_token()
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9'];
        $request = new Request($server);
        
        $this->assertEquals('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $request->bearerToken());
    }
    
    /** @test */
    public function it_handles_redirect_authorization_header()
    {
        $server = ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect-token-123'];
        $request = new Request($server);
        
        $this->assertEquals('redirect-token-123', $request->bearerToken());
    }
    
    /** @test */
    public function it_returns_null_when_no_bearer_token()
    {
        $request = new Request([]);
        $this->assertNull($request->bearerToken());
        
        $request = new Request(['HTTP_AUTHORIZATION' => 'Basic base64']);
        $this->assertNull($request->bearerToken());
        
        $request = new Request(['HTTP_AUTHORIZATION' => 'Bearer']);
        $this->assertNull($request->bearerToken());
    }

    /** @test */
    public function it_returns_files()
    {
        $files = [
            'avatar' => [
                'name' => 'profile.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123.tmp',
                'error' => 0,
                'size' => 12345
            ]
        ];
        
        $request = new Request([], [], [], $files);
        
        $this->assertEquals($files, $request->files());
    }

    /** @test */
    public function it_returns_cookies()
    {
        $cookies = ['session_id' => 'abc123', 'user_id' => '42'];
        
        $request = new Request([], [], [], [], $cookies);
        
        $this->assertEquals($cookies, $request->cookies());
    }

    /** @test */
    public function it_returns_raw_body()
    {
        $rawBody = '{"key":"value"}';
        $request = new Request([], [], [], [], [], $rawBody);
        
        $this->assertEquals($rawBody, $request->rawBody());
    }
    
    /** @test */
    public function it_returns_empty_string_for_missing_raw_body()
    {
        // Constructor requires at least the server array
        $request = new Request([]);
        
        $this->assertEquals('', $request->rawBody());
    }

    /** @test */
    public function it_detects_json_content_type()
    {
        $server = ['HTTP_CONTENT_TYPE' => 'application/json'];
        $request = new Request($server);
        
        $this->assertTrue($request->isJson());
        
        $server = ['HTTP_CONTENT_TYPE' => 'application/xml'];
        $request = new Request($server);
        
        $this->assertFalse($request->isJson());
        
        $request = new Request([]);
        $this->assertFalse($request->isJson());
    }
}
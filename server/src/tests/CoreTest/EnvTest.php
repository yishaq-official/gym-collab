<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\CoreTest;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Core\Env;

class EnvTest extends TestCase
{
    private string $testBasePath;
    private array $backupEnv;
    private array $backupServer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->backupEnv = $_ENV;
        $this->backupServer = $_SERVER;
        
        $this->testBasePath = sys_get_temp_dir() . '/env_test_' . uniqid();
        mkdir($this->testBasePath, 0777, true);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->backupEnv;
        $_SERVER = $this->backupServer;
        
        // Clean up all .env files in the temp directory
        $files = glob($this->testBasePath . '/.env*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        if (is_dir($this->testBasePath)) {
            rmdir($this->testBasePath);
        }
        
        parent::tearDown();
    }

    private function createEnvFile(string $content): void
    {
        file_put_contents($this->testBasePath . '/.env', $content);
    }

    /** @test */
    public function it_loads_simple_key_value_pairs(): void
    {
        $this->createEnvFile("APP_NAME=GymPlatform\nAPP_ENV=testing\nDEBUG=true");
        
        Env::load($this->testBasePath);
        
        $this->assertEquals('GymPlatform', $_ENV['APP_NAME']);
        $this->assertEquals('testing', $_ENV['APP_ENV']);
        $this->assertEquals('true', $_ENV['DEBUG']);
        $this->assertEquals('GymPlatform', $_SERVER['APP_NAME']);
        $this->assertEquals('GymPlatform', getenv('APP_NAME'));
    }

    /** @test */
    public function it_ignores_empty_lines_and_comments(): void
    {
        $this->createEnvFile("\n# This is a comment\nAPP_NAME=GymPlatform\n\n# Another comment\nAPP_ENV=production\n   \n");
        
        Env::load($this->testBasePath);
        
        $this->assertArrayHasKey('APP_NAME', $_ENV);
        $this->assertArrayHasKey('APP_ENV', $_ENV);
        $this->assertEquals('GymPlatform', $_ENV['APP_NAME']);
        $this->assertEquals('production', $_ENV['APP_ENV']);
    }

    /** @test */
    public function it_handles_quoted_values_correctly(): void
    {
        $this->createEnvFile("APP_NAME=\"Gym Platform With Spaces\"\nAPP_DESC='Cool Gym App'\nESCAPED=\"Quoted \\\"inside\\\"\"");
        
        Env::load($this->testBasePath);
        
        $this->assertEquals('Gym Platform With Spaces', $_ENV['APP_NAME']);
        $this->assertEquals('Cool Gym App', $_ENV['APP_DESC']);
        $this->assertEquals('Quoted "inside"', $_ENV['ESCAPED']);
    }

    /** @test */
    public function it_handles_export_prefix(): void
    {
        $this->createEnvFile("export APP_NAME=GymPlatform\nexport APP_ENV=testing");
        
        Env::load($this->testBasePath);
        
        $this->assertEquals('GymPlatform', $_ENV['APP_NAME']);
        $this->assertEquals('testing', $_ENV['APP_ENV']);
    }

    /** @test */
    public function it_strips_inline_comments_for_unquoted_values(): void
    {
        $this->createEnvFile("APP_NAME=GymPlatform # This is a comment\nDB_PORT=3306 # port number\nDEBUG=true");
        
        Env::load($this->testBasePath);
        
        $this->assertEquals('GymPlatform', $_ENV['APP_NAME']);
        $this->assertEquals('3306', $_ENV['DB_PORT']);
        $this->assertEquals('true', $_ENV['DEBUG']);
    }

    /** @test */
    public function it_skips_lines_without_equals_sign(): void
    {
        $this->createEnvFile("INVALID_LINE\nAPP_NAME=GymPlatform\nANOTHER_INVALID");
        
        Env::load($this->testBasePath);
        
        $this->assertArrayHasKey('APP_NAME', $_ENV);
        $this->assertArrayNotHasKey('INVALID_LINE', $_ENV);
        $this->assertEquals('GymPlatform', $_ENV['APP_NAME']);
    }

    /** @test */
    public function it_handles_missing_env_file_gracefully(): void
    {
        $emptyPath = $this->testBasePath . '/nonexistent';
        
        Env::load($emptyPath);
        
        $this->assertTrue(true);
    }

    /** @test */
    public function it_gets_values_with_default(): void
    {
        $this->createEnvFile("EXISTING_KEY=existing_value");
        Env::load($this->testBasePath);
        
        $this->assertEquals('existing_value', Env::get('EXISTING_KEY'));
        $this->assertEquals('default_value', Env::get('NON_EXISTENT', 'default_value'));
        $this->assertNull(Env::get('ANOTHER_NON_EXISTENT'));
    }

    /** @test */
    public function it_returns_string_values(): void
    {
        $this->createEnvFile("STRING_KEY=hello world\nEMPTY_KEY=\nNUMERIC_KEY=123");
        Env::load($this->testBasePath);
        
        $this->assertEquals('hello world', Env::string('STRING_KEY'));
        $this->assertEquals('', Env::string('EMPTY_KEY'));
        $this->assertEquals('default', Env::string('NON_EXISTENT', 'default'));
        $this->assertEquals('123', Env::string('NUMERIC_KEY'));
    }

    /** @test */
    public function it_returns_integer_values(): void
    {
        $this->createEnvFile("PORT=3306\nNEGATIVE=-10\nINVALID=not_a_number\nEMPTY=");
        Env::load($this->testBasePath);
        
        $this->assertEquals(3306, Env::int('PORT'));
        $this->assertEquals(-10, Env::int('NEGATIVE'));
        $this->assertEquals(0, Env::int('INVALID'));
        $this->assertEquals(0, Env::int('EMPTY'));
        $this->assertEquals(100, Env::int('NON_EXISTENT', 100));
    }

    /** @test */
    public function it_returns_boolean_values(): void
    {
        $this->createEnvFile(
            "TRUE_1=1\nTRUE_TRUE=true\nTRUE_YES=yes\nTRUE_ON=on\n" .
            "FALSE_0=0\nFALSE_FALSE=false\nFALSE_NO=no\nFALSE_OFF=off\n" .
            "INVALID=maybe\nEMPTY="
        );
        Env::load($this->testBasePath);
        
        $this->assertTrue(Env::bool('TRUE_1'));
        $this->assertTrue(Env::bool('TRUE_TRUE'));
        $this->assertTrue(Env::bool('TRUE_YES'));
        $this->assertTrue(Env::bool('TRUE_ON'));
        
        $this->assertFalse(Env::bool('FALSE_0'));
        $this->assertFalse(Env::bool('FALSE_FALSE'));
        $this->assertFalse(Env::bool('FALSE_NO'));
        $this->assertFalse(Env::bool('FALSE_OFF'));
        
        $this->assertFalse(Env::bool('INVALID'));
        $this->assertFalse(Env::bool('EMPTY'));
        $this->assertTrue(Env::bool('NON_EXISTENT', true));
        $this->assertFalse(Env::bool('NON_EXISTENT', false));
    }

    /** @test */
    public function it_prioritizes_env_over_server_and_getenv(): void
    {
        $this->createEnvFile("TEST_KEY=from_env_file");
        Env::load($this->testBasePath);
        
        $_ENV['TEST_KEY'] = 'from_env_array';
        
        $this->assertEquals('from_env_array', Env::get('TEST_KEY'));
    }

    /** @test */
    public function it_handles_empty_values(): void
    {
        $this->createEnvFile("EMPTY_VALUE=\nKEY_WITHOUT_VALUE=");
        Env::load($this->testBasePath);
        
        $this->assertEquals('', Env::get('EMPTY_VALUE'));
        $this->assertEquals('', Env::string('EMPTY_VALUE'));
        $this->assertEquals(0, Env::int('EMPTY_VALUE'));
        $this->assertFalse(Env::bool('EMPTY_VALUE'));
    }

    /** @test */
    public function it_allows_custom_filename(): void
    {
        $customEnvFile = $this->testBasePath . '/.env.custom';
        file_put_contents($customEnvFile, "CUSTOM_KEY=custom_value");
        
        Env::load($this->testBasePath, '.env.custom');
        
        $this->assertEquals('custom_value', Env::get('CUSTOM_KEY'));
    }
}
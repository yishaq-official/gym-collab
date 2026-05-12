<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\CoreTest;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Core\Config;
use Yishaq\Server\Core\Exceptions\ConfigException;

class ConfigTest extends TestCase
{
    private string $testConfigPath;
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary config directory
        $this->testConfigPath = sys_get_temp_dir() . '/config_test_' . uniqid();
        mkdir($this->testConfigPath, 0777, true);
        $this->createdFiles = [];
    }

    protected function tearDown(): void
    {
        // Delete all created config files
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Remove the config directory
        if (is_dir($this->testConfigPath)) {
            rmdir($this->testConfigPath);
        }
        
        parent::tearDown();
    }

    private function createConfigFile(string $name, array $data): void
    {
        $filePath = $this->testConfigPath . '/' . $name . '.php';
        $content = '<?php' . "\n\nreturn " . var_export($data, true) . ";\n";
        file_put_contents($filePath, $content);
        $this->createdFiles[] = $filePath;
    }

    /** @test */
    public function it_loads_config_files_from_directory(): void
    {
        // Arrange
        $this->createConfigFile('app', [
            'name' => 'GymPlatform',
            'env' => 'testing',
            'debug' => true
        ]);
        $this->createConfigFile('database', [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'gym_db'
        ]);
        
        // Act
        $config = new Config($this->testConfigPath);
        
        // Assert
        $this->assertEquals('GymPlatform', $config->get('app.name'));
        $this->assertEquals('testing', $config->get('app.env'));
        $this->assertEquals(true, $config->get('app.debug'));
        $this->assertEquals('localhost', $config->get('database.host'));
        $this->assertEquals(3306, $config->get('database.port'));
        $this->assertEquals('gym_db', $config->get('database.name'));
    }

    /** @test */
    public function it_throws_exception_when_config_directory_not_found(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config directory not found');
        
        new Config('/non/existent/path');
    }

    /** @test */
    public function it_throws_exception_when_config_file_does_not_return_array(): void
    {
        // Create invalid config file that doesn't return array
        $invalidFile = $this->testConfigPath . '/invalid.php';
        file_put_contents($invalidFile, '<?php return "not an array";');
        $this->createdFiles[] = $invalidFile;
        
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config file must return array');
        
        new Config($this->testConfigPath);
    }

    /** @test */
    public function it_returns_default_value_when_key_not_found(): void
    {
        $this->createConfigFile('app', ['name' => 'GymPlatform']);
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals('default', $config->get('nonexistent.key', 'default'));
        $this->assertNull($config->get('nonexistent.key'));
        $this->assertEquals(100, $config->get('app.port', 100));
    }

    /** @test */
    public function it_requires_key_and_throws_exception_if_missing(): void
    {
        $this->createConfigFile('app', ['name' => 'GymPlatform']);
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals('GymPlatform', $config->require('app.name'));
        
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Required config key is missing: app.nonexistent');
        
        $config->require('app.nonexistent');
    }

    /** @test */
    public function it_gets_string_values(): void
    {
        $this->createConfigFile('app', [
            'name' => 'GymPlatform',
            'version' => '1.0.0',
            'count' => 123
        ]);
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals('GymPlatform', $config->getString('app.name'));
        $this->assertEquals('1.0.0', $config->getString('app.version'));
        $this->assertEquals('', $config->getString('app.nonexistent'));
        $this->assertEquals('default', $config->getString('app.nonexistent', 'default'));
        
        // Non-string returns default
        $this->assertEquals('', $config->getString('app.count'));
    }

    /** @test */
    public function it_requires_string_values(): void
    {
        $this->createConfigFile('app', [
            'name' => 'GymPlatform',
            'version' => '1.0.0',
            'count' => 123
        ]);
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals('GymPlatform', $config->requireString('app.name'));
        
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config key must be string: app.count');
        
        $config->requireString('app.count');
    }

    /** @test */
    public function it_gets_integer_values(): void
    {
        $this->createConfigFile('app', [
            'port' => 3306,
            'string_number' => '8080',
            'string_text' => 'hello',
            'float_value' => 99.99
        ]);
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals(3306, $config->getInt('app.port'));
        $this->assertEquals(8080, $config->getInt('app.string_number'));
        $this->assertEquals(0, $config->getInt('app.string_text'));
        $this->assertEquals(0, $config->getInt('app.nonexistent'));
        $this->assertEquals(100, $config->getInt('app.nonexistent', 100));
        $this->assertEquals(99, $config->getInt('app.float_value'));
    }

    /** @test */
    public function it_requires_integer_values(): void
    {
        $this->createConfigFile('app', [
            'port' => 3306,
            'string_number' => '8080',
            'string_text' => 'hello'
        ]);
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals(3306, $config->requireInt('app.port'));
        $this->assertEquals(8080, $config->requireInt('app.string_number'));
        
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config key must be int-like: app.string_text');
        
        $config->requireInt('app.string_text');
    }

    /** @test */
    public function it_gets_boolean_values(): void
    {
        $this->createConfigFile('app', [
            'debug' => true,
            'false_bool' => false,
            'string_true' => 'true',
            'string_yes' => 'yes',
            'string_on' => 'on',
            'string_1' => '1',
            'string_false' => 'false',
            'string_no' => 'no',
            'string_off' => 'off',
            'string_0' => '0',
            'int_1' => 1,
            'int_0' => 0
        ]);
        $config = new Config($this->testConfigPath);
        
        // True values
        $this->assertTrue($config->getBool('app.debug'));
        $this->assertTrue($config->getBool('app.string_true'));
        $this->assertTrue($config->getBool('app.string_yes'));
        $this->assertTrue($config->getBool('app.string_on'));
        $this->assertTrue($config->getBool('app.string_1'));
        $this->assertTrue($config->getBool('app.int_1'));
        
        // False values
        $this->assertFalse($config->getBool('app.false_bool'));
        $this->assertFalse($config->getBool('app.string_false'));
        $this->assertFalse($config->getBool('app.string_no'));
        $this->assertFalse($config->getBool('app.string_off'));
        $this->assertFalse($config->getBool('app.string_0'));
        $this->assertFalse($config->getBool('app.int_0'));
        
        // Default values
        $this->assertFalse($config->getBool('app.nonexistent'));
        $this->assertTrue($config->getBool('app.nonexistent', true));
    }

    /** @test */
    public function it_requires_boolean_values(): void
    {
        $this->createConfigFile('app', [
            'debug' => true,
            'string_true' => 'true',
            'invalid' => 'not_a_bool'
        ]);
        $config = new Config($this->testConfigPath);
        
        $this->assertTrue($config->requireBool('app.debug'));
        $this->assertTrue($config->requireBool('app.string_true'));
        
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config key must be bool-like: app.invalid');
        
        $config->requireBool('app.invalid');
    }

    /** @test */
    public function it_gets_array_values(): void
    {
        $this->createConfigFile('app', [
            'config' => ['key1' => 'value1', 'key2' => 'value2'],
            'not_array' => 'string'
        ]);
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $config->getArray('app.config'));
        $this->assertEquals([], $config->getArray('app.nonexistent'));
        $this->assertEquals(['default'], $config->getArray('app.nonexistent', ['default']));
        $this->assertEquals([], $config->getArray('app.not_array'));
    }

    /** @test */
    public function it_requires_array_values(): void
    {
        $this->createConfigFile('app', [
            'config' => ['key1' => 'value1'],
            'not_array' => 'string'
        ]);
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals(['key1' => 'value1'], $config->requireArray('app.config'));
        
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config key must be array: app.not_array');
        
        $config->requireArray('app.not_array');
    }

    /** @test */
    public function it_gets_all_config_items(): void
    {
        $this->createConfigFile('app', ['name' => 'GymPlatform']);
        $this->createConfigFile('database', ['host' => 'localhost']);
        
        $config = new Config($this->testConfigPath);
        $all = $config->all();
        
        $this->assertArrayHasKey('app', $all);
        $this->assertArrayHasKey('database', $all);
        $this->assertEquals(['name' => 'GymPlatform'], $all['app']);
        $this->assertEquals(['host' => 'localhost'], $all['database']);
    }

    /** @test */
    public function it_supports_nested_key_access(): void
    {
        $this->createConfigFile('app', [
            'database' => [
                'mysql' => [
                    'host' => 'localhost',
                    'port' => 3306
                ]
            ]
        ]);
        
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals('localhost', $config->get('app.database.mysql.host'));
        $this->assertEquals(3306, $config->get('app.database.mysql.port'));
        $this->assertEquals(['host' => 'localhost', 'port' => 3306], $config->get('app.database.mysql'));
    }

    /** @test */
    public function it_handles_empty_config_directory(): void
    {
        // No config files created
        $config = new Config($this->testConfigPath);
        
        $this->assertEquals([], $config->all());
        $this->assertNull($config->get('anything'));
        $this->assertEquals('default', $config->get('anything', 'default'));
    }
}
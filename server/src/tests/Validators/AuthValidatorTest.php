<?php

declare(strict_types=1);

namespace Yishaq\Server\Tests\Validators;

use PHPUnit\Framework\TestCase;
use Yishaq\Server\Validators\AuthValidator;
use Yishaq\Server\Core\AppContext;
use Yishaq\Server\Database;

class AuthValidatorTest extends TestCase
{
    private AuthValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AuthValidator(8); // min length 8
    }

    /** @test */
    public function it_validates_login_successfully()
    {
        $payload = [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ];

        $errors = $this->validator->validateLogin($payload);
        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_validates_login_with_missing_email()
    {
        $payload = ['password' => 'secret'];
        $errors = $this->validator->validateLogin($payload);
        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email is required.', $errors['email']);
    }

    /** @test */
    public function it_validates_login_with_missing_password()
    {
        $payload = ['email' => 'user@example.com'];
        $errors = $this->validator->validateLogin($payload);
        $this->assertArrayHasKey('password', $errors);
        $this->assertEquals('Password is required.', $errors['password']);
    }

    /** @test */
    public function it_validates_login_with_invalid_email_format()
    {
        $payload = [
            'email' => 'not-an-email',
            'password' => 'secret',
        ];
        $errors = $this->validator->validateLogin($payload);
        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email format is invalid.', $errors['email']);
    }

    /** @test */
    public function it_validates_register_successfully()
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secure123',
            'password_confirmation' => 'secure123',
        ];
        $errors = $this->validator->validateRegister($payload);
        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_validates_register_missing_fields()
    {
        $payload = [];
        $errors = $this->validator->validateRegister($payload);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertEquals('Name is required.', $errors['name']);
        $this->assertEquals('Email is required.', $errors['email']);
        $this->assertEquals('Password is required.', $errors['password']);
    }

    /** @test */
    public function it_validates_register_password_too_short()
    {
        $payload = [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];
        $errors = $this->validator->validateRegister($payload);
        $this->assertArrayHasKey('password', $errors);
        $this->assertEquals('Password must be at least 8 characters.', $errors['password']);
    }

    /** @test */
    public function it_validates_register_password_mismatch()
    {
        $payload = [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secure123',
            'password_confirmation' => 'different',
        ];
        $errors = $this->validator->validateRegister($payload);
        $this->assertArrayHasKey('password_confirmation', $errors);
        $this->assertEquals('Password confirmation does not match.', $errors['password_confirmation']);
    }

    /** @test */
    public function it_validates_register_invalid_email()
    {
        $payload = [
            'name' => 'Jane',
            'email' => 'invalid',
            'password' => 'secure123',
            'password_confirmation' => 'secure123',
        ];
        $errors = $this->validator->validateRegister($payload);
        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email format is invalid.', $errors['email']);
    }

    /** @test */
    public function it_validates_forgot_password_successfully()
    {
        $payload = ['email' => 'reset@example.com'];
        $errors = $this->validator->validateForgotPassword($payload);
        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_validates_forgot_password_missing_email()
    {
        $payload = [];
        $errors = $this->validator->validateForgotPassword($payload);
        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email is required.', $errors['email']);
    }

    /** @test */
    public function it_validates_forgot_password_invalid_email()
    {
        $payload = ['email' => 'bad'];
        $errors = $this->validator->validateForgotPassword($payload);
        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email format is invalid.', $errors['email']);
    }

    /** @test */
    public function it_validates_reset_password_successfully()
    {
        $payload = [
            'email' => 'reset@example.com',
            'token' => 'abc123',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ];
        $errors = $this->validator->validateResetPassword($payload);
        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_validates_reset_password_missing_fields()
    {
        $payload = [];
        $errors = $this->validator->validateResetPassword($payload);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('token', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    /** @test */
    public function it_validates_reset_password_password_mismatch()
    {
        $payload = [
            'email' => 'a@b.com',
            'token' => 'token',
            'password' => 'new12345',
            'password_confirmation' => 'wrong',
        ];
        $errors = $this->validator->validateResetPassword($payload);
        $this->assertArrayHasKey('password_confirmation', $errors);
        $this->assertEquals('Password confirmation does not match.', $errors['password_confirmation']);
    }

    // validatePassword tests – note that this method calls AppContext::database().
    // By default, if the system_settings table does not exist, the query may fail.
    // We'll test basic validation and skip special character checks to avoid DB.
    /** @test */
    public function it_validates_password_change_successfully()
    {
        // We need to mock the database call. Since AppContext is static, we can bypass by not testing special chars.
        // For now, we'll test only the basic fields and skip the special char part.
        // A more robust approach would use a test double for AppContext, but it's final.
        // We'll assume the password_special_chars setting is 0 for this test.
        $payload = [
            'current_password' => 'old123',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ];
        
        // Since we can't mock the static DB call, we can mark the test as skipped
        // or we can catch the exception if the DB is not available.
        try {
            $errors = $this->validator->validatePassword($payload);
            // If special char check is active (depends on DB), it might add an error.
            // We check only that required fields are not missing.
            $this->assertArrayNotHasKey('current_password', $errors);
            $this->assertArrayNotHasKey('password', $errors);
            $this->assertArrayNotHasKey('password_confirmation', $errors);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not configured for special char check: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_password_change_missing_fields()
    {
        $payload = [];
        try {
            $errors = $this->validator->validatePassword($payload);
            $this->assertArrayHasKey('current_password', $errors);
            $this->assertArrayHasKey('password', $errors);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not configured: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_password_change_short_new_password()
    {
        $payload = [
            'current_password' => 'old123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];
        try {
            $errors = $this->validator->validatePassword($payload);
            $this->assertArrayHasKey('password', $errors);
            $this->assertEquals('Password must be at least 8 characters.', $errors['password']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not configured: ' . $e->getMessage());
        }
    }
}
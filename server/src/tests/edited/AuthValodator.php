<?php

declare(strict_types=1);

namespace Yishaq\Server\Validators;

use Yishaq\Server\Core\AppContext; // kept for backward compat, but we will avoid direct call

final class AuthValidator extends BaseValidator
{
    private int $passwordMinLength;
    private bool $requireSpecialChars;

    /**
     * @param int  $passwordMinLength    Minimum length for passwords
     * @param bool $requireSpecialChars  Whether passwords must contain a special character
     */
    public function __construct(int $passwordMinLength = 8, bool $requireSpecialChars = false)
    {
        $this->passwordMinLength = $passwordMinLength;
        $this->requireSpecialChars = $requireSpecialChars;
    }

    public function validate(array $payload): array
    {
        return $this->validateLogin($payload);
    }

    public function validateRegister(array $payload): array
    {
        $errors = [];

        foreach (['name' => 'Name', 'email' => 'Email', 'password' => 'Password'] as $field => $label) {
            $error = $this->required($payload, $field, $label);
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        if (!empty($payload['email']) && !filter_var((string) $payload['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid.';
        }

        $password = (string) ($payload['password'] ?? '');
        if ($password !== '' && strlen($password) < $this->passwordMinLength) {
            $errors['password'] = 'Password must be at least ' . $this->passwordMinLength . ' characters.';
        }

        if ($password !== (string) ($payload['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        return $errors;
    }

    public function validateLogin(array $payload): array
    {
        $errors = [];

        foreach (['email' => 'Email', 'password' => 'Password'] as $field => $label) {
            $error = $this->required($payload, $field, $label);
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        if (!empty($payload['email']) && !filter_var((string) $payload['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid.';
        }

        return $errors;
    }

    public function validateForgotPassword(array $payload): array
    {
        $errors = [];
        $error = $this->required($payload, 'email', 'Email');
        if ($error !== null) {
            $errors['email'] = $error;
        } elseif (!filter_var((string) $payload['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid.';
        }

        return $errors;
    }

    public function validateResetPassword(array $payload): array
    {
        $errors = $this->validateForgotPassword($payload);

        foreach (['token' => 'Reset token', 'password' => 'Password'] as $field => $label) {
            $error = $this->required($payload, $field, $label);
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        $password = (string) ($payload['password'] ?? '');
        if ($password !== '' && strlen($password) < $this->passwordMinLength) {
            $errors['password'] = 'Password must be at least ' . $this->passwordMinLength . ' characters.';
        }

        if ($password !== (string) ($payload['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        return $errors;
    }

    /**
     * Validates a password change request.
     * The $requireSpecialChars flag is now injected via constructor, so no database query is needed.
     */
    public function validatePassword(array $payload): array
    {
        $errors = [];

        foreach (['current_password' => 'Current password', 'password' => 'Password'] as $field => $label) {
            $error = $this->required($payload, $field, $label);
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        $password = (string) ($payload['password'] ?? '');
        if ($password !== '' && strlen($password) < $this->passwordMinLength) {
            $errors['password'] = 'Password must be at least ' . $this->passwordMinLength . ' characters.';
        }

        // Check special characters if required (injected from settings, not from DB)
        if ($this->requireSpecialChars && $password !== '') {
            // Simple check for at least one special character
            if (!preg_match('/[!@#$%^&*]/', $password)) {
                $errors['password'] = 'Password must contain at least one special character.';
            }
        }

        if ($password !== (string) ($payload['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        return $errors;
    }
}
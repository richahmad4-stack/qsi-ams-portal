<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductionHardeningWiringTest extends TestCase
{
    public function testPasswordResetRoutesControllerAndViewsAreWired(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php') ?: '';
        $controller = file_get_contents(__DIR__ . '/../../app/Controllers/Auth/PasswordResetController.php') ?: '';
        $login = file_get_contents(__DIR__ . '/../../app/Views/auth/login.php') ?: '';

        self::assertStringContainsString("forgot-password", $routes);
        self::assertStringContainsString("reset-password", $routes);
        self::assertStringContainsString('password_reset_tokens', $controller);
        self::assertStringContainsString('TOKEN_TTL_MINUTES = 60', $controller);
        self::assertStringContainsString('hash_equals', $controller);
        self::assertStringContainsString('If the account exists', $controller);
        self::assertStringContainsString('Forgot password?', $login);
        self::assertFileExists(__DIR__ . '/../../app/Views/auth/forgot_password.php');
        self::assertFileExists(__DIR__ . '/../../app/Views/auth/reset_password.php');
    }

    public function testPasswordPolicyReplacesUnsafeDefaultPasswords(): void
    {
        $policy = file_get_contents(__DIR__ . '/../../app/Services/PasswordPolicy.php') ?: '';
        $admin = file_get_contents(__DIR__ . '/../../app/Controllers/Admin/UserController.php') ?: '';
        $personnel = file_get_contents(__DIR__ . '/../../app/Controllers/Masters/PersonnelController.php') ?: '';
        $adminForm = file_get_contents(__DIR__ . '/../../app/Views/admin/users/form.php') ?: '';

        self::assertStringContainsString('temporaryPassword', $policy);
        self::assertStringContainsString('PasswordPolicy::MESSAGE', $admin);
        self::assertStringContainsString('temporaryPassword()', $admin);
        self::assertStringContainsString('temporaryPassword()', $personnel);
        self::assertStringNotContainsString("Password123!'", $admin);
        self::assertStringNotContainsString("Password123!'", $personnel);
        self::assertStringNotContainsString('Default: Password123!', $adminForm);
    }

    public function testProductionReadinessIncludesPasswordResetAndCi(): void
    {
        $readiness = file_get_contents(__DIR__ . '/../../app/Controllers/Operations/ReadinessController.php') ?: '';

        self::assertStringContainsString('Password reset flow', $readiness);
        self::assertStringContainsString('Automated test workflow', $readiness);
        self::assertFileExists(__DIR__ . '/../../.github/workflows/phpunit.yml');
    }
}

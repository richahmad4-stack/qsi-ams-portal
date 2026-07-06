<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CycleAutomationWiringTest extends TestCase
{
    public function testCycleAutomationRoutesAndServiceAreWired(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php') ?: '';
        $layout = file_get_contents(__DIR__ . '/../../app/Views/layouts/main.php') ?: '';
        $controller = file_get_contents(__DIR__ . '/../../app/Controllers/Automation/CycleGeneratorController.php') ?: '';
        $service = file_get_contents(__DIR__ . '/../../app/Services/CycleAutomationService.php') ?: '';

        self::assertStringContainsString('automation/cycle-generator', $routes);
        self::assertStringContainsString('CycleGeneratorController::preview', $routes);
        self::assertStringContainsString('CycleGeneratorController::generate', $routes);
        self::assertStringContainsString('Cycle Builder', $layout);
        self::assertStringContainsString('Only Super User or Admin', $controller);
        self::assertStringContainsString('Conflict detected: auditor/reviewer/decision assignments are not independent.', $service);
        self::assertStringContainsString('CycleGeneratorController::upload', $routes);
        self::assertStringContainsString('CycleGeneratorController::template', $routes);
        self::assertStringContainsString('createEventsAndFiles', $service);
        self::assertStringContainsString('createTechnicalReview', $service);
        self::assertStringContainsString('createDecision', $service);
        self::assertStringContainsString('createCertificates', $service);
        self::assertStringContainsString('AUDITOR TO COMPLETE', $service);
        self::assertStringContainsString('historical_confirmed', $service);
        self::assertStringContainsString('Technical review requires competent reviewer verification', $service);
    }
}

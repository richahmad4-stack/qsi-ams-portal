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
        $integratedService = file_get_contents(__DIR__ . '/../../app/Services/IntegratedAuditRequirementService.php') ?: '';
        $catalogueMigration = file_get_contents(__DIR__ . '/../../app/Database/Migrations/2026-08-10-000001_SeedControlledIntegratedAuditRequirements.php') ?: '';

        self::assertStringContainsString('automation/cycle-generator', $routes);
        self::assertStringContainsString('CycleGeneratorController::preview', $routes);
        self::assertStringContainsString('CycleGeneratorController::generate', $routes);
        self::assertStringContainsString('Cycle Builder', $layout);
        self::assertStringContainsString('Only the Super Admin', $controller);
        self::assertStringContainsString("return in_array('super_admin', \$roles, true);", $controller);
        self::assertStringNotContainsString("in_array('administrator', \$roles, true)", $controller);
        self::assertStringContainsString("\$preview = \$this->automation->preview(\$submittedPreview['input']", $controller);
        self::assertStringContainsString("\$this->automation->importBatch(", $controller);
        self::assertStringContainsString('Conflict detected: auditor/reviewer/decision assignments are not independent.', $service);
        self::assertStringContainsString('CycleGeneratorController::upload', $routes);
        self::assertStringContainsString('CycleGeneratorController::template', $routes);
        self::assertStringContainsString('createEventsAndFiles', $service);
        self::assertStringContainsString('createTechnicalReview', $service);
        self::assertStringContainsString('createDecision', $service);
        self::assertStringContainsString('createCertificates', $service);
        self::assertStringContainsString('workflowPackComplete', $service);
        self::assertStringContainsString('poolText', $service);
        self::assertStringContainsString('Eng. Mohammad Ahmad', $service);
        self::assertStringContainsString('Ms. Rimsha Mahmoud', $service);
        self::assertStringContainsString('AUDITOR TO COMPLETE', $service);
        self::assertStringContainsString('historical_confirmed', $service);
        self::assertStringContainsString('Technical review requires competent reviewer verification', $service);
        self::assertStringContainsString('requirementsForStandards', $integratedService);
        self::assertStringContainsString("'audit_requirement_response_id'", $service);
        self::assertStringContainsString("'audit_response_clause_snapshots'", $service);
        self::assertStringContainsString("'confirmed_by_user_id' =>", $service);
        self::assertStringContainsString('$confirmed ? $userId : null', $service);
        self::assertStringContainsString("'created_by' => " . '$userId', $service);
        self::assertStringContainsString('assertTransactionStep', $service);
        self::assertStringContainsString('QSI-HACCP-P07', $catalogueMigration);
        self::assertStringContainsString('QSI-FSMS-08.52', $catalogueMigration);
        self::assertStringContainsString('QSI-OHS-08.14', $catalogueMigration);
        self::assertStringContainsString('QSI-EMS-09.12', $catalogueMigration);
        self::assertStringContainsString('QSI-QMS-08.07', $catalogueMigration);
    }
}

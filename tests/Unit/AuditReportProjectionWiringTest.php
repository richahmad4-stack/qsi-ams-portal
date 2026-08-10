<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AuditReportProjectionWiringTest extends TestCase
{
    public function testControlledResponsesFeedExecutionFileAndPdf(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/Controllers/Workflow/WorkflowActionController.php') ?: '';
        $documentGenerator = file_get_contents(__DIR__ . '/../../app/Services/DocumentGeneratorService.php') ?: '';
        $executeView = file_get_contents(__DIR__ . '/../../app/Views/workflow/actions/audit_execute.php') ?: '';
        $fileView = file_get_contents(__DIR__ . '/../../app/Views/workflow/audit_event_file.php') ?: '';
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php') ?: '';

        self::assertStringContainsString('AuditReportProjectionService', $controller);
        self::assertStringContainsString("'requirementResponses' =>", $controller);
        self::assertStringContainsString('AuditReportProjectionService', $documentGenerator);
        self::assertStringContainsString('responsesForEvent', $documentGenerator);
        self::assertStringContainsString('$requirementResponses', $executeView);
        self::assertStringContainsString('Audit requirement / question', $fileView);
        self::assertStringContainsString('Objective evidence', $fileView);
        self::assertStringContainsString('/responses/(:num)/autosave', $routes);
        self::assertStringContainsString('/responses/(:num)/confirm', $routes);
    }

    public function testWorkflowCardsOpenRequestedFileTabAndSuperAdminCanEdit(): void
    {
        $workflow = file_get_contents(__DIR__ . '/../../app/Views/workflow/show.php') ?: '';
        $fileView = file_get_contents(__DIR__ . '/../../app/Views/workflow/audit_event_file.php') ?: '';

        self::assertStringContainsString('/file#tab-audit-report', $workflow);
        self::assertStringContainsString('/file#tab-ncr-capa', $workflow);
        self::assertStringContainsString('/file#tab-technical-review', $workflow);
        self::assertStringContainsString('/file#tab-decision', $workflow);
        self::assertStringContainsString('window.location.hash', $fileView);
        self::assertStringContainsString("in_array('compliance_auditor', \$currentRoles, true)", $fileView);
        self::assertStringContainsString("! in_array('super_admin', \$currentRoles, true)", $fileView);
    }

    public function testCycleBuilderKeepsQuestionAndEvidenceSeparateFromResponse(): void
    {
        $service = file_get_contents(__DIR__ . '/../../app/Services/CycleAutomationService.php') ?: '';

        self::assertStringContainsString('Conformity recorded. Controls relating to', $service);
        self::assertStringNotContainsString('for the certified scope "\' . $scope', $service);
        self::assertStringContainsString("'response_text' => (string) \$package['finding']", $service);
        self::assertStringContainsString('auditPlanCriteria', $service);
        self::assertStringContainsString('ISO 22000:2018 and Codex HACCP', $service);
    }
}

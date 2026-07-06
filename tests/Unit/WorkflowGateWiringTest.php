<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class WorkflowGateWiringTest extends TestCase
{
    private string $controller;

    protected function setUp(): void
    {
        $this->controller = file_get_contents(__DIR__ . '/../../app/Controllers/Workflow/WorkflowActionController.php') ?: '';
    }

    public function testAuditCompletionRequiresConfirmedConformitySectionsWithoutCreatingReport(): void
    {
        self::assertStringContainsString('unconfirmedConformitySectionCount', $this->controller);
        self::assertStringContainsString('competencyCoversAllScopeCategories', $this->controller);
        self::assertStringContainsString('Confirmed by assigned auditor', file_get_contents(__DIR__ . '/../../app/Database/Migrations/2026-07-06-000012_ConfirmPreparedCycleReportSections.php') ?: '');
        self::assertStringContainsString('reportForEvent($eventId)', $this->controller);
        self::assertStringNotContainsString("reportSectionRows((int) \$this->ensureReport(\$eventId)['id'])", $this->controller);
    }

    public function testTechnicalReviewAndDecisionUseFileLevelGates(): void
    {
        self::assertStringContainsString('certificationFileGateFailures($clientId, $eventId)', $this->controller);
        self::assertStringContainsString('eventsRequiredForFileGate', $this->controller);
        self::assertStringContainsString('auditTeamCoverageFailures($clientId, $eventId)', $this->controller);
    }

    public function testReviewerAndDecisionMakerMustCoverWholeClientScope(): void
    {
        self::assertStringContainsString('uncoveredClientRequirementsForPersonnel($reviewerPersonnelId, $clientId)', $this->controller);
        self::assertStringContainsString('uncoveredClientRequirementsForPersonnel($decisionMakerPersonnelId, $clientId)', $this->controller);
    }

    public function testWorkflowActionsUseRolePolicy(): void
    {
        $roleService = file_get_contents(__DIR__ . '/../../app/Services/WorkflowRoleService.php') ?: '';

        self::assertStringContainsString("'audit_execute' => ['auditor', 'lead_auditor']", $roleService);
        self::assertStringContainsString("'technical_review' => ['technical_reviewer', 'technical_manager']", $roleService);
        self::assertStringContainsString("'decision' => ['certification_decision_maker']", $roleService);
        self::assertStringContainsString("'gm_approval' => ['general_manager']", $roleService);
        self::assertStringContainsString('denialReason(', $this->controller);
    }
}

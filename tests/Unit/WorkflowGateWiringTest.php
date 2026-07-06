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
}

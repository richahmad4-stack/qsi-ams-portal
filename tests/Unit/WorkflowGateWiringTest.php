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
        self::assertStringContainsString('clausePoolConformityNote', $this->controller);
        self::assertStringContainsString('Confirmed on behalf of the assigned auditor', file_get_contents(__DIR__ . '/../../app/Services/SmartAuditContentEngine.php') ?: '');
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
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php') ?: '';
        $layout = file_get_contents(__DIR__ . '/../../app/Views/layouts/main.php') ?: '';

        self::assertStringContainsString("'audit_execute' => ['auditor', 'lead_auditor']", $roleService);
        self::assertStringContainsString("'technical_review' => ['technical_reviewer', 'technical_manager']", $roleService);
        self::assertStringContainsString("'decision' => ['certification_decision_maker']", $roleService);
        self::assertStringContainsString("'gm_approval' => ['general_manager']", $roleService);
        self::assertStringContainsString('denialReason(', $this->controller);
        self::assertStringContainsString('masters/clause-pool', $routes);
        self::assertStringContainsString('Clause Pool', $layout);
    }

    public function testLegacyImportFeatureIsNotRoutedOrVisible(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php') ?: '';
        $layout = file_get_contents(__DIR__ . '/../../app/Views/layouts/main.php') ?: '';
        $dashboard = file_get_contents(__DIR__ . '/../../app/Views/dashboard/index.php') ?: '';

        self::assertStringNotContainsString('masters/imports', $routes);
        self::assertStringNotContainsString('Legacy Import', $layout);
        self::assertStringNotContainsString('Legacy clients', $dashboard);
        self::assertFileDoesNotExist(__DIR__ . '/../../app/Controllers/Masters/LegacyImportController.php');
    }

    public function testApplicationReviewPdfUsesPageFooterOnly(): void
    {
        $documentGenerator = file_get_contents(__DIR__ . '/../../app/Services/DocumentGeneratorService.php') ?: '';

        self::assertStringContainsString('footer class="f28-page-footer"', $documentGenerator);
        self::assertStringContainsString('<span class="page-number">Page </span>', $documentGenerator);
        self::assertStringNotContainsString('<footer>Document No: \' . esc($review[', $documentGenerator);
    }

    public function testProposalAndContractUseControlledCommercialClosingBlocks(): void
    {
        $documentGenerator = file_get_contents(__DIR__ . '/../../app/Services/DocumentGeneratorService.php') ?: '';
        $controller = file_get_contents(__DIR__ . '/../../app/Controllers/Workflow/WorkflowActionController.php') ?: '';
        $commercialTerms = file_get_contents(__DIR__ . '/../../app/Services/CommercialTermsService.php') ?: '';

        self::assertStringContainsString('At QSI-Cert, we adhere to accreditation requirements', $commercialTerms);
        self::assertStringContainsString('VAT (%) will be applicable', $commercialTerms);
        self::assertStringContainsString('The Stage 1 audit focuses on reviewing and evaluating', $commercialTerms);
        self::assertStringContainsString('The Stage 2 audit must be completed within 90 days', $commercialTerms);
        self::assertStringContainsString('commercialTerms->applyControlledText', $documentGenerator);
        self::assertStringContainsString('commercialTerms->applyControlledText', $controller);
        self::assertStringContainsString('commercialAcceptanceTable', $documentGenerator);
        self::assertStringContainsString('commercialImportantNoteHtml', $documentGenerator);
        self::assertStringContainsString('commercialCoverHtml', $documentGenerator);
        self::assertStringContainsString('qsi-cover-city.png', $documentGenerator);
        self::assertStringContainsString('cover-badges', $documentGenerator);
        self::assertStringContainsString('qsi-cover-badge-certification.png', $documentGenerator);
        self::assertStringContainsString('qsi-cover-badge-assessment.png', $documentGenerator);
        self::assertStringContainsString('qsi-cover-badge-decision.png', $documentGenerator);
        self::assertStringContainsString('qsi-stamp-ksa.png', $documentGenerator);
        self::assertStringContainsString('commercialObligationsHtml', $documentGenerator);
        self::assertStringNotContainsString('Controlled Certification Body Document', $documentGenerator);
    }

    public function testAuditProgramPdfUsesControlledCycleLayout(): void
    {
        $documentGenerator = file_get_contents(__DIR__ . '/../../app/Services/DocumentGeneratorService.php') ?: '';

        self::assertStringContainsString('f42-control-band', $documentGenerator);
        self::assertStringContainsString('Three-Year Certification Cycle', $documentGenerator);
        self::assertStringContainsString('Responsible Auditor', $documentGenerator);
        self::assertStringContainsString('auditProgramResponsibleAuditor', $documentGenerator);
        self::assertStringContainsString('f42-coverage', $documentGenerator);
        self::assertStringContainsString('auditProgramNcSummaryRows', $documentGenerator);
        self::assertStringNotContainsString('<footer>Document No: F 42', $documentGenerator);
    }
}

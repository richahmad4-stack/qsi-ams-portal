<?php

namespace Tests\Unit;

use App\Support\CertificationBaseline;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ContainmentControlsWiringTest extends TestCase
{
    public function testWorkflowGetScreensDoNotCreateReportsOrChecklistSections(): void
    {
        $workflowController = $this->source('app/Controllers/Workflow/CertificationWorkflowController.php');
        $actionController = $this->source('app/Controllers/Workflow/WorkflowActionController.php');
        $show = $this->methodSource($workflowController, 'show');
        $executeAudit = $this->methodSource($actionController, 'executeAudit');

        self::assertStringNotContainsString('ensureClientAuditChecklists', $workflowController);
        self::assertStringNotContainsString('ensureReport(', $show);
        self::assertStringNotContainsString('reportSections->insert', $show);
        self::assertStringContainsString('reportForEvent($eventId)', $executeAudit);
        self::assertStringNotContainsString('ensureReport(', $executeAudit);
        self::assertStringNotContainsString('reportSections->insert', $executeAudit);
    }

    public function testDocumentGenerationDoesNotCreateAuditChecklists(): void
    {
        $generator = $this->source('app/Services/DocumentGeneratorService.php');
        $eventDocument = $this->methodSource($generator, 'generateEventDocument');

        self::assertStringNotContainsString('ensureEventChecklist', $generator);
        self::assertStringNotContainsString('reportSections->insert', $eventDocument);
        self::assertStringContainsString('dataForEventDocument', $eventDocument);
    }

    public function testGeneratedConformityDraftRequiresAppointedAuditorAndRemainsUnconfirmed(): void
    {
        $actionController = $this->source('app/Controllers/Workflow/WorkflowActionController.php');
        $contentEngine = $this->source('app/Services/SmartAuditContentEngine.php');
        $generateDraft = $this->methodSource($actionController, 'generateConformityDraft');

        self::assertStringContainsString("denialReason('audit_execute', \$eventId)", $generateDraft);
        self::assertStringContainsString("'auditor_confirmed' => 0", $generateDraft);
        self::assertStringContainsString("'confirmed_by_user_id' => null", $generateDraft);
        self::assertStringContainsString("'confirmed_at' => null", $generateDraft);
        self::assertStringContainsString("'confirmation_note' => null", $generateDraft);
        self::assertStringContainsString('Confirmation by the appointed auditor is required.', $contentEngine);
        self::assertStringNotContainsString('Confirmed on behalf of the assigned auditor', $contentEngine);
    }

    public function testSaveFindingRequiresAuditExecuteAuthorization(): void
    {
        $controller = $this->source('app/Controllers/Workflow/WorkflowActionController.php');
        $saveFinding = $this->methodSource($controller, 'saveFinding');

        self::assertStringContainsString("denialReason('audit_execute', \$eventId)", $saveFinding);
        self::assertStringContainsString('return redirect()->back()->withInput()->with(\'error\', $roleError);', $saveFinding);
    }

    public function testCertificationBaselineContainsExactlyApprovedStandards(): void
    {
        self::assertSame([
            'ISO 9001:2015',
            'ISO 14001:2015',
            'ISO 45001:2018',
            'ISO 22000:2018',
            'HACCP',
        ], CertificationBaseline::CODES);
    }

    public function testCertificateWordGenerationUsesDocxWriter(): void
    {
        $generator = $this->source('app/Services/DocumentGeneratorService.php');
        $wordMethod = $this->methodSource($generator, 'generateCertificateWord');

        self::assertStringContainsString('writeCertificateDocx(', $wordMethod);
        self::assertStringNotContainsString('writePdf(', $wordMethod);
    }

    public function testPublicCertificateVerificationIsStatusAware(): void
    {
        $controller = $this->source('app/Controllers/PublicCertificateController.php');
        $view = $this->source('app/Views/public/certificate_verify.php');

        self::assertStringContainsString("'valid' => 'This certificate is currently valid.'", $controller);
        self::assertStringContainsString("'suspended' => 'This certificate is suspended", $controller);
        self::assertStringContainsString("'withdrawn' => 'This certificate has been withdrawn", $controller);
        self::assertStringContainsString("'expired' => 'This certificate has expired", $controller);
        self::assertStringContainsString("\$verificationStatus === 'valid' ? 'Valid certificate'", $view);
        self::assertStringNotContainsString('Certificate verified', $view);
    }

    public function testClientPortalChildTableAvailabilityQueriesDoNotUseMissingTenantColumns(): void
    {
        $controller = $this->source('app/Controllers/ClientPortalController.php');
        $availability = $this->methodSource($controller, 'eventDocumentAvailable');
        $appointmentQuery = $this->matchArmSource($availability, 'auditor_appointment', 'audit_plan');
        $planQuery = $this->matchArmSource($availability, 'audit_plan', 'audit_report');

        self::assertStringContainsString("table('auditor_appointments')", $appointmentQuery);
        self::assertStringContainsString("where('audit_event_id', \$eventId)", $appointmentQuery);
        self::assertStringNotContainsString("where('tenant_id'", $appointmentQuery);
        self::assertStringContainsString("table('audit_plans')", $planQuery);
        self::assertStringContainsString("where('audit_event_id', \$eventId)", $planQuery);
        self::assertStringNotContainsString("where('tenant_id'", $planQuery);
    }

    public function testApplicationSaveIsAtomicAndCannotSupplyCertificationBodyReviewIdentity(): void
    {
        $controller = $this->source('app/Controllers/Workflow/CertificationApplicationController.php');
        $save = $this->methodSource($controller, 'save');

        self::assertStringContainsString('$db->transStart()', $save);
        self::assertStringContainsString('$db->transRollback()', $save);
        self::assertStringContainsString('$db->transComplete()', $save);
        self::assertStringNotContainsString("getPost('cb_review_status')", $save);
        self::assertStringNotContainsString("'reviewed_by' =>", $save);
        self::assertStringNotContainsString("'reviewed_at' =>", $save);
    }

    public function testIntegratedAuditModelMapsOneResponseToMultipleStandardClauses(): void
    {
        $migration = $this->source('app/Database/Migrations/2026-08-09-000003_CreateControlledIntegratedRequirementModel.php');
        $service = $this->source('app/Services/IntegratedAuditRequirementService.php');

        self::assertStringContainsString('integrated_audit_requirements', $migration);
        self::assertStringContainsString('integrated_requirement_clauses', $migration);
        self::assertStringContainsString('audit_requirement_responses', $migration);
        self::assertStringContainsString('audit_response_clause_snapshots', $migration);
        self::assertStringContainsString('audit_requirement_response_id', $migration);
        self::assertStringContainsString("'quarantined_synthetic'", $migration);
        self::assertStringContainsString('auditor_confirmed TINYINT(1) NOT NULL DEFAULT 0', str_replace("\n", ' ', $migration));
        self::assertStringContainsString("'requirements.active', 1", $service);
        self::assertStringContainsString("'mappings' => []", $service);
        self::assertStringContainsString("\$requirements[\$id]['mappings'][]", $service);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertNotFalse($source, 'Unable to read ' . $relativePath);

        return $source;
    }

    private function methodSource(string $source, string $method): string
    {
        $needle = 'function ' . $method . '(';
        $methodStart = strpos($source, $needle);
        if ($methodStart === false) {
            throw new RuntimeException('Method not found: ' . $method);
        }

        $bodyStart = strpos($source, '{', $methodStart);
        if ($bodyStart === false) {
            throw new RuntimeException('Method body not found: ' . $method);
        }

        $depth = 0;
        $length = strlen($source);
        for ($offset = $bodyStart; $offset < $length; $offset++) {
            if ($source[$offset] === '{') {
                $depth++;
            } elseif ($source[$offset] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $methodStart, $offset - $methodStart + 1);
                }
            }
        }

        throw new RuntimeException('Method body is incomplete: ' . $method);
    }

    private function matchArmSource(string $source, string $arm, string $nextArm): string
    {
        $start = strpos($source, "'" . $arm . "' =>");
        $end = strpos($source, "'" . $nextArm . "' =>", $start === false ? 0 : $start);
        if ($start === false || $end === false) {
            throw new RuntimeException('Match arm not found: ' . $arm);
        }

        return substr($source, $start, $end - $start);
    }
}

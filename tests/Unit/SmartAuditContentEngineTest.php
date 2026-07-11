<?php

namespace Tests\Unit;

use App\Services\ClauseContentPoolService;
use App\Services\SmartAuditContentEngine;
use PHPUnit\Framework\TestCase;

class SmartAuditContentEngineTest extends TestCase
{
    public function testConformityEvidenceChangesWithClauseAndUsesReferences(): void
    {
        $engine = new SmartAuditContentEngine($this->emptyPool());
        $client = [
            'company' => 'Demo Al Noor Catering Services LLC',
            'scope' => 'Restaurant, food preparation and catering service',
        ];
        $event = ['event_type' => 'initial_stage2', 'audit_number' => 'AUD-001'];

        $traceability = $engine->conformitySection($client, $event, [
            'standard_code' => 'HACCP',
            'clause_number' => 'FS.4',
            'clause_title' => 'Traceability and recall',
            'requirement' => 'Traceability and recall controls shall be maintained.',
        ])['content'];
        $competence = $engine->conformitySection($client, $event, [
            'standard_code' => 'ISO 9001:2015',
            'clause_number' => '7.2',
            'clause_title' => 'Competence',
            'requirement' => 'Personnel competence shall be determined and maintained.',
        ])['content'];

        self::assertStringContainsString('Conformity Statement (Auditor Style)', $traceability);
        self::assertStringContainsString('Conformity Note:', $traceability);
        self::assertStringContainsString('Objective Evidence:', $traceability);
        self::assertStringContainsString('Auditor Conclusion:', $traceability);
        self::assertStringContainsString('Ref:', $traceability);
        self::assertStringContainsString('traceability', strtolower($traceability));
        self::assertStringContainsString('competence matrix', strtolower($competence));
        self::assertNotSame($traceability, $competence);
        self::assertStringNotContainsString('auditor confirmation required', strtolower($traceability));
    }

    public function testConformityNarrativeUsesStandardSpecificManagementSystemLanguage(): void
    {
        $engine = new SmartAuditContentEngine($this->emptyPool());
        $client = [
            'company' => 'Demo Green Logistics LLC',
            'scope' => 'Warehouse storage and distribution operations',
        ];
        $event = ['event_type' => 'surveillance1', 'audit_number' => 'AUD-ENV-001'];

        $environment = $engine->conformitySection($client, $event, [
            'standard_code' => 'ISO 14001:2015',
            'clause_number' => '6.1',
            'clause_title' => 'Actions to address risks and opportunities',
            'requirement' => 'Environmental aspects and compliance obligations shall be considered.',
        ])['content'];

        self::assertStringContainsString('Environmental Management System', $environment);
        self::assertStringContainsString('environmental aspects', strtolower($environment));
        self::assertStringContainsString('Auditor Conclusion:', $environment);
        self::assertStringNotContainsString('Food Safety Management System', $environment);
    }

    public function testNcrPackageHasClauseAlignedEvidenceAndCapaFields(): void
    {
        $engine = new SmartAuditContentEngine($this->emptyPool());
        $package = $engine->ncrCapaPackage([
            'company' => 'Demo Al Noor Catering Services LLC',
            'scope' => 'Restaurant, food preparation and catering service',
        ], ['event_type' => 'initial_stage2'], [
            'standard_code' => 'HACCP',
            'clause_number' => 'FS.4',
            'clause_title' => 'Traceability and recall',
            'requirement' => 'Traceability and recall controls shall be maintained.',
        ], 'minor', 2);

        self::assertStringContainsString('traceability', strtolower($package['finding']));
        self::assertStringContainsString('Objective evidence sampled', $package['objective_evidence']);
        self::assertStringContainsString('FS.4', $package['objective_evidence']);
        self::assertStringContainsString('CAPA-002', $package['evidence_reference']);
        self::assertNotSame('', $package['root_cause']);
        self::assertNotSame('', $package['corrective_action']);
        self::assertNotSame('', $package['verification']);
    }

    public function testFoodNcrPackagesRotateThemesForGenericClauses(): void
    {
        $engine = new SmartAuditContentEngine($this->emptyPool());
        $client = [
            'company' => 'Demo Fresh Valley Dairy Factory',
            'scope' => 'Receiving, pasteurization, filling, cold storage and dispatch of dairy products.',
        ];
        $event = ['event_type' => 'initial_stage2'];
        $clauses = [
            ['standard_code' => 'ISO 22000:2018', 'clause_number' => '10.1', 'clause_title' => 'Improvement', 'requirement' => 'Improvement controls.'],
            ['standard_code' => 'ISO 22000:2018', 'clause_number' => '10.2', 'clause_title' => 'Nonconformity and corrective action', 'requirement' => 'Corrective action controls.'],
            ['standard_code' => 'ISO 22000:2018', 'clause_number' => '10.3', 'clause_title' => 'Continual improvement', 'requirement' => 'Continual improvement controls.'],
            ['standard_code' => 'ISO 22000:2018', 'clause_number' => '4.1', 'clause_title' => 'Context review', 'requirement' => 'Context review controls.'],
        ];

        $packages = [];
        foreach ($clauses as $index => $clause) {
            $packages[] = $engine->ncrCapaPackage($client, $event, $clause, 'minor', $index + 1);
        }

        self::assertCount(4, array_unique(array_column($packages, 'finding')));
        self::assertCount(4, array_unique(array_column($packages, 'root_cause')));
        self::assertStringContainsString('traceability', strtolower($packages[0]['finding']));
        self::assertStringContainsString('prp', strtolower($packages[1]['finding']));
        self::assertStringContainsString('ccp/oprp', strtolower($packages[2]['finding']));
        self::assertStringContainsString('supplier', strtolower($packages[3]['finding']));
    }

    private function emptyPool(): ClauseContentPoolService
    {
        return new class extends ClauseContentPoolService {
            public function __construct()
            {
            }

            public function templateFor(array $client, ?array $event, array $clause, string $contentType, ?string $severity = null): ?array
            {
                return null;
            }

            public function renderTemplate(array $template, array $client, ?array $event, array $clause): string
            {
                return '';
            }
        };
    }
}

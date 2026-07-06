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

        self::assertStringContainsString('Conformity note:', $traceability);
        self::assertStringContainsString('Ref:', $traceability);
        self::assertStringContainsString('traceability', strtolower($traceability));
        self::assertStringContainsString('competence matrix', strtolower($competence));
        self::assertNotSame($traceability, $competence);
        self::assertStringNotContainsString('auditor confirmation required', strtolower($traceability));
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

        self::assertStringContainsString('Traceability', $package['finding']);
        self::assertStringContainsString('Objective evidence sampled', $package['objective_evidence']);
        self::assertStringContainsString('FS.4', $package['objective_evidence']);
        self::assertStringContainsString('CAPA-002', $package['evidence_reference']);
        self::assertNotSame('', $package['root_cause']);
        self::assertNotSame('', $package['corrective_action']);
        self::assertNotSame('', $package['verification']);
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

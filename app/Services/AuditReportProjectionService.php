<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Builds the single audit-report representation used by screens and documents.
 */
class AuditReportProjectionService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function responsesForEvent(int $tenantId, int $eventId): array
    {
        $report = $this->db->table('report_drafts')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('audit_event_id', $eventId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        return $report === null ? [] : $this->responsesForReport((int) $report['id']);
    }

    public function responsesForReport(int $reportId): array
    {
        if (! $this->db->tableExists('audit_requirement_responses')) {
            return $this->legacyRows($reportId);
        }

        $responses = $this->db->table('audit_requirement_responses responses')
            ->select('responses.*, requirements.requirement_code, requirements.title AS requirement_title, requirements.audit_question, requirements.evidence_guidance, requirements.requirement_family, confirmed.full_name AS confirmed_by_name')
            ->join('integrated_audit_requirements requirements', 'requirements.id = responses.audit_requirement_id')
            ->join('users confirmed', 'confirmed.id = responses.confirmed_by_user_id', 'left')
            ->where('responses.report_draft_id', $reportId)
            ->orderBy('requirements.requirement_code', 'ASC')
            ->orderBy('responses.id', 'ASC')
            ->get()
            ->getResultArray();

        if ($responses === []) {
            return $this->legacyRows($reportId);
        }

        $responseIds = array_map('intval', array_column($responses, 'id'));
        $mappingsByResponse = [];
        $mappings = $this->db->table('audit_response_clause_snapshots snapshots')
            ->select('snapshots.*')
            ->whereIn('snapshots.audit_requirement_response_id', $responseIds)
            ->orderBy('snapshots.standard_code_snapshot', 'ASC')
            ->orderBy('snapshots.clause_reference_snapshot', 'ASC')
            ->get()
            ->getResultArray();
        foreach ($mappings as $mapping) {
            $mappingsByResponse[(int) $mapping['audit_requirement_response_id']][] = [
                'standard_id' => (int) $mapping['standard_id'],
                'standard_code' => (string) $mapping['standard_code_snapshot'],
                'clause_library_id' => empty($mapping['clause_library_id']) ? null : (int) $mapping['clause_library_id'],
                'clause_reference' => (string) $mapping['clause_reference_snapshot'],
                'clause_title' => (string) ($mapping['clause_title_snapshot'] ?? ''),
                'mapping_role' => (string) $mapping['mapping_role'],
            ];
        }

        $ncrsByResponse = [];
        if ($this->db->fieldExists('audit_requirement_response_id', 'ncrs')) {
            $ncrs = $this->db->table('ncrs')
                ->whereIn('audit_requirement_response_id', $responseIds)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            foreach ($ncrs as $ncr) {
                $ncrsByResponse[(int) $ncr['audit_requirement_response_id']][] = $ncr;
            }
        }

        $rows = [];
        foreach ($responses as $response) {
            $id = (int) $response['id'];
            $rowMappings = $mappingsByResponse[$id] ?? [];
            $standardCodes = array_values(array_unique(array_filter(array_column($rowMappings, 'standard_code'))));
            $clauseReferences = array_values(array_unique(array_filter(array_column($rowMappings, 'clause_reference'))));
            $clauseTitles = array_values(array_unique(array_filter(array_column($rowMappings, 'clause_title'))));

            $rows[] = array_merge($response, [
                'response_id' => $id,
                'is_controlled_response' => true,
                'mappings' => $rowMappings,
                'ncrs' => $ncrsByResponse[$id] ?? [],
                // Compatibility fields for existing renderers while they migrate.
                'section_key' => (string) $response['finding_type'],
                'section_title' => trim((string) $response['requirement_code'] . ' - ' . (string) $response['requirement_title']),
                'section_content' => (string) ($response['response_text'] ?? ''),
                'standard_code' => implode(', ', $standardCodes),
                'clause_number' => implode(', ', $clauseReferences),
                'clause_title' => implode('; ', $clauseTitles),
            ]);
        }

        return $rows;
    }

    public function supplementaryNotesForReport(int $reportId): array
    {
        return array_values(array_filter(
            $this->legacyRows($reportId),
            static fn (array $row): bool => ! in_array((string) ($row['source_type'] ?? ''), ['qsi_integrated_catalogue'], true)
        ));
    }

    private function legacyRows(int $reportId): array
    {
        return $this->db->table('report_sections')
            ->select('report_sections.*, clause_library.clause_number, clause_library.clause_title, standards.code AS standard_code')
            ->join('clause_library', 'clause_library.id = report_sections.clause_library_id', 'left')
            ->join('standards', 'standards.id = clause_library.standard_id', 'left')
            ->where('report_sections.report_draft_id', $reportId)
            ->orderBy('report_sections.sort_order', 'ASC')
            ->orderBy('report_sections.id', 'ASC')
            ->get()
            ->getResultArray();
    }
}

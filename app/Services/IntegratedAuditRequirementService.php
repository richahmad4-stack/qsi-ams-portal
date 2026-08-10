<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class IntegratedAuditRequirementService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Returns each approved QSI-authored audit question once, with every applicable
     * standard/clause mapping attached for traceable integrated reporting.
     */
    public function requirementsForClient(int $tenantId, int $clientId, string $auditStage): array
    {
        $standardIds = array_map(
            'intval',
            array_column(
                $this->db->table('client_standards')
                    ->select('standard_id')
                    ->where('client_id', $clientId)
                    ->get()
                    ->getResultArray(),
                'standard_id'
            )
        );

        return $this->requirementsForStandards($tenantId, $standardIds, $auditStage);
    }

    /**
     * Returns approved requirements for a selected set of standards before a
     * client record exists, which is required by the Super Admin Cycle Builder.
     */
    public function requirementsForStandards(int $tenantId, array $standardIds, string $auditStage): array
    {
        $standardIds = array_values(array_unique(array_filter(array_map('intval', $standardIds))));
        if ($standardIds === []) {
            return [];
        }

        $rows = $this->db->table('integrated_audit_requirements requirements')
            ->select('requirements.*, mappings.standard_id, mappings.clause_library_id, mappings.clause_reference, mappings.clause_title_snapshot, mappings.mapping_role, standards.code AS standard_code')
            ->join('integrated_requirement_clauses mappings', 'mappings.audit_requirement_id = requirements.id')
            ->join('standards', 'standards.id = mappings.standard_id')
            ->where('requirements.tenant_id', $tenantId)
            ->where('requirements.active', 1)
            ->whereIn('mappings.standard_id', $standardIds)
            ->groupStart()
                ->where('requirements.stage_applicability', 'all')
                ->orLike('requirements.stage_applicability', $auditStage)
            ->groupEnd()
            ->orderBy('requirements.requirement_code', 'ASC')
            ->orderBy('standards.code', 'ASC')
            ->orderBy('mappings.clause_reference', 'ASC')
            ->get()
            ->getResultArray();

        $requirements = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (! isset($requirements[$id])) {
                $requirements[$id] = [
                    'id' => $id,
                    'requirement_code' => (string) $row['requirement_code'],
                    'title' => (string) $row['title'],
                    'audit_question' => (string) $row['audit_question'],
                    'evidence_guidance' => $row['evidence_guidance'],
                    'requirement_family' => (string) $row['requirement_family'],
                    'mappings' => [],
                ];
            }

            $requirements[$id]['mappings'][] = [
                'standard_id' => (int) $row['standard_id'],
                'standard_code' => (string) $row['standard_code'],
                'clause_library_id' => empty($row['clause_library_id']) ? null : (int) $row['clause_library_id'],
                'clause_reference' => (string) $row['clause_reference'],
                'clause_title' => $row['clause_title_snapshot'],
                'mapping_role' => (string) $row['mapping_role'],
            ];
        }

        return array_values($requirements);
    }
}

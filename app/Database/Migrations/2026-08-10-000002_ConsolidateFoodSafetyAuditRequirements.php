<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ConsolidateFoodSafetyAuditRequirements extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('integrated_audit_requirements')) {
            return;
        }

        $haccp = $this->db->table('standards')->select('id')->where('code', 'HACCP')->get()->getRowArray();
        if ($haccp === null) {
            return;
        }

        foreach ($this->db->table('tenants')->select('id')->get()->getResultArray() as $tenant) {
            $tenantId = (int) $tenant['id'];
            $this->consolidate(
                $tenantId,
                'QSI-FSMS-08.02',
                'QSI-HACCP-GHP',
                (int) $haccp['id'],
                'GHP',
                'Good hygiene practices'
            );
            $this->consolidate(
                $tenantId,
                'QSI-FSMS-08.52',
                'QSI-HACCP-P01',
                (int) $haccp['id'],
                'HACCP Principle 1',
                'Conduct hazard analysis and identify controls'
            );
        }
    }

    public function down(): void
    {
        // Historical responses remain attached to their original snapshots.
    }

    private function consolidate(int $tenantId, string $primaryCode, string $duplicateCode, int $standardId, string $reference, string $title): void
    {
        $primary = $this->requirement($tenantId, $primaryCode);
        if ($primary === null) {
            return;
        }

        $mapping = $this->db->table('integrated_requirement_clauses')
            ->where('audit_requirement_id', (int) $primary['id'])
            ->where('standard_id', $standardId)
            ->where('clause_reference', $reference)
            ->get()
            ->getRowArray();
        if ($mapping === null) {
            $this->db->table('integrated_requirement_clauses')->insert([
                'audit_requirement_id' => (int) $primary['id'],
                'standard_id' => $standardId,
                'clause_library_id' => null,
                'clause_reference' => $reference,
                'clause_title_snapshot' => $title,
                'mapping_role' => 'primary',
            ]);
        }

        $duplicate = $this->requirement($tenantId, $duplicateCode);
        if ($duplicate === null) {
            return;
        }

        $responseCount = $this->db->table('audit_requirement_responses')
            ->where('audit_requirement_id', (int) $duplicate['id'])
            ->countAllResults();
        if ($responseCount > 0) {
            $this->db->table('integrated_audit_requirements')->where('id', (int) $duplicate['id'])->update(['active' => 0]);
            return;
        }

        $this->db->table('integrated_audit_requirements')->where('id', (int) $duplicate['id'])->delete();
    }

    private function requirement(int $tenantId, string $code): ?array
    {
        return $this->db->table('integrated_audit_requirements')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('requirement_code', $code)
            ->where('version_no', 1)
            ->get()
            ->getRowArray();
    }
}

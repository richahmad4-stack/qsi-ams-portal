<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ReplacePlaceholderClauseRequirements extends Migration
{
    public function up(): void
    {
        $rows = $this->db->table('clause_library')
            ->select('clause_library.id, clause_library.requirement, clause_library.clause_title, standards.code AS standard_code')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->like('clause_library.requirement', 'Placeholder for licensed', 'after')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $focus = strtolower(trim((string) ($row['clause_title'] ?? 'the audited requirement')));
            $standard = trim((string) ($row['standard_code'] ?? 'the applicable standard'));

            $this->db->table('clause_library')
                ->where('id', (int) $row['id'])
                ->update([
                    'requirement' => 'Internal audit checklist question for ' . $standard . ': verify that controls for ' . $focus . ' are defined, implemented, monitored and supported by retained evidence. Do not treat this text as a substitute for licensed standard wording.',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    public function down(): void
    {
        // The original placeholder wording is intentionally not restored.
    }
}

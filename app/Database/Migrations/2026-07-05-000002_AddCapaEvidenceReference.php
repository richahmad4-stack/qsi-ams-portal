<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCapaEvidenceReference extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('capas')) {
            return;
        }

        if (! in_array('evidence_reference', $this->db->getFieldNames('capas'), true)) {
            $this->forge->addColumn('capas', [
                'evidence_reference' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'target_date',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('capas') && in_array('evidence_reference', $this->db->getFieldNames('capas'), true)) {
            $this->forge->dropColumn('capas', 'evidence_reference');
        }
    }
}

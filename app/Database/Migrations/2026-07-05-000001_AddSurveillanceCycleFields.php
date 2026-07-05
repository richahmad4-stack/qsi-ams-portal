<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSurveillanceCycleFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('audit_programs')) {
            return;
        }

        $fields = $this->db->getFieldNames('audit_programs');
        $add = [];

        if (! in_array('surveillance_1_due_date', $fields, true)) {
            $add['surveillance_1_due_date'] = ['type' => 'DATE', 'null' => true, 'after' => 'certificate_issue_date'];
        }

        if (! in_array('surveillance_2_due_date', $fields, true)) {
            $add['surveillance_2_due_date'] = ['type' => 'DATE', 'null' => true, 'after' => 'certificate_issue_date'];
        }

        if (! in_array('surveillance_1_status', $fields, true)) {
            $add['surveillance_1_status'] = ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'locked', 'after' => 'certificate_expiry_date'];
        }

        if (! in_array('surveillance_2_status', $fields, true)) {
            $add['surveillance_2_status'] = ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'locked', 'after' => 'certificate_expiry_date'];
        }

        if ($add !== []) {
            $this->forge->addColumn('audit_programs', $add);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('audit_programs')) {
            return;
        }

        $fields = $this->db->getFieldNames('audit_programs');
        $drop = array_values(array_intersect([
            'surveillance_1_due_date',
            'surveillance_2_due_date',
            'surveillance_1_status',
            'surveillance_2_status',
        ], $fields));

        if ($drop !== []) {
            $this->forge->dropColumn('audit_programs', $drop);
        }
    }
}

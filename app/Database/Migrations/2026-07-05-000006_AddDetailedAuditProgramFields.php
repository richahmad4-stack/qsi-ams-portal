<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDetailedAuditProgramFields extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('audit_programs', 'document_number')) {
            $this->db->query(
                "ALTER TABLE audit_programs
                    ADD document_number VARCHAR(40) NOT NULL DEFAULT 'F 42' AFTER program_number,
                    ADD revision_number VARCHAR(20) NOT NULL DEFAULT '2' AFTER document_number,
                    ADD issue_number VARCHAR(20) NOT NULL DEFAULT '2' AFTER revision_number,
                    ADD document_date DATE NOT NULL DEFAULT '2022-05-15' AFTER issue_number,
                    ADD program_payload JSON NULL AFTER status,
                    ADD prepared_by_name VARCHAR(180) NULL AFTER program_payload,
                    ADD prepared_date DATE NULL AFTER prepared_by_name,
                    ADD approved_by_name VARCHAR(180) NULL AFTER prepared_date,
                    ADD approved_date DATE NULL AFTER approved_by_name"
            );
        }
    }

    public function down(): void
    {
        if ($this->columnExists('audit_programs', 'document_number')) {
            $this->db->query(
                'ALTER TABLE audit_programs
                    DROP COLUMN approved_date,
                    DROP COLUMN approved_by_name,
                    DROP COLUMN prepared_date,
                    DROP COLUMN prepared_by_name,
                    DROP COLUMN program_payload,
                    DROP COLUMN document_date,
                    DROP COLUMN issue_number,
                    DROP COLUMN revision_number,
                    DROP COLUMN document_number'
            );
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->db->escape($column))
            ->getRowArray() !== null;
    }
}

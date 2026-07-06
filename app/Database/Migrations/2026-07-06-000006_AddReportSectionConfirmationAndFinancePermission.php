<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReportSectionConfirmationAndFinancePermission extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('source_type', 'report_sections')) {
            $this->db->query(
                "ALTER TABLE report_sections
                    ADD source_type VARCHAR(40) NOT NULL DEFAULT 'system_draft' AFTER section_content,
                    ADD auditor_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER source_type,
                    ADD confirmed_by_user_id BIGINT UNSIGNED NULL AFTER auditor_confirmed,
                    ADD confirmed_at DATETIME NULL AFTER confirmed_by_user_id,
                    ADD confirmation_note TEXT NULL AFTER confirmed_at,
                    ADD KEY idx_report_sections_confirmation (report_draft_id, section_key, auditor_confirmed),
                    ADD KEY idx_report_sections_confirmed_by (confirmed_by_user_id)"
            );
        }

        $this->db->table('report_sections')
            ->where('section_key', 'conformity')
            ->where('auditor_confirmed', 0)
            ->update([
                'source_type' => 'legacy_confirmed',
                'auditor_confirmed' => 1,
                'confirmed_at' => date('Y-m-d H:i:s'),
                'confirmation_note' => 'Legacy report section marked confirmed during migration. New generated sections require explicit auditor confirmation.',
            ]);

        foreach (['view', 'download', 'print'] as $action) {
            $this->db->query(
                'INSERT IGNORE INTO permissions (module, action, description) VALUES (?, ?, ?)',
                ['finance', $action, ucwords($action . ' finance')]
            );
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('source_type', 'report_sections')) {
            $this->db->query('ALTER TABLE report_sections DROP KEY idx_report_sections_confirmation, DROP KEY idx_report_sections_confirmed_by');
        }

        foreach (['confirmation_note', 'confirmed_at', 'confirmed_by_user_id', 'auditor_confirmed', 'source_type'] as $column) {
            if ($this->db->fieldExists($column, 'report_sections')) {
                $this->forge->dropColumn('report_sections', $column);
            }
        }
    }
}

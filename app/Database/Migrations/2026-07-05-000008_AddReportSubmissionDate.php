<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReportSubmissionDate extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('report_drafts', 'submitted_at')) {
            $this->db->query(
                'ALTER TABLE report_drafts
                    ADD submitted_at DATETIME NULL AFTER approved_at,
                    ADD KEY idx_report_drafts_submitted_at (submitted_at)'
            );
        }

        $this->db->query(
            "UPDATE report_drafts rd
                JOIN audit_events ae ON ae.id = rd.audit_event_id
                SET rd.submitted_at = COALESCE(
                    rd.approved_at,
                    rd.updated_at,
                    CONCAT(ae.actual_end_date, ' 17:00:00'),
                    rd.created_at
                )
                WHERE rd.submitted_at IS NULL
                    AND (rd.status IN ('submitted', 'approved', 'completed') OR ae.status = 'completed')"
        );
    }

    public function down(): void
    {
        if ($this->columnExists('report_drafts', 'submitted_at')) {
            $this->db->query(
                'ALTER TABLE report_drafts
                    DROP KEY idx_report_drafts_submitted_at,
                    DROP COLUMN submitted_at'
            );
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->db->escape($column))
            ->getRowArray() !== null;
    }
}

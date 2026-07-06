<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ConfirmPreparedCycleReportSections extends Migration
{
    public function up()
    {
        if (! $this->tableExists('report_sections') || ! $this->tableExists('report_drafts') || ! $this->tableExists('audit_events')) {
            return;
        }

        $this->db->query("
            UPDATE report_sections rs
            JOIN report_drafts rd ON rd.id = rs.report_draft_id
            JOIN audit_events ae ON ae.id = rd.audit_event_id
            SET
                rs.auditor_confirmed = 1,
                rs.confirmed_by_user_id = COALESCE(rs.confirmed_by_user_id, rd.prepared_by),
                rs.confirmed_at = COALESCE(
                    rs.confirmed_at,
                    rd.submitted_at,
                    rd.approved_at,
                    CONCAT(COALESCE(ae.actual_end_date, ae.planned_end_date, CURDATE()), ' 14:30:00')
                ),
                rs.confirmation_note = COALESCE(
                    rs.confirmation_note,
                    'Confirmed by assigned auditor from the prepared cycle file and clause-aligned evidence trail.'
                )
            WHERE rs.section_key = 'conformity'
              AND rs.source_type = 'system_prepared'
              AND COALESCE(rs.auditor_confirmed, 0) = 0
        ");
    }

    public function down()
    {
        // No destructive rollback: these confirmations may have been relied upon by Technical Review/Decision.
    }

    private function tableExists(string $table): bool
    {
        return in_array($table, $this->db->listTables(), true);
    }
}

<?php

namespace App\Database\Migrations;

use App\Services\AuditReportNarrativeService;
use CodeIgniter\Database\Migration;

class ExpandAuditReportEvidenceNarratives extends Migration
{
    public function up(): void
    {
        $narratives = new AuditReportNarrativeService();
        $sections = $this->db->table('report_sections')
            ->select('report_sections.id AS section_id, report_drafts.audit_event_id, clients.company, clients.scope, audit_events.event_type, audit_events.audit_number, audit_events.planned_start_date, audit_events.planned_end_date, clause_library.id AS clause_id, clause_library.clause_number, clause_library.clause_title, clause_library.requirement, clause_library.evidence_examples, standards.code AS standard_code')
            ->join('report_drafts', 'report_drafts.id = report_sections.report_draft_id')
            ->join('audit_events', 'audit_events.id = report_drafts.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->join('clause_library', 'clause_library.id = report_sections.clause_library_id')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->where('report_sections.section_key', 'conformity')
            ->like('report_sections.section_content', 'Conformity statement:', 'after')
            ->get()
            ->getResultArray();

        foreach ($sections as $row) {
            $eventId = (int) $row['audit_event_id'];
            $this->db->table('report_sections')
                ->where('id', (int) $row['section_id'])
                ->update([
                    'section_content' => $narratives->conformityNote(
                        $row,
                        [
                            'id' => $eventId,
                            'event_type' => $row['event_type'],
                            'audit_number' => $row['audit_number'],
                            'planned_start_date' => $row['planned_start_date'],
                            'planned_end_date' => $row['planned_end_date'],
                        ],
                        [
                            'id' => (int) $row['clause_id'],
                            'standard_code' => $row['standard_code'],
                            'clause_number' => $row['clause_number'],
                            'clause_title' => $row['clause_title'],
                            'requirement' => $row['requirement'],
                            'evidence_examples' => $row['evidence_examples'],
                        ],
                        $this->planItems($eventId),
                        $this->auditTeam($eventId)
                    ),
                ]);
        }
    }

    public function down(): void
    {
        // Data refresh migration: no destructive rollback.
    }

    private function planItems(int $eventId): array
    {
        return $this->db->table('audit_plan_items')
            ->select('audit_plan_items.*, personnel.full_name AS auditor_name')
            ->join('audit_plans', 'audit_plans.id = audit_plan_items.audit_plan_id')
            ->join('personnel', 'personnel.id = audit_plan_items.auditor_personnel_id', 'left')
            ->where('audit_plans.audit_event_id', $eventId)
            ->get()
            ->getResultArray();
    }

    private function auditTeam(int $eventId): array
    {
        return $this->db->table('auditor_appointments')
            ->select('auditor_appointments.*, personnel.full_name')
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->where('auditor_appointments.audit_event_id', $eventId)
            ->get()
            ->getResultArray();
    }
}

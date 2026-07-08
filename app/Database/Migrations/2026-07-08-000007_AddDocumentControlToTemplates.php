<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentControlToTemplates extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('document_number', 'document_templates')) {
            $this->forge->addColumn('document_templates', [
                'document_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => true,
                    'after' => 'document_type',
                ],
                'revision_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                    'after' => 'document_number',
                ],
                'issue_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                    'after' => 'revision_number',
                ],
                'document_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'after' => 'issue_number',
                ],
            ]);
        }

        $this->seedControls();
    }

    public function down(): void
    {
        if ($this->db->fieldExists('document_number', 'document_templates')) {
            $this->forge->dropColumn('document_templates', ['document_date', 'issue_number', 'revision_number', 'document_number']);
        }
    }

    private function seedControls(): void
    {
        $templates = [
            ['certification_application', 'Certification Application', 'application', 'F 25', '1', '2', '2024-11-01'],
            ['application_review', 'Application Review Checklist Report', 'review', 'F 28', '4', '2', '2025-02-01'],
            ['proposal', 'Proposal', 'proposal', 'F 26', '2', '2', '2022-05-15'],
            ['contract_agreement', 'Certification Agreement', 'contract', 'F 27', '2', '2', '2022-05-15'],
            ['audit_program', 'Audit Program', 'audit_program', 'F 42', '2', '2', '2022-05-15'],
            ['auditor_appointment', 'Auditor Appointment', 'appointment', 'F 30_app', '2', '2', '2022-05-15'],
            ['audit_plan', 'Audit Plan', 'audit_plan', 'F 31', '2', '2', '2022-05-15'],
            ['stage1_report', 'Stage 1 Report', 'report', 'F 32', '2', '2', '2022-05-15'],
            ['stage2_report', 'Stage 2 Report', 'report', 'F 32', '2', '2', '2022-05-15'],
            ['surveillance_report', 'Surveillance Report', 'report', 'F 32', '2', '2', '2022-05-15'],
            ['recertification_report', 'Recertification Report', 'report', 'F 32', '2', '2', '2022-05-15'],
            ['ncr_capa', 'NCR / CAPA', 'ncr_capa', 'F 33', '2', '2', '2022-05-15'],
            ['technical_review_report', 'Technical Review Report', 'report', 'F 34', '2', '2', '2022-05-15'],
            ['decision_report', 'Certification Decision Report', 'report', 'F 35', '2', '2', '2022-05-15'],
            ['feedback', 'Client Feedback', 'feedback', 'F 36', '2', '2', '2022-05-15'],
        ];

        foreach ($templates as [$key, $name, $type, $number, $revision, $issue, $date]) {
            $existing = $this->db->table('document_templates')
                ->where('tenant_id', 1)
                ->where('template_key', $key)
                ->get(1)
                ->getRowArray();

            $payload = [
                'document_number' => $number,
                'revision_number' => $revision,
                'issue_number' => $issue,
                'document_date' => $date,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing === null) {
                $this->db->table('document_templates')->insert($payload + [
                    'tenant_id' => 1,
                    'template_key' => $key,
                    'name' => $name,
                    'document_type' => $type,
                    'allowed_placeholders' => json_encode(['client_name', 'scope', 'standard'], JSON_THROW_ON_ERROR),
                    'status' => 'approved',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            $this->db->table('document_templates')
                ->where('id', (int) $existing['id'])
                ->update($payload);
        }
    }
}

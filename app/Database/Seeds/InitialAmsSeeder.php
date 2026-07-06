<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialAmsSeeder extends Seeder
{
    private array $actions = [
        'view',
        'create',
        'edit',
        'delete',
        'approve',
        'reject',
        'download',
        'print',
    ];

    private array $modules = [
        'dashboard',
        'clients',
        'legacy_imports',
        'standards',
        'questionnaires',
        'application_reviews',
        'proposals',
        'contracts',
        'audit_programs',
        'surveillance_1',
        'surveillance_2',
        'recertification',
        'personnel',
        'competency_matrix',
        'auditor_appointments',
        'audit_plans',
        'clause_library',
        'reports',
        'ncrs',
        'capas',
        'internal_audits',
        'management_reviews',
        'technical_reviews',
        'certification_decisions',
        'certificates',
        'public_verification',
        'document_templates',
        'notifications',
        'global_search',
        'audit_trail',
        'finance',
        'api',
        'settings',
    ];

    public function run(): void
    {
        $this->seedTenant();
        $this->seedStandards();
        $this->seedRoles();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->seedDocumentTemplates();
        $this->seedNotificationRules();
        $this->call(ApplicationQuestionLibrarySeeder::class);
    }

    private function seedTenant(): void
    {
        $this->db->query(
            "INSERT IGNORE INTO tenants (id, name, legal_name, code, timezone, currency, status)
             VALUES (1, 'QSI', 'QSI Certification Body', 'QSI', 'Asia/Riyadh', 'SAR', 'active')"
        );
    }

    private function seedStandards(): void
    {
        $standards = [
            ['ISO 9001:2015', 'ISO 9001', '2015', 'management_system'],
            ['ISO 14001:2015', 'ISO 14001', '2015', 'management_system'],
            ['ISO 45001:2018', 'ISO 45001', '2018', 'management_system'],
            ['ISO 22000:2018', 'ISO 22000', '2018', 'food_safety'],
            ['ISO 13485:2016', 'ISO 13485', '2016', 'medical_device'],
            ['HACCP', 'HACCP', null, 'food_safety'],
            ['FSSC 22000 Version 6', 'FSSC 22000', 'Version 6', 'food_safety'],
            ['ISO 17021', 'ISO 17021', null, 'certification_body'],
            ['ISO 17065', 'ISO 17065', null, 'certification_body'],
        ];

        foreach ($standards as [$code, $name, $version, $schemeType]) {
            $this->db->query(
                'INSERT IGNORE INTO standards (code, name, version, scheme_type, active) VALUES (?, ?, ?, ?, 1)',
                [$code, $name, $version, $schemeType]
            );
        }
    }

    private function seedRoles(): void
    {
        $roles = [
            ['super_admin', 'Super User', 'Full tenant owner access to every module and workflow action.', true],
            ['administrator', 'Administrator', 'Day-to-day certification operations administration.', true],
            ['quality_manager', 'Quality Manager', 'Quality system oversight, approval, and audit trail review.', true],
            ['technical_manager', 'Technical Manager', 'Application review, technical controls, and competence oversight.', true],
            ['proposal_officer', 'Proposal Officer', 'Client enquiries, proposals, contracts, and commercial records.', true],
            ['auditor', 'Auditor', 'Assigned audit execution, findings, evidence, and reports.', true],
            ['lead_auditor', 'Lead Auditor', 'Audit team leadership, audit plans, NCRs, and report finalization.', true],
            ['technical_reviewer', 'Technical Reviewer', 'Independent technical review before certification decision.', true],
            ['certification_decision_maker', 'Certification Decision Maker', 'Independent certification decisions and electronic approvals.', true],
            ['finance', 'Finance', 'Invoices, payments, revenue dashboard, and fee summaries.', true],
            ['viewer', 'Viewer', 'Read-only internal access for authorized records.', true],
        ];

        foreach ($roles as [$code, $name, $description, $systemRole]) {
            $this->db->query(
                'INSERT IGNORE INTO roles (tenant_id, code, name, description, system_role) VALUES (1, ?, ?, ?, ?)',
                [$code, $name, $description, $systemRole ? 1 : 0]
            );
        }
    }

    private function seedPermissions(): void
    {
        foreach ($this->modules as $module) {
            foreach ($this->actions as $action) {
                $description = ucwords(str_replace('_', ' ', $action . ' ' . $module));
                $this->db->query(
                    'INSERT IGNORE INTO permissions (module, action, description) VALUES (?, ?, ?)',
                    [$module, $action, $description]
                );
            }
        }
    }

    private function seedRolePermissions(): void
    {
        $superAdminId = $this->roleId('super_admin');

        $this->db->query(
            'INSERT IGNORE INTO role_permissions (role_id, permission_id)
             SELECT ?, id FROM permissions',
            [$superAdminId]
        );

        $viewerId = $this->roleId('viewer');

        $this->db->query(
            "INSERT IGNORE INTO role_permissions (role_id, permission_id)
             SELECT ?, id FROM permissions WHERE action IN ('view', 'download', 'print')",
            [$viewerId]
        );

        $roleRules = [
            'administrator' => [
                'dashboard', 'clients', 'legacy_imports', 'standards', 'personnel', 'competency_matrix',
                'application_reviews', 'proposals', 'contracts', 'audit_programs', 'auditor_appointments',
                'audit_plans', 'reports', 'ncrs', 'capas', 'technical_reviews', 'certification_decisions',
                'certificates', 'document_templates', 'finance', 'global_search',
            ],
            'quality_manager' => [
                'dashboard', 'clients', 'standards', 'questionnaires', 'application_reviews',
                'audit_programs', 'clause_library', 'reports', 'ncrs', 'capas', 'internal_audits',
                'management_reviews', 'technical_reviews', 'certification_decisions', 'certificates',
                'document_templates', 'notifications', 'global_search', 'audit_trail',
            ],
            'technical_manager' => [
                'dashboard', 'clients', 'standards', 'application_reviews', 'audit_programs',
                'personnel', 'competency_matrix', 'auditor_appointments', 'clause_library',
                'reports', 'technical_reviews', 'certification_decisions', 'global_search',
            ],
            'proposal_officer' => [
                'dashboard', 'clients', 'legacy_imports', 'application_reviews', 'proposals',
                'contracts', 'audit_programs', 'finance', 'document_templates', 'global_search',
            ],
            'auditor' => [
                'dashboard', 'clients', 'audit_plans', 'clause_library', 'reports', 'ncrs',
                'capas', 'internal_audits', 'global_search',
            ],
            'lead_auditor' => [
                'dashboard', 'clients', 'audit_programs', 'auditor_appointments', 'audit_plans',
                'clause_library', 'reports', 'ncrs', 'capas', 'internal_audits', 'global_search',
            ],
            'technical_reviewer' => [
                'dashboard', 'clients', 'audit_programs', 'reports', 'ncrs', 'capas',
                'technical_reviews', 'global_search',
            ],
            'certification_decision_maker' => [
                'dashboard', 'clients', 'technical_reviews', 'certification_decisions',
                'certificates', 'global_search',
            ],
            'finance' => [
                'dashboard', 'clients', 'proposals', 'contracts', 'finance', 'global_search',
            ],
        ];

        foreach ($roleRules as $roleCode => $modules) {
            $roleId = $this->roleId($roleCode);

            foreach ($modules as $module) {
                $this->db->query(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id)
                     SELECT ?, id FROM permissions WHERE module = ?',
                    [$roleId, $module]
                );
            }
        }
    }

    private function seedDocumentTemplates(): void
    {
        $templates = [
            ['proposal', 'Proposal', 'proposal', ['client_name', 'scope', 'standard', 'certification_fee', 'surveillance1_fee', 'surveillance2_fee', 'vat', 'grand_total', 'proposal_validity']],
            ['contract_agreement', 'Certification Agreement', 'contract', ['client_name', 'scope', 'standard', 'proposal_number', 'grand_total', 'contract_number']],
            ['audit_plan', 'Audit Plan', 'audit_plan', ['client_name', 'scope', 'standard', 'auditor', 'audit_date', 'audit_team', 'audit_timetable']],
            ['stage1_report', 'Stage 1 Report', 'report', ['client_name', 'scope', 'standard', 'auditor', 'audit_date', 'ncr_summary', 'capa_summary']],
            ['stage2_report', 'Stage 2 Report', 'report', ['client_name', 'scope', 'standard', 'auditor', 'audit_date', 'ncr_summary', 'capa_summary']],
            ['surveillance_report', 'Surveillance Report', 'report', ['client_name', 'scope', 'standard', 'auditor', 'audit_date', 'ncr_summary', 'capa_summary']],
            ['recertification_report', 'Recertification Report', 'report', ['client_name', 'scope', 'standard', 'auditor', 'audit_date', 'ncr_summary', 'capa_summary']],
            ['technical_review_report', 'Technical Review Report', 'report', ['client_name', 'scope', 'standard', 'technical_reviewer', 'recommendation']],
            ['decision_report', 'Certification Decision Report', 'report', ['client_name', 'scope', 'standard', 'decision', 'decision_maker']],
            ['certificate', 'Certificate', 'certificate', ['client_name', 'scope', 'certificate_number', 'standard', 'issue_date', 'expiry_date', 'initial_certification_date', 'qr_code', 'verification_url']],
        ];

        foreach ($templates as [$key, $name, $type, $placeholders]) {
            $this->db->query(
                'INSERT IGNORE INTO document_templates
                 (tenant_id, template_key, name, document_type, allowed_placeholders, status)
                 VALUES (1, ?, ?, ?, ?, ?)',
                [$key, $name, $type, json_encode($placeholders, JSON_THROW_ON_ERROR), 'draft']
            );
        }
    }

    private function seedNotificationRules(): void
    {
        $rules = [
            ['certificate_expiry_90', 'dashboard', 'certificate_expiry', -90, ['quality_manager', 'technical_manager']],
            ['certificate_expiry_30', 'dashboard', 'certificate_expiry', -30, ['quality_manager', 'technical_manager']],
            ['capa_due_7', 'dashboard', 'capa_due', -7, ['quality_manager', 'auditor', 'lead_auditor']],
            ['ncr_due_7', 'dashboard', 'ncr_due', -7, ['quality_manager', 'auditor', 'lead_auditor']],
            ['surveillance_due_90', 'dashboard', 'surveillance_due', -90, ['quality_manager', 'technical_manager', 'proposal_officer']],
            ['competency_expiry_60', 'dashboard', 'competency_expiry', -60, ['quality_manager', 'technical_manager']],
        ];

        foreach ($rules as [$key, $channel, $event, $offset, $roles]) {
            $this->db->query(
                'INSERT IGNORE INTO notification_rules
                 (tenant_id, rule_key, channel, trigger_event, days_offset, recipient_roles, active)
                 VALUES (1, ?, ?, ?, ?, ?, 1)',
                [$key, $channel, $event, $offset, json_encode($roles, JSON_THROW_ON_ERROR)]
            );
        }
    }

    private function roleId(string $code): int
    {
        $row = $this->db->query(
            'SELECT id FROM roles WHERE tenant_id = 1 AND code = ? LIMIT 1',
            [$code]
        )->getRowArray();

        return (int) $row['id'];
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeWorkflowRolePermissions extends Migration
{
    private array $roleNames = [
        'super_admin' => 'Super User',
        'administrator' => 'Administrator',
        'certification_manager' => 'Certification Manager',
        'technical_manager' => 'Technical Manager',
        'quality_manager' => 'Quality Manager',
        'lead_auditor' => 'Lead Auditor',
        'auditor' => 'Auditor',
        'technical_reviewer' => 'Technical Reviewer',
        'certification_decision_maker' => 'Certification Decision Maker',
        'general_manager' => 'General Manager',
        'chief_operating_officer' => 'Chief Operating Officer',
        'finance' => 'Finance',
        'proposal_officer' => 'Proposal Officer',
        'sales_executive' => 'Sales Executive',
        'document_controller' => 'Document Controller',
        'client_representative' => 'Client Representative',
        'trainer' => 'Trainer',
        'viewer' => 'Viewer',
    ];

    public function up(): void
    {
        foreach ($this->roleNames as $code => $name) {
            $this->db->query(
                'INSERT IGNORE INTO roles (tenant_id, code, name, description, system_role) VALUES (1, ?, ?, ?, 1)',
                [$code, $name, $name . ' role.']
            );
        }

        $managedRoleIds = $this->managedRoleIds();
        if ($managedRoleIds !== []) {
            $this->db->table('role_permissions')->whereIn('role_id', $managedRoleIds)->delete();
        }

        $this->grantAll('super_admin');
        $this->grantByActions('viewer', ['view', 'download', 'print']);

        $matrix = [
            'administrator' => [
                'dashboard' => ['view'],
                'clients' => ['view', 'create', 'edit', 'delete'],
                'standards' => ['view'],
                'personnel' => ['view'],
                'competency_matrix' => ['view'],
                'application_reviews' => ['view'],
                'proposals' => ['view', 'create', 'edit', 'download', 'print'],
                'contracts' => ['view', 'create', 'edit', 'download', 'print'],
                'audit_programs' => ['view', 'create', 'edit', 'download', 'print'],
                'auditor_appointments' => ['view', 'create', 'edit', 'delete'],
                'audit_plans' => ['view', 'create', 'edit', 'delete', 'download', 'print'],
                'reports' => ['view', 'download', 'print'],
                'ncrs' => ['view'],
                'capas' => ['view'],
                'technical_reviews' => ['view'],
                'certification_decisions' => ['view'],
                'certificates' => ['view', 'create', 'edit', 'download', 'print'],
                'document_templates' => ['view', 'download', 'print'],
                'finance' => ['view'],
                'global_search' => ['view'],
            ],
            'certification_manager' => [
                'dashboard' => ['view'],
                'clients' => ['view', 'edit'],
                'application_reviews' => ['view', 'edit'],
                'proposals' => ['view', 'create', 'edit', 'download', 'print'],
                'contracts' => ['view', 'create', 'edit', 'download', 'print'],
                'audit_programs' => ['view', 'create', 'edit', 'download', 'print'],
                'auditor_appointments' => ['view', 'create', 'edit', 'delete'],
                'audit_plans' => ['view', 'download', 'print'],
                'reports' => ['view', 'download', 'print'],
                'ncrs' => ['view'],
                'capas' => ['view'],
                'technical_reviews' => ['view'],
                'certification_decisions' => ['view'],
                'certificates' => ['view', 'create', 'edit', 'download', 'print'],
                'global_search' => ['view'],
            ],
            'technical_manager' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'standards' => ['view'],
                'personnel' => ['view'],
                'competency_matrix' => ['view'],
                'application_reviews' => ['view', 'edit', 'approve', 'reject'],
                'audit_programs' => ['view', 'edit'],
                'auditor_appointments' => ['view', 'create', 'edit', 'delete'],
                'audit_plans' => ['view', 'download', 'print'],
                'reports' => ['view', 'download', 'print'],
                'ncrs' => ['view'],
                'capas' => ['view'],
                'technical_reviews' => ['view', 'edit', 'approve', 'reject'],
                'certification_decisions' => ['view'],
                'global_search' => ['view'],
            ],
            'quality_manager' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'standards' => ['view', 'edit'],
                'application_reviews' => ['view', 'edit', 'approve', 'reject'],
                'clause_library' => ['view', 'create', 'edit', 'delete'],
                'reports' => ['view', 'download', 'print'],
                'ncrs' => ['view', 'edit'],
                'capas' => ['view', 'edit'],
                'technical_reviews' => ['view'],
                'certification_decisions' => ['view'],
                'document_templates' => ['view', 'edit', 'download', 'print'],
                'audit_trail' => ['view'],
                'global_search' => ['view'],
            ],
            'lead_auditor' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'audit_programs' => ['view'],
                'auditor_appointments' => ['view'],
                'audit_plans' => ['view', 'create', 'edit', 'delete', 'download', 'print'],
                'clause_library' => ['view'],
                'reports' => ['view', 'create', 'edit', 'delete', 'download', 'print'],
                'ncrs' => ['view', 'create', 'edit', 'delete'],
                'capas' => ['view', 'create', 'edit', 'delete'],
                'global_search' => ['view'],
            ],
            'auditor' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'audit_plans' => ['view', 'download', 'print'],
                'clause_library' => ['view'],
                'reports' => ['view', 'create', 'edit', 'download', 'print'],
                'ncrs' => ['view', 'create', 'edit'],
                'capas' => ['view', 'create', 'edit'],
                'global_search' => ['view'],
            ],
            'technical_reviewer' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'audit_programs' => ['view'],
                'audit_plans' => ['view', 'download', 'print'],
                'reports' => ['view', 'download', 'print'],
                'ncrs' => ['view'],
                'capas' => ['view'],
                'technical_reviews' => ['view', 'edit', 'approve', 'reject', 'download', 'print'],
                'global_search' => ['view'],
            ],
            'certification_decision_maker' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'technical_reviews' => ['view', 'download', 'print'],
                'certification_decisions' => ['view', 'edit', 'approve', 'reject', 'download', 'print'],
                'certificates' => ['view'],
                'global_search' => ['view'],
            ],
            'general_manager' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'reports' => ['view', 'download', 'print'],
                'technical_reviews' => ['view', 'download', 'print'],
                'certification_decisions' => ['view', 'edit', 'approve', 'download', 'print'],
                'certificates' => ['view', 'edit', 'download', 'print'],
                'finance' => ['view'],
                'audit_trail' => ['view'],
                'global_search' => ['view'],
            ],
            'chief_operating_officer' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'proposals' => ['view', 'download', 'print'],
                'contracts' => ['view', 'download', 'print'],
                'audit_programs' => ['view'],
                'reports' => ['view', 'download', 'print'],
                'certificates' => ['view', 'download', 'print'],
                'finance' => ['view'],
                'global_search' => ['view'],
            ],
            'finance' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'proposals' => ['view', 'download', 'print'],
                'contracts' => ['view', 'download', 'print'],
                'finance' => ['view', 'download', 'print'],
            ],
            'proposal_officer' => [
                'dashboard' => ['view'],
                'clients' => ['view', 'create', 'edit'],
                'application_reviews' => ['view'],
                'proposals' => ['view', 'create', 'edit', 'download', 'print'],
                'contracts' => ['view', 'create', 'edit', 'download', 'print'],
                'audit_programs' => ['view'],
                'global_search' => ['view'],
            ],
            'sales_executive' => [
                'dashboard' => ['view'],
                'clients' => ['view', 'create', 'edit'],
                'application_reviews' => ['view'],
                'proposals' => ['view', 'create', 'edit', 'download', 'print'],
                'contracts' => ['view', 'download', 'print'],
                'global_search' => ['view'],
            ],
            'document_controller' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'document_templates' => ['view', 'create', 'edit', 'download', 'print'],
                'reports' => ['view', 'download', 'print'],
                'certificates' => ['view', 'download', 'print'],
                'global_search' => ['view'],
            ],
            'trainer' => [
                'dashboard' => ['view'],
                'clients' => ['view'],
                'clause_library' => ['view'],
                'reports' => ['view'],
            ],
            'client_representative' => [
                'dashboard' => ['view'],
            ],
        ];

        foreach ($matrix as $roleCode => $modules) {
            foreach ($modules as $module => $actions) {
                $this->grant($roleCode, $module, $actions);
            }
        }

        $this->assignRoleToUserEmail('admin@qsi.local', 'super_admin');
    }

    public function down(): void
    {
        // Role normalization is intentionally not destructive on rollback.
    }

    private function managedRoleIds(): array
    {
        $rows = $this->db->table('roles')
            ->select('id')
            ->where('tenant_id', 1)
            ->whereIn('code', array_keys($this->roleNames))
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function grantAll(string $roleCode): void
    {
        $roleId = $this->roleId($roleCode);
        if ($roleId <= 0) {
            return;
        }

        $this->db->query(
            'INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions',
            [$roleId]
        );
    }

    private function grantByActions(string $roleCode, array $actions): void
    {
        $roleId = $this->roleId($roleCode);
        if ($roleId <= 0 || $actions === []) {
            return;
        }

        foreach ($actions as $action) {
            $this->db->query(
                'INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE action = ?',
                [$roleId, $action]
            );
        }
    }

    private function grant(string $roleCode, string $module, array $actions): void
    {
        $roleId = $this->roleId($roleCode);
        if ($roleId <= 0) {
            return;
        }

        foreach ($actions as $action) {
            $permission = $this->db->table('permissions')
                ->select('id')
                ->where('module', $module)
                ->where('action', $action)
                ->get(1)
                ->getRowArray();

            if ($permission === null) {
                continue;
            }

            $this->db->query(
                'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$roleId, (int) $permission['id']]
            );
        }
    }

    private function roleId(string $roleCode): int
    {
        $row = $this->db->table('roles')
            ->select('id')
            ->where('tenant_id', 1)
            ->where('code', $roleCode)
            ->get(1)
            ->getRowArray();

        return $row === null ? 0 : (int) $row['id'];
    }

    private function assignRoleToUserEmail(string $email, string $roleCode): void
    {
        $user = $this->db->table('users')
            ->select('id')
            ->where('tenant_id', 1)
            ->where('email', $email)
            ->get(1)
            ->getRowArray();
        $roleId = $this->roleId($roleCode);

        if ($user === null || $roleId <= 0) {
            return;
        }

        $this->db->query(
            'INSERT IGNORE INTO user_role_assignments (user_id, role_id) VALUES (?, ?)',
            [(int) $user['id'], $roleId]
        );
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddComplianceAuditViewer extends Migration
{
    private string $email = 'compliance@qsi.local';

    public function up(): void
    {
        $this->db->query(
            'INSERT IGNORE INTO roles (tenant_id, code, name, description, system_role) VALUES (1, ?, ?, ?, 1)',
            [
                'compliance_auditor',
                'Compliance Audit Viewer',
                'Read-only compliance audit access to certification cycles, audit files and controlled report outputs.',
            ]
        );

        $roleId = $this->roleId('compliance_auditor');
        if ($roleId <= 0) {
            return;
        }

        $this->db->table('role_permissions')->where('role_id', $roleId)->delete();

        $permissions = [
            'dashboard' => ['view'],
            'clients' => ['view'],
            'standards' => ['view'],
            'application_reviews' => ['view', 'download', 'print'],
            'proposals' => ['view', 'download', 'print'],
            'contracts' => ['view', 'download', 'print'],
            'audit_programs' => ['view', 'download', 'print'],
            'auditor_appointments' => ['view', 'download', 'print'],
            'audit_plans' => ['view', 'download', 'print'],
            'reports' => ['view', 'download', 'print'],
            'ncrs' => ['view', 'download', 'print'],
            'capas' => ['view', 'download', 'print'],
            'technical_reviews' => ['view', 'download', 'print'],
            'certification_decisions' => ['view', 'download', 'print'],
            'certificates' => ['view', 'download', 'print'],
            'document_templates' => ['view', 'download', 'print'],
            'clause_library' => ['view'],
        ];

        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                $this->grant($roleId, $module, $action);
            }
        }

        $this->ensureUser($roleId);
    }

    public function down(): void
    {
        $roleId = $this->roleId('compliance_auditor');
        if ($roleId > 0) {
            $this->db->table('role_permissions')->where('role_id', $roleId)->delete();
            $this->db->table('roles')->where('id', $roleId)->delete();
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

    private function grant(int $roleId, string $module, string $action): void
    {
        $permission = $this->db->table('permissions')
            ->select('id')
            ->where('module', $module)
            ->where('action', $action)
            ->get(1)
            ->getRowArray();

        if ($permission === null) {
            return;
        }

        $this->db->query(
            'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
            [$roleId, (int) $permission['id']]
        );
    }

    private function ensureUser(int $roleId): void
    {
        $user = $this->db->table('users')
            ->select('id')
            ->where('tenant_id', 1)
            ->where('email', $this->email)
            ->get(1)
            ->getRowArray();

        if ($user === null) {
            $seedPassword = trim((string) env('seeds.complianceViewerPassword', ''));
            $password = $seedPassword === '' ? bin2hex(random_bytes(18)) : $seedPassword;

            $this->db->table('users')->insert([
                'tenant_id' => 1,
                'primary_role_id' => $roleId,
                'full_name' => 'Compliance Audit Viewer',
                'email' => $this->email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'active',
                'must_change_password' => 1,
            ]);
            $userId = (int) $this->db->insertID();
        } else {
            $userId = (int) $user['id'];
            $this->db->table('users')
                ->where('id', $userId)
                ->update([
                    'primary_role_id' => $roleId,
                    'status' => 'active',
                    'must_change_password' => 1,
                ]);
        }

        $this->db->query(
            'INSERT IGNORE INTO user_role_assignments (user_id, role_id) VALUES (?, ?)',
            [$userId, $roleId]
        );
    }
}

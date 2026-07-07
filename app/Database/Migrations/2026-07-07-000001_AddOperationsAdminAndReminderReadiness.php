<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOperationsAdminAndReminderReadiness extends Migration
{
    public function up(): void
    {
        foreach ([
            ['operations', 'view', 'View production readiness and operating controls'],
            ['users', 'view', 'View user administration'],
            ['users', 'create', 'Create users'],
            ['users', 'edit', 'Edit users and role assignments'],
            ['users', 'delete', 'Deactivate users'],
            ['reminders', 'view', 'View reminder status'],
            ['reminders', 'process', 'Process due reminders'],
            ['leads', 'view', 'View website leads'],
            ['leads', 'edit', 'Qualify and assign website leads'],
        ] as [$module, $action, $description]) {
            $this->db->query(
                'INSERT IGNORE INTO permissions (module, action, description) VALUES (?, ?, ?)',
                [$module, $action, $description]
            );
        }

        foreach (['super_admin', 'administrator'] as $roleCode) {
            $role = $this->db->table('roles')
                ->select('id')
                ->where('tenant_id', 1)
                ->where('code', $roleCode)
                ->get()
                ->getRowArray();

            if ($role === null) {
                continue;
            }

            $this->db->query(
                "INSERT IGNORE INTO role_permissions (role_id, permission_id)
                 SELECT ?, id FROM permissions WHERE module IN ('operations', 'users', 'reminders', 'leads')",
                [(int) $role['id']]
            );
        }

        if ($this->db->tableExists('audit_reminders')) {
            $this->addAuditReminderColumn('tenant_id', 'BIGINT UNSIGNED NULL AFTER id');
            $this->addAuditReminderColumn('client_id', 'BIGINT UNSIGNED NULL AFTER tenant_id');
            $this->addAuditReminderColumn('title', 'VARCHAR(190) NULL AFTER reminder_type');
            $this->addAuditReminderColumn('message', 'TEXT NULL AFTER title');

            $this->db->query('ALTER TABLE audit_reminders MODIFY audit_event_id BIGINT UNSIGNED NULL');
            $this->addIndexIfMissing('audit_reminders', 'idx_audit_reminders_tenant_status', 'tenant_id, status, due_date');
            $this->addIndexIfMissing('audit_reminders', 'idx_audit_reminders_client', 'client_id, reminder_type, due_date');
        }

        if (! $this->db->tableExists('website_leads')) {
            $this->db->query(<<<SQL
CREATE TABLE website_leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(80) NOT NULL DEFAULT 'website',
    company VARCHAR(220) NOT NULL,
    contact_person VARCHAR(180) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    service_interest VARCHAR(180) NULL,
    message TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'new',
    assigned_to BIGINT UNSIGNED NULL,
    converted_client_id BIGINT UNSIGNED NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    qualified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_website_leads_tenant_status (tenant_id, status, received_at),
    KEY idx_website_leads_assigned (assigned_to, status),
    CONSTRAINT fk_website_leads_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_website_leads_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_website_leads_client FOREIGN KEY (converted_client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('website_leads')) {
            $this->db->disableForeignKeyChecks();
            $this->db->query('DROP TABLE website_leads');
            $this->db->enableForeignKeyChecks();
        }

        $permissionIds = $this->db->table('permissions')
            ->select('id')
            ->whereIn('module', ['operations', 'users', 'reminders', 'leads'])
            ->get()
            ->getResultArray();

        if ($permissionIds !== []) {
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $permissionIds);
            $this->db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
            $this->db->table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    private function addAuditReminderColumn(string $column, string $definition): void
    {
        if (! $this->db->fieldExists($column, 'audit_reminders')) {
            $this->db->query('ALTER TABLE audit_reminders ADD ' . $column . ' ' . $definition);
        }
    }

    private function addIndexIfMissing(string $table, string $index, string $columns): void
    {
        $row = $this->db->query(
            'SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?',
            [$index]
        )->getRowArray();

        if ($row === null) {
            $this->db->query('ALTER TABLE ' . $table . ' ADD KEY ' . $index . ' (' . $columns . ')');
        }
    }
}

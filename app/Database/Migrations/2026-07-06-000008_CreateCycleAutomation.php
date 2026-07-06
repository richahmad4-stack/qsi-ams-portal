<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCycleAutomation extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('automation_runs')) {
            $this->db->query(<<<SQL
CREATE TABLE automation_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NULL,
    run_number VARCHAR(80) NOT NULL,
    module VARCHAR(80) NOT NULL DEFAULT 'cycle_generator',
    status VARCHAR(40) NOT NULL DEFAULT 'previewed',
    input_payload JSON NOT NULL,
    preview_payload JSON NULL,
    generated_payload JSON NULL,
    warning_payload JSON NULL,
    generated_by BIGINT UNSIGNED NULL,
    generated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_automation_runs_number (tenant_id, run_number),
    KEY idx_automation_runs_client (client_id),
    KEY idx_automation_runs_status (tenant_id, status, created_at),
    CONSTRAINT fk_automation_runs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_automation_runs_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_automation_runs_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        foreach (['view', 'create', 'edit'] as $action) {
            $this->db->query(
                'INSERT IGNORE INTO permissions (module, action, description) VALUES (?, ?, ?)',
                ['automation', $action, 'Automation / Cycle Generator ' . $action]
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
                 SELECT ?, id FROM permissions WHERE module = 'automation'",
                [(int) $role['id']]
            );
        }
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        if ($this->db->tableExists('automation_runs')) {
            $this->db->query('DROP TABLE automation_runs');
        }
        $this->db->enableForeignKeyChecks();
    }
}

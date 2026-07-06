<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveLegacyImportFeature extends Migration
{
    public function up(): void
    {
        $permissionIds = array_column(
            $this->db->table('permissions')
                ->select('id')
                ->where('module', 'legacy_imports')
                ->get()
                ->getResultArray(),
            'id'
        );

        if ($permissionIds !== []) {
            $this->db->table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        $this->db->table('permissions')->where('module', 'legacy_imports')->delete();
    }

    public function down(): void
    {
        foreach (['view', 'create', 'edit', 'delete', 'approve', 'reject', 'download', 'print'] as $action) {
            $this->db->query(
                'INSERT IGNORE INTO permissions (module, action, description) VALUES (?, ?, ?)',
                ['legacy_imports', $action, ucfirst($action) . ' Legacy Imports']
            );
        }
    }
}

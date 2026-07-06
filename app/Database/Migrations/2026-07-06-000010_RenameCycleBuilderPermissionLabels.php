<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RenameCycleBuilderPermissionLabels extends Migration
{
    public function up()
    {
        foreach (['view', 'create', 'edit'] as $action) {
            $this->db->table('permissions')
                ->where('module', 'automation')
                ->where('action', $action)
                ->update(['description' => 'Cycle Builder ' . $action]);
        }

        $this->db->table('roles')
            ->like('description', 'demo access role')
            ->update(['description' => 'System role.']);
    }

    public function down()
    {
        foreach (['view', 'create', 'edit'] as $action) {
            $this->db->table('permissions')
                ->where('module', 'automation')
                ->where('action', $action)
                ->update(['description' => 'Automation / Cycle Generator ' . $action]);
        }
    }
}

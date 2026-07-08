<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddClientLogoPath extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('clients') || $this->db->fieldExists('client_logo_path', 'clients')) {
            return;
        }

        $this->forge->addColumn('clients', [
            'client_logo_path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
                'after' => 'website',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->tableExists('clients') && $this->db->fieldExists('client_logo_path', 'clients')) {
            $this->forge->dropColumn('clients', 'client_logo_path');
        }
    }
}

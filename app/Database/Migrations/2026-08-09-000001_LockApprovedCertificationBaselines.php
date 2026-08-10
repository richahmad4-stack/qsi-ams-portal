<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LockApprovedCertificationBaselines extends Migration
{
    public function up()
    {
        $approved = [
            'ISO 9001:2015',
            'ISO 14001:2015',
            'ISO 45001:2018',
            'ISO 22000:2018',
            'HACCP',
        ];

        $this->db->table('standards')->whereNotIn('code', $approved)->update(['active' => 0]);
        $this->db->table('standards')->whereIn('code', $approved)->update(['active' => 1]);
        $this->db->table('standards')->where('code', 'HACCP')->update([
            'name' => 'HACCP / Codex General Principles of Food Hygiene',
            'version' => 'CXC 1-1969',
            'scheme_type' => 'food_safety',
        ]);
    }

    public function down()
    {
        // Historical unsupported schemes remain inactive by design.
    }
}

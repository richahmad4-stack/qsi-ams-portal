<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixSessionTimestampColumn extends Migration
{
    public function up(): void
    {
        $this->db->query('TRUNCATE TABLE ci_sessions');
        $this->db->query('ALTER TABLE ci_sessions MODIFY `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        $this->db->query('TRUNCATE TABLE ci_sessions');
        $this->db->query('ALTER TABLE ci_sessions MODIFY `timestamp` INT UNSIGNED NOT NULL DEFAULT 0');
    }
}

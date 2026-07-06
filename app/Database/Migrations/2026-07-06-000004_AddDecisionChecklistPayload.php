<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDecisionChecklistPayload extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('certification_decisions', 'decision_payload')) {
            $this->db->query('ALTER TABLE certification_decisions ADD decision_payload JSON NULL AFTER electronic_signature');
        }
    }

    public function down(): void
    {
        if ($this->columnExists('certification_decisions', 'decision_payload')) {
            $this->db->query('ALTER TABLE certification_decisions DROP COLUMN decision_payload');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->db->escape($column))
            ->getRowArray() !== null;
    }
}

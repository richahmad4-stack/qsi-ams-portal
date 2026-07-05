<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddClientLinkToPersonnel extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('personnel', 'client_id')) {
            $this->db->query(
                'ALTER TABLE personnel
                    ADD client_id BIGINT UNSIGNED NULL AFTER user_id,
                    ADD KEY idx_personnel_client (client_id),
                    ADD CONSTRAINT fk_personnel_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        if (! $this->columnExists('personnel', 'client_id')) {
            return;
        }

        if ($this->foreignKeyExists('personnel', 'fk_personnel_client')) {
            $this->db->query('ALTER TABLE personnel DROP FOREIGN KEY fk_personnel_client');
        }

        if ($this->indexExists('personnel', 'idx_personnel_client')) {
            $this->db->query('ALTER TABLE personnel DROP INDEX idx_personnel_client');
        }

        $this->db->query('ALTER TABLE personnel DROP COLUMN client_id');
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->db->escape($column))
            ->getRowArray() !== null;
    }

    private function indexExists(string $table, string $index): bool
    {
        return $this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ' . $this->db->escape($index))
            ->getRowArray() !== null;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return $this->db->query(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        )->getRowArray() !== null;
    }
}

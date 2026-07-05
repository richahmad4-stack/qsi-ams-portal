<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFinalWorkflowSupport extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('certification_decisions', 'gm_approved_by_user_id')) {
            $this->db->query(
                'ALTER TABLE certification_decisions
                    ADD gm_approved_by_user_id BIGINT UNSIGNED NULL AFTER status,
                    ADD gm_approval_notes TEXT NULL AFTER gm_approved_by_user_id,
                    ADD gm_approved_at DATETIME NULL AFTER gm_approval_notes,
                    ADD KEY idx_certification_decisions_gm_user (gm_approved_by_user_id),
                    ADD CONSTRAINT fk_certification_decisions_gm_user FOREIGN KEY (gm_approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL'
            );
        }

        if (! $this->db->tableExists('client_feedback')) {
            $this->db->query(<<<SQL
CREATE TABLE client_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    audit_program_id BIGINT UNSIGNED NULL,
    certificate_id BIGINT UNSIGNED NULL,
    contact_name VARCHAR(180) NULL,
    contact_email VARCHAR(190) NULL,
    submitted_at DATETIME NULL,
    overall_rating TINYINT UNSIGNED NULL,
    communication_rating TINYINT UNSIGNED NULL,
    auditor_rating TINYINT UNSIGNED NULL,
    report_quality_rating TINYINT UNSIGNED NULL,
    comments TEXT NULL,
    improvement_suggestion TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_client_feedback_client (tenant_id, client_id),
    KEY idx_client_feedback_program (audit_program_id),
    KEY idx_client_feedback_certificate (certificate_id),
    KEY idx_client_feedback_created_by (created_by),
    CONSTRAINT fk_client_feedback_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_client_feedback_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_client_feedback_program FOREIGN KEY (audit_program_id) REFERENCES audit_programs(id) ON DELETE SET NULL,
    CONSTRAINT fk_client_feedback_certificate FOREIGN KEY (certificate_id) REFERENCES certificates(id) ON DELETE SET NULL,
    CONSTRAINT fk_client_feedback_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('client_feedback')) {
            $this->db->query('DROP TABLE client_feedback');
        }

        if ($this->columnExists('certification_decisions', 'gm_approved_by_user_id')) {
            $this->db->query(
                'ALTER TABLE certification_decisions
                    DROP FOREIGN KEY fk_certification_decisions_gm_user,
                    DROP KEY idx_certification_decisions_gm_user,
                    DROP COLUMN gm_approved_at,
                    DROP COLUMN gm_approval_notes,
                    DROP COLUMN gm_approved_by_user_id'
            );
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->db->escape($column))
            ->getRowArray() !== null;
    }
}

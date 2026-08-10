<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateControlledIntegratedRequirementModel extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('validation_status', 'clause_library')) {
            $this->db->query(
                "ALTER TABLE clause_library
                    ADD validation_status VARCHAR(40) NOT NULL DEFAULT 'unverified' AFTER stage_applicability,
                    ADD source_reference VARCHAR(255) NULL AFTER validation_status,
                    ADD validated_by_user_id BIGINT UNSIGNED NULL AFTER source_reference,
                    ADD validated_at DATETIME NULL AFTER validated_by_user_id,
                    ADD KEY idx_clause_library_validation (tenant_id, validation_status, active)"
            );
        }

        // Quarantine the generic placeholder pattern without touching manually authored content.
        $this->db->table('clause_library')
            ->like('requirement', 'Internal audit checklist question for ', 'after')
            ->update([
                'active' => 0,
                'validation_status' => 'quarantined_synthetic',
                'source_reference' => 'Placeholder seeder pattern; authoritative mapping required.',
            ]);

        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS integrated_audit_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    requirement_code VARCHAR(120) NOT NULL,
    title VARCHAR(255) NOT NULL,
    audit_question TEXT NOT NULL,
    evidence_guidance TEXT NULL,
    requirement_family VARCHAR(80) NOT NULL DEFAULT 'management_system',
    stage_applicability VARCHAR(80) NOT NULL DEFAULT 'all',
    source_type VARCHAR(40) NOT NULL DEFAULT 'qsi_authored',
    version_no INT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_integrated_requirement_version (tenant_id, requirement_code, version_no),
    KEY idx_integrated_requirement_active (tenant_id, active, requirement_family),
    CONSTRAINT fk_integrated_requirement_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_integrated_requirement_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_integrated_requirement_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS integrated_requirement_clauses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_requirement_id BIGINT UNSIGNED NOT NULL,
    standard_id BIGINT UNSIGNED NOT NULL,
    clause_library_id BIGINT UNSIGNED NULL,
    clause_reference VARCHAR(80) NOT NULL,
    clause_title_snapshot VARCHAR(255) NULL,
    mapping_role VARCHAR(40) NOT NULL DEFAULT 'primary',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_integrated_requirement_clause (audit_requirement_id, standard_id, clause_reference),
    KEY idx_integrated_clause_standard (standard_id, clause_reference),
    KEY idx_integrated_clause_library (clause_library_id),
    CONSTRAINT fk_integrated_clause_requirement FOREIGN KEY (audit_requirement_id) REFERENCES integrated_audit_requirements(id) ON DELETE CASCADE,
    CONSTRAINT fk_integrated_clause_standard FOREIGN KEY (standard_id) REFERENCES standards(id),
    CONSTRAINT fk_integrated_clause_library FOREIGN KEY (clause_library_id) REFERENCES clause_library(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS audit_requirement_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_draft_id BIGINT UNSIGNED NOT NULL,
    audit_requirement_id BIGINT UNSIGNED NOT NULL,
    response_text LONGTEXT NULL,
    objective_evidence LONGTEXT NULL,
    finding_type VARCHAR(40) NOT NULL DEFAULT 'pending',
    source_type VARCHAR(40) NOT NULL DEFAULT 'manual',
    auditor_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    confirmed_by_user_id BIGINT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_audit_requirement_response (report_draft_id, audit_requirement_id),
    KEY idx_audit_response_confirmation (report_draft_id, auditor_confirmed),
    CONSTRAINT fk_audit_response_report FOREIGN KEY (report_draft_id) REFERENCES report_drafts(id) ON DELETE CASCADE,
    CONSTRAINT fk_audit_response_requirement FOREIGN KEY (audit_requirement_id) REFERENCES integrated_audit_requirements(id),
    CONSTRAINT fk_audit_response_confirmed_by FOREIGN KEY (confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_response_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS audit_response_clause_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_requirement_response_id BIGINT UNSIGNED NOT NULL,
    standard_id BIGINT UNSIGNED NOT NULL,
    clause_library_id BIGINT UNSIGNED NULL,
    standard_code_snapshot VARCHAR(120) NOT NULL,
    clause_reference_snapshot VARCHAR(80) NOT NULL,
    clause_title_snapshot VARCHAR(255) NULL,
    mapping_role VARCHAR(40) NOT NULL DEFAULT 'primary',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_audit_response_clause_snapshot (audit_requirement_response_id, standard_id, clause_reference_snapshot),
    KEY idx_audit_response_clause_standard (standard_id, clause_reference_snapshot),
    CONSTRAINT fk_audit_response_clause_response FOREIGN KEY (audit_requirement_response_id) REFERENCES audit_requirement_responses(id) ON DELETE CASCADE,
    CONSTRAINT fk_audit_response_clause_standard FOREIGN KEY (standard_id) REFERENCES standards(id),
    CONSTRAINT fk_audit_response_clause_library FOREIGN KEY (clause_library_id) REFERENCES clause_library(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        if (! $this->db->fieldExists('audit_requirement_response_id', 'ncrs')) {
            $this->db->query(
                'ALTER TABLE ncrs
                    ADD audit_requirement_response_id BIGINT UNSIGNED NULL AFTER clause_library_id,
                    ADD KEY idx_ncrs_requirement_response (audit_requirement_response_id),
                    ADD CONSTRAINT fk_ncrs_requirement_response FOREIGN KEY (audit_requirement_response_id) REFERENCES audit_requirement_responses(id) ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('audit_requirement_response_id', 'ncrs')) {
            $this->db->query('ALTER TABLE ncrs DROP FOREIGN KEY fk_ncrs_requirement_response, DROP KEY idx_ncrs_requirement_response, DROP COLUMN audit_requirement_response_id');
        }

        foreach (['audit_response_clause_snapshots', 'audit_requirement_responses', 'integrated_requirement_clauses', 'integrated_audit_requirements'] as $table) {
            $this->forge->dropTable($table, true);
        }

        if ($this->db->fieldExists('validation_status', 'clause_library')) {
            $this->db->query('ALTER TABLE clause_library DROP KEY idx_clause_library_validation');
            foreach (['validated_at', 'validated_by_user_id', 'source_reference', 'validation_status'] as $column) {
                if ($this->db->fieldExists($column, 'clause_library')) {
                    $this->forge->dropColumn('clause_library', $column);
                }
            }
        }
    }
}

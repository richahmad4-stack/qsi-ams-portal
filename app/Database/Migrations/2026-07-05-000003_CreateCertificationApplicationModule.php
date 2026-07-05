<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCertificationApplicationModule extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('certification_applications')) {
            return;
        }

        $this->db->query(<<<SQL
CREATE TABLE question_library (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_key VARCHAR(120) NOT NULL,
    question_text VARCHAR(500) NOT NULL,
    question_type VARCHAR(40) NOT NULL DEFAULT 'text',
    applicable_standards JSON NOT NULL,
    mandatory TINYINT(1) NOT NULL DEFAULT 0,
    section VARCHAR(120) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    validation_rules JSON NULL,
    help_text VARCHAR(500) NULL,
    default_answer TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_question_library_key (question_key),
    KEY idx_question_library_section (section, display_order),
    KEY idx_question_library_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<SQL
CREATE TABLE certification_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    application_number VARCHAR(80) NOT NULL,
    document_number VARCHAR(40) NOT NULL DEFAULT 'F 25',
    revision_number VARCHAR(20) NOT NULL DEFAULT '1',
    issue_number VARCHAR(20) NOT NULL DEFAULT '2',
    issue_date DATE NOT NULL DEFAULT '2024-11-01',
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    declaration_name VARCHAR(180) NULL,
    declaration_position VARCHAR(180) NULL,
    declaration_date DATE NULL,
    cb_review_status VARCHAR(40) NULL,
    cb_review_notes TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_certification_applications_number (tenant_id, application_number),
    KEY idx_certification_applications_client (tenant_id, client_id),
    CONSTRAINT fk_certification_applications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_certification_applications_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_certification_applications_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_certification_applications_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<SQL
CREATE TABLE application_selected_standards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    standard_id BIGINT UNSIGNED NOT NULL,
    standard_code VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application_selected_standard (application_id, standard_id),
    KEY idx_application_selected_standard_code (standard_code),
    CONSTRAINT fk_application_selected_standards_application FOREIGN KEY (application_id) REFERENCES certification_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_selected_standards_standard FOREIGN KEY (standard_id) REFERENCES standards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<SQL
CREATE TABLE application_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    question_library_id BIGINT UNSIGNED NOT NULL,
    question_key VARCHAR(120) NOT NULL,
    question_text VARCHAR(500) NOT NULL,
    question_type VARCHAR(40) NOT NULL,
    section VARCHAR(120) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    mandatory TINYINT(1) NOT NULL DEFAULT 0,
    validation_rules JSON NULL,
    help_text VARCHAR(500) NULL,
    standard_codes JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application_questions_key (application_id, question_key),
    KEY idx_application_questions_section (application_id, section, display_order),
    CONSTRAINT fk_application_questions_application FOREIGN KEY (application_id) REFERENCES certification_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_questions_library FOREIGN KEY (question_library_id) REFERENCES question_library(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<SQL
CREATE TABLE application_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    application_question_id BIGINT UNSIGNED NOT NULL,
    question_library_id BIGINT UNSIGNED NOT NULL,
    answer_text LONGTEXT NULL,
    answered_by BIGINT UNSIGNED NULL,
    answered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application_answers_question (application_id, application_question_id),
    KEY idx_application_answers_library (question_library_id),
    CONSTRAINT fk_application_answers_application FOREIGN KEY (application_id) REFERENCES certification_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_answers_question FOREIGN KEY (application_question_id) REFERENCES application_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_answers_library FOREIGN KEY (question_library_id) REFERENCES question_library(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_answers_user FOREIGN KEY (answered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<SQL
CREATE TABLE application_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    application_question_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    category VARCHAR(120) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_application_attachments_application (application_id, category),
    CONSTRAINT fk_application_attachments_application FOREIGN KEY (application_id) REFERENCES certification_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_attachments_question FOREIGN KEY (application_question_id) REFERENCES application_questions(id) ON DELETE SET NULL,
    CONSTRAINT fk_application_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        foreach ([
            'application_attachments',
            'application_answers',
            'application_questions',
            'application_selected_standards',
            'certification_applications',
            'question_library',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->query('DROP TABLE ' . $table);
            }
        }
        $this->db->enableForeignKeyChecks();
    }
}

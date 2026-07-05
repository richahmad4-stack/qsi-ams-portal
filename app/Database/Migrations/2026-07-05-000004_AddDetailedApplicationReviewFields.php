<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDetailedApplicationReviewFields extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('application_reviews', 'document_number')) {
            $this->db->query(
                "ALTER TABLE application_reviews
                    ADD application_review_number VARCHAR(80) NULL AFTER questionnaire_response_id,
                    ADD certification_application_id BIGINT UNSIGNED NULL AFTER application_review_number,
                    ADD document_number VARCHAR(40) NOT NULL DEFAULT 'F 28' AFTER certification_application_id,
                    ADD revision_number VARCHAR(20) NOT NULL DEFAULT '4' AFTER document_number,
                    ADD issue_number VARCHAR(20) NOT NULL DEFAULT '2' AFTER revision_number,
                    ADD document_date DATE NOT NULL DEFAULT '2025-02-01' AFTER issue_number,
                    ADD review_payload JSON NULL AFTER review_notes,
                    ADD technical_reviewer_name VARCHAR(180) NULL AFTER reviewed_at,
                    ADD technical_review_date DATE NULL AFTER technical_reviewer_name,
                    ADD quality_manager_status VARCHAR(60) NULL AFTER technical_review_date,
                    ADD quality_manager_comments TEXT NULL AFTER quality_manager_status,
                    ADD quality_manager_name VARCHAR(180) NULL AFTER quality_manager_comments,
                    ADD quality_manager_date DATE NULL AFTER quality_manager_name,
                    ADD general_manager_status VARCHAR(60) NULL AFTER quality_manager_date,
                    ADD general_manager_comments TEXT NULL AFTER general_manager_status,
                    ADD general_manager_name VARCHAR(180) NULL AFTER general_manager_comments,
                    ADD general_manager_date DATE NULL AFTER general_manager_name,
                    ADD KEY idx_application_reviews_cert_app (certification_application_id),
                    ADD CONSTRAINT fk_application_reviews_cert_app FOREIGN KEY (certification_application_id) REFERENCES certification_applications(id) ON DELETE SET NULL"
            );
        }
    }

    public function down(): void
    {
        if ($this->columnExists('application_reviews', 'document_number')) {
            $this->db->query(
                'ALTER TABLE application_reviews
                    DROP FOREIGN KEY fk_application_reviews_cert_app,
                    DROP KEY idx_application_reviews_cert_app,
                    DROP COLUMN general_manager_date,
                    DROP COLUMN general_manager_name,
                    DROP COLUMN general_manager_comments,
                    DROP COLUMN general_manager_status,
                    DROP COLUMN quality_manager_date,
                    DROP COLUMN quality_manager_name,
                    DROP COLUMN quality_manager_comments,
                    DROP COLUMN quality_manager_status,
                    DROP COLUMN technical_review_date,
                    DROP COLUMN technical_reviewer_name,
                    DROP COLUMN review_payload,
                    DROP COLUMN document_date,
                    DROP COLUMN issue_number,
                    DROP COLUMN revision_number,
                    DROP COLUMN document_number,
                    DROP COLUMN certification_application_id,
                    DROP COLUMN application_review_number'
            );
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->db->escape($column))
            ->getRowArray() !== null;
    }
}

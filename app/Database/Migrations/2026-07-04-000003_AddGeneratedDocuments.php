<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGeneratedDocuments extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('generated_documents')) {
            return;
        }

        $this->db->query(<<<SQL
CREATE TABLE generated_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NULL,
    document_key VARCHAR(120) NOT NULL,
    document_title VARCHAR(255) NOT NULL,
    related_table VARCHAR(80) NULL,
    related_id BIGINT UNSIGNED NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL DEFAULT 'application/pdf',
    generated_by BIGINT UNSIGNED NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_generated_documents_client (tenant_id, client_id, generated_at),
    KEY idx_generated_documents_related (related_table, related_id),
    KEY idx_generated_documents_user (generated_by),
    CONSTRAINT fk_generated_documents_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_generated_documents_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_generated_documents_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        if ($this->db->tableExists('generated_documents')) {
            $this->db->query('DROP TABLE generated_documents');
        }
    }
}

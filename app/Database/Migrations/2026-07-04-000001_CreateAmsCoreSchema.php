<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAmsCoreSchema extends Migration
{
    private array $tables = [
        'global_search_index',
        'audit_logs',
        'notifications',
        'notification_rules',
        'document_template_versions',
        'document_templates',
        'certificate_public_events',
        'certificates',
        'certification_decisions',
        'technical_reviews',
        'management_review_actions',
        'management_reviews',
        'internal_audit_findings',
        'internal_audits',
        'capa_evidence',
        'capas',
        'ncr_evidence',
        'ncrs',
        'report_sections',
        'report_drafts',
        'clause_library',
        'audit_plan_items',
        'audit_plans',
        'auditor_appointments',
        'personnel_availability',
        'personnel_witness_audits',
        'personnel_documents',
        'personnel_competencies',
        'personnel',
        'audit_reminders',
        'audit_events',
        'audit_programs',
        'contract_versions',
        'contracts',
        'proposal_approvals',
        'proposal_versions',
        'proposal_line_items',
        'proposals',
        'payments',
        'invoices',
        'application_reviews',
        'questionnaire_responses',
        'questionnaire_questions',
        'questionnaire_versions',
        'client_attachments',
        'client_sites',
        'client_standards',
        'client_processes',
        'clients',
        'legacy_import_rows',
        'legacy_import_batches',
        'medical_device_categories',
        'food_chain_categories',
        'nace_codes',
        'iaf_codes',
        'standards',
        'user_role_assignments',
        'users',
        'role_permissions',
        'permissions',
        'roles',
        'ci_sessions',
        'tenants',
    ];

    public function up(): void
    {
        foreach ($this->statements() as $statement) {
            $this->db->query($statement);
        }
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();

        foreach ($this->tables as $table) {
            $this->db->query('DROP TABLE IF EXISTS `' . $table . '`');
        }

        $this->db->enableForeignKeyChecks();
    }

    private function statements(): array
    {
        return [
            <<<SQL
CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    legal_name VARCHAR(220) NOT NULL,
    code VARCHAR(40) NOT NULL,
    timezone VARCHAR(80) NOT NULL DEFAULT 'Asia/Riyadh',
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_tenants_code (code),
    KEY idx_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS ci_sessions (
    id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data BLOB NOT NULL,
    KEY ci_sessions_timestamp (timestamp),
    PRIMARY KEY (id, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(80) NOT NULL,
    description VARCHAR(500) NULL,
    system_role TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_roles_tenant_code (tenant_id, code),
    CONSTRAINT fk_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(80) NOT NULL,
    action VARCHAR(40) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permissions_module_action (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permissions (role_id, permission_id),
    KEY idx_role_permissions_permission (permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    primary_role_id BIGINT UNSIGNED NULL,
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_users_tenant_email (tenant_id, email),
    KEY idx_users_role (primary_role_id),
    KEY idx_users_status (tenant_id, status),
    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_users_primary_role FOREIGN KEY (primary_role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS user_role_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_role_assignments (user_id, role_id),
    KEY idx_user_role_assignments_role (role_id),
    CONSTRAINT fk_user_role_assignments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_role_assignments_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS standards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(180) NOT NULL,
    version VARCHAR(80) NULL,
    scheme_type VARCHAR(80) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_standards_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS iaf_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    title VARCHAR(180) NOT NULL,
    risk_level VARCHAR(40) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_iaf_codes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS nace_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    title VARCHAR(220) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nace_codes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS food_chain_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_food_chain_categories_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS medical_device_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_medical_device_categories_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS legacy_import_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(20) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    column_mapping JSON NOT NULL,
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    invalid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    duplicate_rows INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'preview',
    imported_by BIGINT UNSIGNED NULL,
    imported_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_legacy_import_batches_tenant (tenant_id, status),
    KEY idx_legacy_import_batches_user (imported_by),
    CONSTRAINT fk_legacy_import_batches_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_legacy_import_batches_user FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS legacy_import_rows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    row_number INT UNSIGNED NOT NULL,
    raw_payload JSON NOT NULL,
    normalized_payload JSON NULL,
    validation_errors JSON NULL,
    duplicate_key VARCHAR(190) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    client_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_legacy_import_rows_batch_status (batch_id, status),
    KEY idx_legacy_import_rows_client (client_id),
    CONSTRAINT fk_legacy_import_rows_batch FOREIGN KEY (batch_id) REFERENCES legacy_import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    legacy_import_batch_id BIGINT UNSIGNED NULL,
    company VARCHAR(220) NOT NULL,
    legal_name VARCHAR(220) NULL,
    address TEXT NULL,
    country VARCHAR(120) NULL,
    city VARCHAR(120) NULL,
    contact_person VARCHAR(180) NULL,
    designation VARCHAR(120) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    website VARCHAR(220) NULL,
    scope TEXT NULL,
    employee_count INT UNSIGNED NULL,
    permanent_employees INT UNSIGNED NULL,
    temporary_employees INT UNSIGNED NULL,
    shift_pattern VARCHAR(180) NULL,
    seasonal_operations VARCHAR(180) NULL,
    number_of_sites INT UNSIGNED NOT NULL DEFAULT 1,
    certification_status VARCHAR(60) NOT NULL DEFAULT 'enquiry',
    risk_category VARCHAR(60) NULL,
    certificate_number VARCHAR(80) NULL,
    initial_certification_date DATE NULL,
    certificate_issue_date DATE NULL,
    certificate_expiry_date DATE NULL,
    notes TEXT NULL,
    is_legacy TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_clients_tenant_certificate (tenant_id, certificate_number),
    KEY idx_clients_tenant_company (tenant_id, company),
    KEY idx_clients_status (tenant_id, certification_status),
    KEY idx_clients_expiry (tenant_id, certificate_expiry_date),
    KEY idx_clients_import_batch (legacy_import_batch_id),
    KEY idx_clients_created_by (created_by),
    CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_clients_import_batch FOREIGN KEY (legacy_import_batch_id) REFERENCES legacy_import_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_clients_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS client_processes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    process_name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_processes (client_id, process_name),
    CONSTRAINT fk_client_processes_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS client_standards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    standard_id BIGINT UNSIGNED NOT NULL,
    iaf_code_id BIGINT UNSIGNED NULL,
    nace_code_id BIGINT UNSIGNED NULL,
    food_chain_category_id BIGINT UNSIGNED NULL,
    medical_device_category_id BIGINT UNSIGNED NULL,
    scope TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_standards (client_id, standard_id),
    KEY idx_client_standards_standard (standard_id),
    KEY idx_client_standards_iaf (iaf_code_id),
    KEY idx_client_standards_nace (nace_code_id),
    KEY idx_client_standards_food (food_chain_category_id),
    KEY idx_client_standards_medical (medical_device_category_id),
    CONSTRAINT fk_client_standards_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_client_standards_standard FOREIGN KEY (standard_id) REFERENCES standards(id),
    CONSTRAINT fk_client_standards_iaf FOREIGN KEY (iaf_code_id) REFERENCES iaf_codes(id) ON DELETE SET NULL,
    CONSTRAINT fk_client_standards_nace FOREIGN KEY (nace_code_id) REFERENCES nace_codes(id) ON DELETE SET NULL,
    CONSTRAINT fk_client_standards_food FOREIGN KEY (food_chain_category_id) REFERENCES food_chain_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_client_standards_medical FOREIGN KEY (medical_device_category_id) REFERENCES medical_device_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS client_sites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    site_name VARCHAR(180) NOT NULL,
    address TEXT NULL,
    country VARCHAR(120) NULL,
    city VARCHAR(120) NULL,
    employee_count INT UNSIGNED NULL,
    processes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_client_sites_client (client_id, active),
    CONSTRAINT fk_client_sites_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS client_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    category VARCHAR(80) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT UNSIGNED NULL,
    checksum_sha256 CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_client_attachments_client (client_id, category),
    KEY idx_client_attachments_user (uploaded_by),
    CONSTRAINT fk_client_attachments_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_client_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS questionnaire_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    effective_from DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_questionnaire_versions (tenant_id, name, version_number),
    KEY idx_questionnaire_versions_created_by (created_by),
    KEY idx_questionnaire_versions_approved_by (approved_by),
    CONSTRAINT fk_questionnaire_versions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_questionnaire_versions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_questionnaire_versions_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS questionnaire_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    questionnaire_version_id BIGINT UNSIGNED NOT NULL,
    parent_question_id BIGINT UNSIGNED NULL,
    question_key VARCHAR(120) NOT NULL,
    question_text TEXT NOT NULL,
    answer_type VARCHAR(40) NOT NULL,
    options_json JSON NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    conditional_logic JSON NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_questionnaire_questions_key (questionnaire_version_id, question_key),
    KEY idx_questionnaire_questions_parent (parent_question_id),
    CONSTRAINT fk_questionnaire_questions_version FOREIGN KEY (questionnaire_version_id) REFERENCES questionnaire_versions(id) ON DELETE CASCADE,
    CONSTRAINT fk_questionnaire_questions_parent FOREIGN KEY (parent_question_id) REFERENCES questionnaire_questions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS questionnaire_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    questionnaire_version_id BIGINT UNSIGNED NOT NULL,
    submitted_by BIGINT UNSIGNED NULL,
    response_payload JSON NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_questionnaire_responses_client (client_id, status),
    KEY idx_questionnaire_responses_version (questionnaire_version_id),
    KEY idx_questionnaire_responses_user (submitted_by),
    CONSTRAINT fk_questionnaire_responses_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_questionnaire_responses_version FOREIGN KEY (questionnaire_version_id) REFERENCES questionnaire_versions(id),
    CONSTRAINT fk_questionnaire_responses_user FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS application_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    questionnaire_response_id BIGINT UNSIGNED NULL,
    technical_manager_id BIGINT UNSIGNED NULL,
    quality_manager_id BIGINT UNSIGNED NULL,
    completeness_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    risk_rating VARCHAR(40) NULL,
    recommendation VARCHAR(80) NULL,
    md5_duration_days DECIMAL(6,2) NULL,
    iso22003_duration_days DECIMAL(6,2) NULL,
    integrated_reduction_percent DECIMAL(5,2) NULL,
    stage1_days DECIMAL(6,2) NULL,
    stage2_days DECIMAL(6,2) NULL,
    review_notes TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_application_reviews_client (client_id, status),
    KEY idx_application_reviews_questionnaire (questionnaire_response_id),
    KEY idx_application_reviews_tm (technical_manager_id),
    KEY idx_application_reviews_qm (quality_manager_id),
    CONSTRAINT fk_application_reviews_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_reviews_questionnaire FOREIGN KEY (questionnaire_response_id) REFERENCES questionnaire_responses(id) ON DELETE SET NULL,
    CONSTRAINT fk_application_reviews_tm FOREIGN KEY (technical_manager_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_application_reviews_qm FOREIGN KEY (quality_manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(80) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoices_tenant_number (tenant_id, invoice_number),
    KEY idx_invoices_client (client_id, status),
    KEY idx_invoices_date (tenant_id, invoice_date),
    CONSTRAINT fk_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_invoices_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(60) NULL,
    reference_number VARCHAR(120) NULL,
    received_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payments_invoice (invoice_id),
    KEY idx_payments_received_by (received_by),
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_received_by FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS proposals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    application_review_id BIGINT UNSIGNED NULL,
    proposal_number VARCHAR(80) NOT NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    valid_until DATE NULL,
    certification_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    surveillance1_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    surveillance2_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    training_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    travel_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    accommodation_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    vat_percent DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_proposals_tenant_number (tenant_id, proposal_number),
    KEY idx_proposals_client (client_id, status),
    KEY idx_proposals_review (application_review_id),
    KEY idx_proposals_created_by (created_by),
    KEY idx_proposals_approved_by (approved_by),
    CONSTRAINT fk_proposals_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_proposals_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_proposals_review FOREIGN KEY (application_review_id) REFERENCES application_reviews(id) ON DELETE SET NULL,
    CONSTRAINT fk_proposals_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_proposals_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS proposal_line_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_proposal_line_items_proposal (proposal_id),
    CONSTRAINT fk_proposal_line_items_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS proposal_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    change_summary VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_proposal_versions (proposal_id, version_number),
    KEY idx_proposal_versions_user (created_by),
    CONSTRAINT fk_proposal_versions_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposal_versions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS proposal_approvals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id BIGINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(30) NOT NULL,
    comments TEXT NULL,
    decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proposal_approvals_proposal (proposal_id, decision),
    KEY idx_proposal_approvals_approver (approver_id),
    CONSTRAINT fk_proposal_approvals_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposal_approvals_approver FOREIGN KEY (approver_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    proposal_id BIGINT UNSIGNED NOT NULL,
    contract_number VARCHAR(80) NOT NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    signed_at DATETIME NULL,
    signed_by_name VARCHAR(180) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contracts_tenant_number (tenant_id, contract_number),
    KEY idx_contracts_client (client_id, status),
    KEY idx_contracts_proposal (proposal_id),
    KEY idx_contracts_created_by (created_by),
    CONSTRAINT fk_contracts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_contracts_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_contracts_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id),
    CONSTRAINT fk_contracts_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS contract_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contract_versions (contract_id, version_number),
    KEY idx_contract_versions_user (created_by),
    CONSTRAINT fk_contract_versions_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    CONSTRAINT fk_contract_versions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS audit_programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NULL,
    program_number VARCHAR(80) NOT NULL,
    cycle_type VARCHAR(40) NOT NULL DEFAULT 'initial',
    certificate_issue_date DATE NULL,
    certificate_expiry_date DATE NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'planned',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_audit_programs_tenant_number (tenant_id, program_number),
    KEY idx_audit_programs_client (client_id, status),
    KEY idx_audit_programs_contract (contract_id),
    KEY idx_audit_programs_created_by (created_by),
    CONSTRAINT fk_audit_programs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_audit_programs_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_audit_programs_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_programs_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS audit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_program_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    audit_number VARCHAR(80) NOT NULL,
    planned_start_date DATE NULL,
    planned_end_date DATE NULL,
    actual_start_date DATE NULL,
    actual_end_date DATE NULL,
    audit_window_start DATE NULL,
    audit_window_end DATE NULL,
    duration_days DECIMAL(6,2) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'planned',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_audit_events_number (audit_number),
    KEY idx_audit_events_program (audit_program_id, event_type),
    KEY idx_audit_events_window (audit_window_start, audit_window_end),
    CONSTRAINT fk_audit_events_program FOREIGN KEY (audit_program_id) REFERENCES audit_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS audit_reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_event_id BIGINT UNSIGNED NOT NULL,
    reminder_type VARCHAR(80) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_reminders_due (due_date, status),
    KEY idx_audit_reminders_event (audit_event_id),
    CONSTRAINT fk_audit_reminders_event FOREIGN KEY (audit_event_id) REFERENCES audit_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS personnel (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    personnel_type VARCHAR(80) NOT NULL,
    approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    languages JSON NULL,
    countries JSON NULL,
    experience_summary TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    KEY idx_personnel_tenant_type (tenant_id, personnel_type, approval_status),
    KEY idx_personnel_user (user_id),
    CONSTRAINT fk_personnel_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_personnel_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS personnel_competencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personnel_id BIGINT UNSIGNED NOT NULL,
    standard_id BIGINT UNSIGNED NULL,
    iaf_code_id BIGINT UNSIGNED NULL,
    food_chain_category_id BIGINT UNSIGNED NULL,
    medical_device_category_id BIGINT UNSIGNED NULL,
    competency_type VARCHAR(80) NOT NULL,
    valid_from DATE NULL,
    valid_until DATE NULL,
    approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    evidence_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_personnel_competencies_personnel (personnel_id, approval_status),
    KEY idx_personnel_competencies_standard (standard_id),
    KEY idx_personnel_competencies_expiry (valid_until),
    KEY idx_personnel_competencies_iaf (iaf_code_id),
    KEY idx_personnel_competencies_food (food_chain_category_id),
    KEY idx_personnel_competencies_medical (medical_device_category_id),
    CONSTRAINT fk_personnel_competencies_personnel FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    CONSTRAINT fk_personnel_competencies_standard FOREIGN KEY (standard_id) REFERENCES standards(id) ON DELETE SET NULL,
    CONSTRAINT fk_personnel_competencies_iaf FOREIGN KEY (iaf_code_id) REFERENCES iaf_codes(id) ON DELETE SET NULL,
    CONSTRAINT fk_personnel_competencies_food FOREIGN KEY (food_chain_category_id) REFERENCES food_chain_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_personnel_competencies_medical FOREIGN KEY (medical_device_category_id) REFERENCES medical_device_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS personnel_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personnel_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(80) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    valid_until DATE NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_personnel_documents_personnel (personnel_id, document_type),
    KEY idx_personnel_documents_expiry (valid_until),
    KEY idx_personnel_documents_user (uploaded_by),
    CONSTRAINT fk_personnel_documents_personnel FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    CONSTRAINT fk_personnel_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS personnel_witness_audits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personnel_id BIGINT UNSIGNED NOT NULL,
    audit_event_id BIGINT UNSIGNED NULL,
    witness_date DATE NOT NULL,
    witness_by BIGINT UNSIGNED NULL,
    result VARCHAR(40) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_personnel_witness_personnel (personnel_id, witness_date),
    KEY idx_personnel_witness_event (audit_event_id),
    KEY idx_personnel_witness_by (witness_by),
    CONSTRAINT fk_personnel_witness_personnel FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    CONSTRAINT fk_personnel_witness_event FOREIGN KEY (audit_event_id) REFERENCES audit_events(id) ON DELETE SET NULL,
    CONSTRAINT fk_personnel_witness_by FOREIGN KEY (witness_by) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS personnel_availability (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personnel_id BIGINT UNSIGNED NOT NULL,
    unavailable_from DATE NOT NULL,
    unavailable_to DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_personnel_availability_personnel (personnel_id, unavailable_from, unavailable_to),
    CONSTRAINT fk_personnel_availability_personnel FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS auditor_appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_event_id BIGINT UNSIGNED NOT NULL,
    personnel_id BIGINT UNSIGNED NOT NULL,
    appointment_role VARCHAR(60) NOT NULL,
    appointed_by BIGINT UNSIGNED NULL,
    appointed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(40) NOT NULL DEFAULT 'appointed',
    conflict_check_json JSON NULL,
    UNIQUE KEY uq_auditor_appointments_role (audit_event_id, personnel_id, appointment_role),
    KEY idx_auditor_appointments_personnel (personnel_id, appointment_role),
    KEY idx_auditor_appointments_user (appointed_by),
    CONSTRAINT fk_auditor_appointments_event FOREIGN KEY (audit_event_id) REFERENCES audit_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_auditor_appointments_personnel FOREIGN KEY (personnel_id) REFERENCES personnel(id),
    CONSTRAINT fk_auditor_appointments_user FOREIGN KEY (appointed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS audit_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_event_id BIGINT UNSIGNED NOT NULL,
    plan_number VARCHAR(80) NOT NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    prepared_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_audit_plans_number (plan_number),
    KEY idx_audit_plans_event (audit_event_id, status),
    KEY idx_audit_plans_prepared_by (prepared_by),
    KEY idx_audit_plans_approved_by (approved_by),
    CONSTRAINT fk_audit_plans_event FOREIGN KEY (audit_event_id) REFERENCES audit_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_audit_plans_prepared_by FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_plans_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS audit_plan_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_plan_id BIGINT UNSIGNED NOT NULL,
    audit_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    activity_type VARCHAR(80) NOT NULL,
    department VARCHAR(180) NULL,
    process_name VARCHAR(180) NULL,
    clauses VARCHAR(500) NULL,
    auditor_personnel_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_audit_plan_items_plan (audit_plan_id, audit_date, sort_order),
    KEY idx_audit_plan_items_auditor (auditor_personnel_id),
    CONSTRAINT fk_audit_plan_items_plan FOREIGN KEY (audit_plan_id) REFERENCES audit_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_audit_plan_items_auditor FOREIGN KEY (auditor_personnel_id) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS clause_library (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    standard_id BIGINT UNSIGNED NOT NULL,
    clause_number VARCHAR(60) NOT NULL,
    clause_title VARCHAR(255) NOT NULL,
    requirement TEXT NOT NULL,
    predefined_conformity_note TEXT NULL,
    positive_finding TEXT NULL,
    opportunity_for_improvement TEXT NULL,
    minor_nc TEXT NULL,
    major_nc TEXT NULL,
    evidence_examples TEXT NULL,
    auditor_guidance TEXT NULL,
    risk_rating VARCHAR(40) NULL,
    stage_applicability VARCHAR(80) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clause_library (tenant_id, standard_id, clause_number),
    KEY idx_clause_library_standard (standard_id, active),
    CONSTRAINT fk_clause_library_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_clause_library_standard FOREIGN KEY (standard_id) REFERENCES standards(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS report_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    audit_event_id BIGINT UNSIGNED NOT NULL,
    report_type VARCHAR(80) NOT NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    generated_payload JSON NOT NULL,
    editable_payload JSON NULL,
    prepared_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report_drafts_version (audit_event_id, report_type, version_number),
    KEY idx_report_drafts_tenant_status (tenant_id, status),
    KEY idx_report_drafts_prepared_by (prepared_by),
    KEY idx_report_drafts_approved_by (approved_by),
    CONSTRAINT fk_report_drafts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_report_drafts_event FOREIGN KEY (audit_event_id) REFERENCES audit_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_drafts_prepared_by FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_report_drafts_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS report_sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_draft_id BIGINT UNSIGNED NOT NULL,
    clause_library_id BIGINT UNSIGNED NULL,
    section_key VARCHAR(120) NOT NULL,
    section_title VARCHAR(255) NOT NULL,
    section_content MEDIUMTEXT NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_report_sections_report (report_draft_id, sort_order),
    KEY idx_report_sections_clause (clause_library_id),
    CONSTRAINT fk_report_sections_report FOREIGN KEY (report_draft_id) REFERENCES report_drafts(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_sections_clause FOREIGN KEY (clause_library_id) REFERENCES clause_library(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS ncrs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    audit_event_id BIGINT UNSIGNED NOT NULL,
    clause_library_id BIGINT UNSIGNED NULL,
    ncr_number VARCHAR(80) NOT NULL,
    requirement TEXT NOT NULL,
    finding TEXT NOT NULL,
    objective_evidence TEXT NOT NULL,
    classification VARCHAR(40) NOT NULL,
    correction TEXT NULL,
    root_cause TEXT NULL,
    corrective_action TEXT NULL,
    responsible_person VARCHAR(180) NULL,
    target_date DATE NULL,
    verification TEXT NULL,
    closure_notes TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    closed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ncrs_tenant_number (tenant_id, ncr_number),
    KEY idx_ncrs_event_status (audit_event_id, status),
    KEY idx_ncrs_clause (clause_library_id),
    KEY idx_ncrs_due (target_date, status),
    KEY idx_ncrs_created_by (created_by),
    CONSTRAINT fk_ncrs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_ncrs_event FOREIGN KEY (audit_event_id) REFERENCES audit_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_ncrs_clause FOREIGN KEY (clause_library_id) REFERENCES clause_library(id) ON DELETE SET NULL,
    CONSTRAINT fk_ncrs_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS ncr_evidence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ncr_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ncr_evidence_ncr (ncr_id),
    KEY idx_ncr_evidence_user (uploaded_by),
    CONSTRAINT fk_ncr_evidence_ncr FOREIGN KEY (ncr_id) REFERENCES ncrs(id) ON DELETE CASCADE,
    CONSTRAINT fk_ncr_evidence_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS capas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    ncr_id BIGINT UNSIGNED NULL,
    capa_number VARCHAR(80) NOT NULL,
    source VARCHAR(80) NOT NULL,
    issue TEXT NOT NULL,
    immediate_correction TEXT NULL,
    root_cause TEXT NULL,
    five_why JSON NULL,
    fishbone JSON NULL,
    corrective_action TEXT NULL,
    preventive_action TEXT NULL,
    responsible_person VARCHAR(180) NULL,
    target_date DATE NULL,
    verification TEXT NULL,
    effectiveness TEXT NULL,
    closure_notes TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    closed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_capas_tenant_number (tenant_id, capa_number),
    KEY idx_capas_source_status (source, status),
    KEY idx_capas_ncr (ncr_id),
    KEY idx_capas_due (target_date, status),
    KEY idx_capas_created_by (created_by),
    CONSTRAINT fk_capas_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_capas_ncr FOREIGN KEY (ncr_id) REFERENCES ncrs(id) ON DELETE SET NULL,
    CONSTRAINT fk_capas_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS capa_evidence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capa_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_capa_evidence_capa (capa_id),
    KEY idx_capa_evidence_user (uploaded_by),
    CONSTRAINT fk_capa_evidence_capa FOREIGN KEY (capa_id) REFERENCES capas(id) ON DELETE CASCADE,
    CONSTRAINT fk_capa_evidence_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS internal_audits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    audit_number VARCHAR(80) NOT NULL,
    scope TEXT NOT NULL,
    planned_date DATE NOT NULL,
    completed_date DATE NULL,
    checklist_json JSON NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'planned',
    lead_auditor_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_internal_audits_tenant_number (tenant_id, audit_number),
    KEY idx_internal_audits_date (tenant_id, planned_date, status),
    KEY idx_internal_audits_lead (lead_auditor_id),
    CONSTRAINT fk_internal_audits_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_internal_audits_lead FOREIGN KEY (lead_auditor_id) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS internal_audit_findings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internal_audit_id BIGINT UNSIGNED NOT NULL,
    capa_id BIGINT UNSIGNED NULL,
    finding_type VARCHAR(60) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_internal_audit_findings_audit (internal_audit_id, status),
    KEY idx_internal_audit_findings_capa (capa_id),
    CONSTRAINT fk_internal_audit_findings_audit FOREIGN KEY (internal_audit_id) REFERENCES internal_audits(id) ON DELETE CASCADE,
    CONSTRAINT fk_internal_audit_findings_capa FOREIGN KEY (capa_id) REFERENCES capas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS management_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    review_number VARCHAR(80) NOT NULL,
    meeting_date DATE NOT NULL,
    agenda JSON NOT NULL,
    inputs JSON NULL,
    outputs JSON NULL,
    minutes MEDIUMTEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'planned',
    chairperson_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_management_reviews_tenant_number (tenant_id, review_number),
    KEY idx_management_reviews_date (tenant_id, meeting_date, status),
    KEY idx_management_reviews_chair (chairperson_id),
    CONSTRAINT fk_management_reviews_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_management_reviews_chair FOREIGN KEY (chairperson_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS management_review_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    management_review_id BIGINT UNSIGNED NOT NULL,
    capa_id BIGINT UNSIGNED NULL,
    action_text TEXT NOT NULL,
    responsible_person VARCHAR(180) NULL,
    target_date DATE NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_management_review_actions_review (management_review_id, status),
    KEY idx_management_review_actions_capa (capa_id),
    KEY idx_management_review_actions_due (target_date, status),
    CONSTRAINT fk_management_review_actions_review FOREIGN KEY (management_review_id) REFERENCES management_reviews(id) ON DELETE CASCADE,
    CONSTRAINT fk_management_review_actions_capa FOREIGN KEY (capa_id) REFERENCES capas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS technical_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    audit_event_id BIGINT UNSIGNED NOT NULL,
    reviewer_personnel_id BIGINT UNSIGNED NOT NULL,
    checklist_payload JSON NOT NULL,
    competency_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    duration_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    application_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    reports_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    ncr_capa_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    scope_dates_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    impartiality_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    recommendation VARCHAR(80) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_technical_reviews_event (audit_event_id),
    KEY idx_technical_reviews_tenant_status (tenant_id, status),
    KEY idx_technical_reviews_reviewer (reviewer_personnel_id),
    CONSTRAINT fk_technical_reviews_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_technical_reviews_event FOREIGN KEY (audit_event_id) REFERENCES audit_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_technical_reviews_reviewer FOREIGN KEY (reviewer_personnel_id) REFERENCES personnel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS certification_decisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    technical_review_id BIGINT UNSIGNED NOT NULL,
    decision_maker_personnel_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(40) NOT NULL,
    reason TEXT NULL,
    electronic_signature VARCHAR(255) NULL,
    decided_at DATETIME NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_certification_decisions_review (technical_review_id),
    KEY idx_certification_decisions_tenant_status (tenant_id, status),
    KEY idx_certification_decisions_maker (decision_maker_personnel_id),
    CONSTRAINT fk_certification_decisions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_certification_decisions_review FOREIGN KEY (technical_review_id) REFERENCES technical_reviews(id) ON DELETE CASCADE,
    CONSTRAINT fk_certification_decisions_maker FOREIGN KEY (decision_maker_personnel_id) REFERENCES personnel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    certification_decision_id BIGINT UNSIGNED NULL,
    certificate_number VARCHAR(80) NOT NULL,
    standard_id BIGINT UNSIGNED NOT NULL,
    scope TEXT NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    initial_certification_date DATE NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    qr_payload VARCHAR(500) NOT NULL,
    public_slug VARCHAR(120) NOT NULL,
    suspended_at DATETIME NULL,
    withdrawn_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_certificates_tenant_number (tenant_id, certificate_number),
    UNIQUE KEY uq_certificates_public_slug (public_slug),
    KEY idx_certificates_client_status (client_id, status),
    KEY idx_certificates_standard (standard_id),
    KEY idx_certificates_expiry (tenant_id, expiry_date, status),
    KEY idx_certificates_decision (certification_decision_id),
    CONSTRAINT fk_certificates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_certificates_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_certificates_decision FOREIGN KEY (certification_decision_id) REFERENCES certification_decisions(id) ON DELETE SET NULL,
    CONSTRAINT fk_certificates_standard FOREIGN KEY (standard_id) REFERENCES standards(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS certificate_public_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_id BIGINT UNSIGNED NOT NULL,
    search_term VARCHAR(190) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_certificate_public_events_certificate (certificate_id, created_at),
    CONSTRAINT fk_certificate_public_events_certificate FOREIGN KEY (certificate_id) REFERENCES certificates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS document_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    template_key VARCHAR(120) NOT NULL,
    name VARCHAR(180) NOT NULL,
    document_type VARCHAR(80) NOT NULL,
    active_version INT UNSIGNED NULL,
    allowed_placeholders JSON NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_templates_key (tenant_id, template_key),
    CONSTRAINT fk_document_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS document_template_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_template_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    header_html MEDIUMTEXT NULL,
    footer_html MEDIUMTEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_template_versions (document_template_id, version_number),
    KEY idx_document_template_versions_created_by (created_by),
    KEY idx_document_template_versions_approved_by (approved_by),
    CONSTRAINT fk_document_template_versions_template FOREIGN KEY (document_template_id) REFERENCES document_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_document_template_versions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_document_template_versions_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS notification_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    rule_key VARCHAR(120) NOT NULL,
    channel VARCHAR(40) NOT NULL,
    trigger_event VARCHAR(120) NOT NULL,
    days_offset INT NOT NULL DEFAULT 0,
    recipient_roles JSON NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_rules (tenant_id, rule_key),
    CONSTRAINT fk_notification_rules_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    notification_rule_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'dashboard',
    related_module VARCHAR(80) NULL,
    related_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'unread',
    sent_at DATETIME NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user_status (user_id, status, created_at),
    KEY idx_notifications_tenant (tenant_id, created_at),
    KEY idx_notifications_rule (notification_rule_id),
    CONSTRAINT fk_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_rule FOREIGN KEY (notification_rule_id) REFERENCES notification_rules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    module VARCHAR(80) NOT NULL,
    entity_table VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_tenant_module (tenant_id, module, created_at),
    KEY idx_audit_logs_user (user_id, created_at),
    KEY idx_audit_logs_entity (entity_table, entity_id),
    CONSTRAINT fk_audit_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS global_search_index (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    module VARCHAR(80) NOT NULL,
    entity_table VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    keywords TEXT NULL,
    status VARCHAR(40) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_global_search_entity (tenant_id, entity_table, entity_id),
    FULLTEXT KEY ft_global_search (title, summary, keywords),
    KEY idx_global_search_module (tenant_id, module, status),
    CONSTRAINT fk_global_search_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        ];
    }
}

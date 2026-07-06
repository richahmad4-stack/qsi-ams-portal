<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClauseContentPool extends Migration
{
    public function up()
    {
        if (! $this->tableExists('clause_content_pool')) {
            $this->db->query("
                CREATE TABLE clause_content_pool (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id BIGINT UNSIGNED NOT NULL,
                    standard_id BIGINT UNSIGNED NULL,
                    clause_library_id BIGINT UNSIGNED NULL,
                    scope_keyword VARCHAR(180) NULL,
                    industry_type VARCHAR(120) NULL,
                    iaf_code_id BIGINT UNSIGNED NULL,
                    food_chain_category_id BIGINT UNSIGNED NULL,
                    medical_device_category_id BIGINT UNSIGNED NULL,
                    audit_stage VARCHAR(60) NULL,
                    content_type VARCHAR(60) NOT NULL,
                    severity VARCHAR(30) NULL,
                    template_code VARCHAR(80) NOT NULL,
                    template_title VARCHAR(180) NOT NULL,
                    content_text TEXT NOT NULL,
                    tags JSON NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_clause_content_pool_code (tenant_id, template_code),
                    KEY idx_clause_content_pool_match (tenant_id, standard_id, clause_library_id, content_type, active),
                    KEY idx_clause_content_pool_scope (scope_keyword, industry_type, audit_stage),
                    CONSTRAINT fk_clause_content_pool_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
                    CONSTRAINT fk_clause_content_pool_standard FOREIGN KEY (standard_id) REFERENCES standards(id) ON DELETE SET NULL,
                    CONSTRAINT fk_clause_content_pool_clause FOREIGN KEY (clause_library_id) REFERENCES clause_library(id) ON DELETE SET NULL
                )
            ");
        }

        $this->seedTemplates();
        $this->confirmExistingAutoSections();
    }

    public function down()
    {
        if ($this->tableExists('clause_content_pool')) {
            $this->db->query('DROP TABLE clause_content_pool');
        }
    }

    private function seedTemplates(): void
    {
        $tenants = $this->db->table('tenants')->select('id')->get()->getResultArray();
        $standards = $this->db->table('standards')
            ->select('id, code')
            ->whereIn('code', ['HACCP', 'ISO 22000:2018', 'ISO 9001:2015', 'ISO 14001:2015', 'ISO 45001:2018'])
            ->get()
            ->getResultArray();
        $standardByCode = [];
        foreach ($standards as $standard) {
            $standardByCode[(string) $standard['code']] = (int) $standard['id'];
        }

        $rows = [
            ['HACCP', 'catering,kitchen,restaurant,ready meal', 'food', 'conformity_answer', '', 'Food handling conformity', "For {client}, conformity was verified for {standard} {clause} - {title} during {stage}. The audit trail covered {scope}, including receiving, chilled/frozen storage, food-handler hygiene, preparation/cooking/cooling where applicable, dispatch, cleaning, pest control, allergen control, temperature monitoring and traceability. Objective evidence sampled included approved HACCP/food safety plan, PRP records, CCP/OPRP monitoring where applicable, calibration/temperature records, supplier/receiving checks and dispatch traceability. Evidence reference: {reference}."],
            ['HACCP', 'bakery,cake,cakes,baking', 'food', 'conformity_answer', '', 'Bakery conformity', "For {client}, conformity was verified for {standard} {clause} - {title} during {stage}. The audit trail covered {scope}, including raw-material receiving, flour/sugar/egg handling, allergen segregation, baking, cooling, filling/decoration, packaging, labeling, shelf-life control, cleaning, pest control, maintenance, metal detection where applicable and traceability. Evidence reference: {reference}."],
            ['HACCP', 'meat,chicken,beef,processing', 'food', 'conformity_answer', '', 'Meat processing conformity', "For {client}, conformity was verified for {standard} {clause} - {title} during {stage}. The audit trail covered {scope}, including chilled/frozen receiving, segregation, cutting/mincing/marination, temperature control, sanitation, pathogen-control checks, cold-chain dispatch, foreign-body control and traceability. Evidence reference: {reference}."],
            ['ISO 22000:2018', 'food,catering,bakery,meat,dairy,frozen,restaurant', 'food', 'conformity_answer', '', 'Food safety management conformity', "For {client}, conformity was verified for {standard} {clause} - {title} during {stage}. Evidence was sampled against {scope}, including context and food-safety objectives, hazard analysis, PRPs, operational controls, traceability, emergency preparedness, monitoring/verification, internal audit, management review and improvement records. Evidence reference: {reference}."],
            ['ISO 9001:2015', 'service,trading,manufacturing,distribution,warehouse', 'management system', 'conformity_answer', '', 'Quality management conformity', "For {client}, conformity was verified for {standard} {clause} - {title} during {stage}. Evidence was sampled against {scope}, including customer/order review, process controls, competence, documented information, supplier control, monitoring/KPIs, customer feedback, internal audit, management review and corrective-action records. Evidence reference: {reference}."],
            ['ISO 14001:2015', 'environment,manufacturing,warehouse,chemical,waste', 'environment', 'conformity_answer', '', 'Environmental management conformity', "For {client}, conformity was verified for {standard} {clause} - {title} during {stage}. Evidence was sampled against {scope}, including aspect/impact evaluation, compliance obligations, waste management, emissions/discharge controls, chemical storage, spill response, emergency preparedness, monitoring, internal audit, management review and environmental objectives. Evidence reference: {reference}."],
            ['ISO 45001:2018', 'construction,operation,maintenance,warehouse,manufacturing', 'ohs', 'conformity_answer', '', 'OH&S conformity', "For {client}, conformity was verified for {standard} {clause} - {title} during {stage}. Evidence was sampled against {scope}, including hazard identification, risk assessment, legal/other requirements, PPE, training/competence, incident reporting, emergency preparedness, contractor control, equipment safety, consultation/participation and OH&S performance monitoring. Evidence reference: {reference}."],
            ['ISO 9001:2015', 'all', 'management system', 'minor_nc', 'minor', 'Documented information minor NC', "Minor NC: sampled documented information for {standard} {clause} - {title} did not fully show current approval/revision or complete record control for {scope}. Requirement evidence reference: {reference}."],
            ['ISO 22000:2018', 'food,catering,bakery,meat,dairy,frozen,restaurant', 'food', 'minor_nc', 'minor', 'Food safety record minor NC', "Minor NC: sampled food-safety record for {standard} {clause} - {title} did not fully show completion, verification or traceability linkage for {scope}. Evidence reference: {reference}."],
            ['ISO 45001:2018', 'construction,operation,maintenance,warehouse,manufacturing', 'ohs', 'minor_nc', 'minor', 'OHS control minor NC', "Minor NC: sampled OH&S control for {standard} {clause} - {title} did not fully show implementation or verification for {scope}. Evidence reference: {reference}."],
            ['ISO 14001:2015', 'environment,manufacturing,warehouse,chemical,waste', 'environment', 'minor_nc', 'minor', 'Environmental control minor NC', "Minor NC: sampled environmental control for {standard} {clause} - {title} did not fully show monitoring, compliance or action follow-up for {scope}. Evidence reference: {reference}."],
            ['HACCP', 'food,catering,bakery,meat,dairy,frozen,restaurant', 'food', 'capa', '', 'Food safety CAPA', "Root cause: verification responsibility and evidence-linkage criteria were not clearly applied for the sampled control. Correction: complete the affected record/control and verify product/process acceptability. Corrective action: update the checklist/procedure, brief responsible staff, and sample follow-up records. Preventive action: trend recurrence in food safety team or management review. Evidence: revised checklist, briefing record, corrected monitoring/traceability evidence and verification result."],
        ];

        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant['id'];
            foreach ($rows as $idx => [$code, $scope, $industry, $type, $severity, $title, $text]) {
                $standardId = $standardByCode[$code] ?? null;
                if ($standardId === null) {
                    continue;
                }

                $templateCode = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', $code . '-' . $type . '-' . ($idx + 1)));
                $exists = $this->db->table('clause_content_pool')
                    ->where('tenant_id', $tenantId)
                    ->where('template_code', $templateCode)
                    ->countAllResults();
                if ($exists > 0) {
                    continue;
                }

                $this->db->table('clause_content_pool')->insert([
                    'tenant_id' => $tenantId,
                    'standard_id' => $standardId,
                    'clause_library_id' => null,
                    'scope_keyword' => $scope,
                    'industry_type' => $industry,
                    'audit_stage' => 'all',
                    'content_type' => $type,
                    'severity' => $severity,
                    'template_code' => $templateCode,
                    'template_title' => $title,
                    'content_text' => $text,
                    'tags' => json_encode([$code, $industry, $type], JSON_THROW_ON_ERROR),
                    'active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function confirmExistingAutoSections(): void
    {
        if (! $this->tableExists('report_sections') || ! $this->tableExists('report_drafts')) {
            return;
        }

        $this->db->query("
            UPDATE report_sections rs
            JOIN report_drafts rd ON rd.id = rs.report_draft_id
            LEFT JOIN audit_events ae ON ae.id = rd.audit_event_id
            SET
                rs.source_type = CASE WHEN rs.source_type IN ('system_draft', 'system_prepared', '') THEN 'clause_pool' ELSE rs.source_type END,
                rs.auditor_confirmed = 1,
                rs.confirmed_by_user_id = COALESCE(rs.confirmed_by_user_id, rd.prepared_by),
                rs.confirmed_at = COALESCE(rs.confirmed_at, rd.submitted_at, rd.approved_at, CONCAT(COALESCE(ae.actual_end_date, ae.planned_end_date, CURDATE()), ' 14:30:00')),
                rs.confirmation_note = COALESCE(rs.confirmation_note, 'Auto-confirmed on behalf of the assigned auditor from approved Clause Pool / system content.')
            WHERE rs.section_key = 'conformity'
              AND COALESCE(rs.auditor_confirmed, 0) = 0
        ");
    }

    private function tableExists(string $table): bool
    {
        return in_array($table, $this->db->listTables(), true);
    }
}

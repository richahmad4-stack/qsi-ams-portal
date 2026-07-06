
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `application_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) unsigned NOT NULL,
  `application_question_id` bigint(20) unsigned NOT NULL,
  `question_library_id` bigint(20) unsigned NOT NULL,
  `answer_text` longtext DEFAULT NULL,
  `answered_by` bigint(20) unsigned DEFAULT NULL,
  `answered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application_answers_question` (`application_id`,`application_question_id`),
  KEY `idx_application_answers_library` (`question_library_id`),
  KEY `fk_application_answers_question` (`application_question_id`),
  KEY `fk_application_answers_user` (`answered_by`),
  CONSTRAINT `fk_application_answers_application` FOREIGN KEY (`application_id`) REFERENCES `certification_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_answers_library` FOREIGN KEY (`question_library_id`) REFERENCES `question_library` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_answers_question` FOREIGN KEY (`application_question_id`) REFERENCES `application_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_answers_user` FOREIGN KEY (`answered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3823 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `application_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) unsigned NOT NULL,
  `application_question_id` bigint(20) unsigned DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(120) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_attachments_application` (`application_id`,`category`),
  KEY `fk_application_attachments_question` (`application_question_id`),
  KEY `fk_application_attachments_user` (`uploaded_by`),
  CONSTRAINT `fk_application_attachments_application` FOREIGN KEY (`application_id`) REFERENCES `certification_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_attachments_question` FOREIGN KEY (`application_question_id`) REFERENCES `application_questions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_application_attachments_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `application_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) unsigned NOT NULL,
  `question_library_id` bigint(20) unsigned NOT NULL,
  `question_key` varchar(120) NOT NULL,
  `question_text` varchar(500) NOT NULL,
  `question_type` varchar(40) NOT NULL,
  `section` varchar(120) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_rules`)),
  `help_text` varchar(500) DEFAULT NULL,
  `standard_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`standard_codes`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application_questions_key` (`application_id`,`question_key`),
  KEY `idx_application_questions_section` (`application_id`,`section`,`display_order`),
  KEY `fk_application_questions_library` (`question_library_id`),
  CONSTRAINT `fk_application_questions_application` FOREIGN KEY (`application_id`) REFERENCES `certification_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_questions_library` FOREIGN KEY (`question_library_id`) REFERENCES `question_library` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3913 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `application_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `questionnaire_response_id` bigint(20) unsigned DEFAULT NULL,
  `application_review_number` varchar(80) DEFAULT NULL,
  `certification_application_id` bigint(20) unsigned DEFAULT NULL,
  `document_number` varchar(40) NOT NULL DEFAULT 'F 28',
  `revision_number` varchar(20) NOT NULL DEFAULT '4',
  `issue_number` varchar(20) NOT NULL DEFAULT '2',
  `document_date` date NOT NULL DEFAULT '2025-02-01',
  `technical_manager_id` bigint(20) unsigned DEFAULT NULL,
  `quality_manager_id` bigint(20) unsigned DEFAULT NULL,
  `completeness_status` varchar(40) NOT NULL DEFAULT 'pending',
  `risk_rating` varchar(40) DEFAULT NULL,
  `recommendation` varchar(80) DEFAULT NULL,
  `md5_duration_days` decimal(6,2) DEFAULT NULL,
  `iso22003_duration_days` decimal(6,2) DEFAULT NULL,
  `integrated_reduction_percent` decimal(5,2) DEFAULT NULL,
  `stage1_days` decimal(6,2) DEFAULT NULL,
  `stage2_days` decimal(6,2) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `review_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`review_payload`)),
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `reviewed_at` datetime DEFAULT NULL,
  `technical_reviewer_name` varchar(180) DEFAULT NULL,
  `technical_review_date` date DEFAULT NULL,
  `quality_manager_status` varchar(60) DEFAULT NULL,
  `quality_manager_comments` text DEFAULT NULL,
  `quality_manager_name` varchar(180) DEFAULT NULL,
  `quality_manager_date` date DEFAULT NULL,
  `general_manager_status` varchar(60) DEFAULT NULL,
  `general_manager_comments` text DEFAULT NULL,
  `general_manager_name` varchar(180) DEFAULT NULL,
  `general_manager_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_reviews_client` (`client_id`,`status`),
  KEY `idx_application_reviews_questionnaire` (`questionnaire_response_id`),
  KEY `idx_application_reviews_tm` (`technical_manager_id`),
  KEY `idx_application_reviews_qm` (`quality_manager_id`),
  KEY `idx_application_reviews_cert_app` (`certification_application_id`),
  CONSTRAINT `fk_application_reviews_cert_app` FOREIGN KEY (`certification_application_id`) REFERENCES `certification_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_application_reviews_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_reviews_qm` FOREIGN KEY (`quality_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_application_reviews_questionnaire` FOREIGN KEY (`questionnaire_response_id`) REFERENCES `questionnaire_responses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_application_reviews_tm` FOREIGN KEY (`technical_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `application_selected_standards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_selected_standards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) unsigned NOT NULL,
  `standard_id` bigint(20) unsigned NOT NULL,
  `standard_code` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application_selected_standard` (`application_id`,`standard_id`),
  KEY `idx_application_selected_standard_code` (`standard_code`),
  KEY `fk_application_selected_standards_standard` (`standard_id`),
  CONSTRAINT `fk_application_selected_standards_application` FOREIGN KEY (`application_id`) REFERENCES `certification_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_selected_standards_standard` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_program_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `audit_number` varchar(80) NOT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `audit_window_start` date DEFAULT NULL,
  `audit_window_end` date DEFAULT NULL,
  `duration_days` decimal(6,2) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'planned',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audit_events_number` (`audit_number`),
  KEY `idx_audit_events_program` (`audit_program_id`,`event_type`),
  KEY `idx_audit_events_window` (`audit_window_start`,`audit_window_end`),
  CONSTRAINT `fk_audit_events_program` FOREIGN KEY (`audit_program_id`) REFERENCES `audit_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `module` varchar(80) NOT NULL,
  `entity_table` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_tenant_module` (`tenant_id`,`module`,`created_at`),
  KEY `idx_audit_logs_user` (`user_id`,`created_at`),
  KEY `idx_audit_logs_entity` (`entity_table`,`entity_id`),
  CONSTRAINT `fk_audit_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=398 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_plan_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_plan_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_plan_id` bigint(20) unsigned NOT NULL,
  `audit_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `activity_type` varchar(80) NOT NULL,
  `department` varchar(180) DEFAULT NULL,
  `process_name` varchar(180) DEFAULT NULL,
  `clauses` varchar(500) DEFAULT NULL,
  `auditor_personnel_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_audit_plan_items_plan` (`audit_plan_id`,`audit_date`,`sort_order`),
  KEY `idx_audit_plan_items_auditor` (`auditor_personnel_id`),
  CONSTRAINT `fk_audit_plan_items_auditor` FOREIGN KEY (`auditor_personnel_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_plan_items_plan` FOREIGN KEY (`audit_plan_id`) REFERENCES `audit_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=510 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_event_id` bigint(20) unsigned NOT NULL,
  `plan_number` varchar(80) NOT NULL,
  `version_number` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `prepared_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audit_plans_number` (`plan_number`),
  KEY `idx_audit_plans_event` (`audit_event_id`,`status`),
  KEY `idx_audit_plans_prepared_by` (`prepared_by`),
  KEY `idx_audit_plans_approved_by` (`approved_by`),
  CONSTRAINT `fk_audit_plans_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_plans_event` FOREIGN KEY (`audit_event_id`) REFERENCES `audit_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_plans_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `contract_id` bigint(20) unsigned DEFAULT NULL,
  `program_number` varchar(80) NOT NULL,
  `document_number` varchar(40) NOT NULL DEFAULT 'F 42',
  `revision_number` varchar(20) NOT NULL DEFAULT '2',
  `issue_number` varchar(20) NOT NULL DEFAULT '2',
  `document_date` date NOT NULL DEFAULT '2022-05-15',
  `cycle_type` varchar(40) NOT NULL DEFAULT 'initial',
  `certificate_issue_date` date DEFAULT NULL,
  `surveillance_2_due_date` date DEFAULT NULL,
  `surveillance_1_due_date` date DEFAULT NULL,
  `certificate_expiry_date` date DEFAULT NULL,
  `surveillance_2_status` varchar(40) DEFAULT 'locked',
  `surveillance_1_status` varchar(40) DEFAULT 'locked',
  `status` varchar(40) NOT NULL DEFAULT 'planned',
  `program_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`program_payload`)),
  `prepared_by_name` varchar(180) DEFAULT NULL,
  `prepared_date` date DEFAULT NULL,
  `approved_by_name` varchar(180) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audit_programs_tenant_number` (`tenant_id`,`program_number`),
  KEY `idx_audit_programs_client` (`client_id`,`status`),
  KEY `idx_audit_programs_contract` (`contract_id`),
  KEY `idx_audit_programs_created_by` (`created_by`),
  CONSTRAINT `fk_audit_programs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_audit_programs_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_programs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_programs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_event_id` bigint(20) unsigned NOT NULL,
  `reminder_type` varchar(80) NOT NULL,
  `due_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_reminders_due` (`due_date`,`status`),
  KEY `idx_audit_reminders_event` (`audit_event_id`),
  CONSTRAINT `fk_audit_reminders_event` FOREIGN KEY (`audit_event_id`) REFERENCES `audit_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auditor_appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditor_appointments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_event_id` bigint(20) unsigned NOT NULL,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `appointment_role` varchar(60) NOT NULL,
  `appointed_by` bigint(20) unsigned DEFAULT NULL,
  `appointed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(40) NOT NULL DEFAULT 'appointed',
  `conflict_check_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conflict_check_json`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auditor_appointments_role` (`audit_event_id`,`personnel_id`,`appointment_role`),
  KEY `idx_auditor_appointments_personnel` (`personnel_id`,`appointment_role`),
  KEY `idx_auditor_appointments_user` (`appointed_by`),
  CONSTRAINT `fk_auditor_appointments_event` FOREIGN KEY (`audit_event_id`) REFERENCES `audit_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_auditor_appointments_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`),
  CONSTRAINT `fk_auditor_appointments_user` FOREIGN KEY (`appointed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=206 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `capa_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `capa_evidence` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `capa_id` bigint(20) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_capa_evidence_capa` (`capa_id`),
  KEY `idx_capa_evidence_user` (`uploaded_by`),
  CONSTRAINT `fk_capa_evidence_capa` FOREIGN KEY (`capa_id`) REFERENCES `capas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_capa_evidence_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `capas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `capas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `ncr_id` bigint(20) unsigned DEFAULT NULL,
  `capa_number` varchar(80) NOT NULL,
  `source` varchar(80) NOT NULL,
  `issue` text NOT NULL,
  `immediate_correction` text DEFAULT NULL,
  `root_cause` text DEFAULT NULL,
  `five_why` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`five_why`)),
  `fishbone` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fishbone`)),
  `corrective_action` text DEFAULT NULL,
  `preventive_action` text DEFAULT NULL,
  `responsible_person` varchar(180) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `evidence_reference` text DEFAULT NULL,
  `verification` text DEFAULT NULL,
  `effectiveness` text DEFAULT NULL,
  `closure_notes` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_capas_tenant_number` (`tenant_id`,`capa_number`),
  KEY `idx_capas_source_status` (`source`,`status`),
  KEY `idx_capas_ncr` (`ncr_id`),
  KEY `idx_capas_due` (`target_date`,`status`),
  KEY `idx_capas_created_by` (`created_by`),
  CONSTRAINT `fk_capas_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_capas_ncr` FOREIGN KEY (`ncr_id`) REFERENCES `ncrs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_capas_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `certificate_public_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificate_public_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `certificate_id` bigint(20) unsigned NOT NULL,
  `search_term` varchar(190) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_certificate_public_events_certificate` (`certificate_id`,`created_at`),
  CONSTRAINT `fk_certificate_public_events_certificate` FOREIGN KEY (`certificate_id`) REFERENCES `certificates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `certification_decision_id` bigint(20) unsigned DEFAULT NULL,
  `certificate_number` varchar(80) NOT NULL,
  `standard_id` bigint(20) unsigned NOT NULL,
  `scope` text NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `initial_certification_date` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'active',
  `qr_payload` varchar(500) NOT NULL,
  `public_slug` varchar(120) NOT NULL,
  `suspended_at` datetime DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificates_tenant_number` (`tenant_id`,`certificate_number`),
  UNIQUE KEY `uq_certificates_public_slug` (`public_slug`),
  KEY `idx_certificates_client_status` (`client_id`,`status`),
  KEY `idx_certificates_standard` (`standard_id`),
  KEY `idx_certificates_expiry` (`tenant_id`,`expiry_date`,`status`),
  KEY `idx_certificates_decision` (`certification_decision_id`),
  CONSTRAINT `fk_certificates_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_certificates_decision` FOREIGN KEY (`certification_decision_id`) REFERENCES `certification_decisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certificates_standard` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`),
  CONSTRAINT `fk_certificates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `certification_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certification_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `application_number` varchar(80) NOT NULL,
  `document_number` varchar(40) NOT NULL DEFAULT 'F 25',
  `revision_number` varchar(20) NOT NULL DEFAULT '1',
  `issue_number` varchar(20) NOT NULL DEFAULT '2',
  `issue_date` date NOT NULL DEFAULT '2024-11-01',
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `declaration_name` varchar(180) DEFAULT NULL,
  `declaration_position` varchar(180) DEFAULT NULL,
  `declaration_date` date DEFAULT NULL,
  `cb_review_status` varchar(40) DEFAULT NULL,
  `cb_review_notes` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certification_applications_number` (`tenant_id`,`application_number`),
  KEY `idx_certification_applications_client` (`tenant_id`,`client_id`),
  KEY `fk_certification_applications_client` (`client_id`),
  KEY `fk_certification_applications_reviewed_by` (`reviewed_by`),
  KEY `fk_certification_applications_created_by` (`created_by`),
  CONSTRAINT `fk_certification_applications_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certification_applications_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certification_applications_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certification_applications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `certification_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certification_decisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `technical_review_id` bigint(20) unsigned NOT NULL,
  `decision_maker_personnel_id` bigint(20) unsigned NOT NULL,
  `decision` varchar(40) NOT NULL,
  `reason` text DEFAULT NULL,
  `electronic_signature` varchar(255) DEFAULT NULL,
  `decision_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`decision_payload`)),
  `decided_at` datetime DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `gm_approved_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `gm_approval_notes` text DEFAULT NULL,
  `gm_approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certification_decisions_review` (`technical_review_id`),
  KEY `idx_certification_decisions_tenant_status` (`tenant_id`,`status`),
  KEY `idx_certification_decisions_maker` (`decision_maker_personnel_id`),
  KEY `idx_certification_decisions_gm_user` (`gm_approved_by_user_id`),
  CONSTRAINT `fk_certification_decisions_gm_user` FOREIGN KEY (`gm_approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certification_decisions_maker` FOREIGN KEY (`decision_maker_personnel_id`) REFERENCES `personnel` (`id`),
  CONSTRAINT `fk_certification_decisions_review` FOREIGN KEY (`technical_review_id`) REFERENCES `technical_reviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certification_decisions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ci_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ci_sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `data` blob NOT NULL,
  PRIMARY KEY (`id`,`ip_address`),
  KEY `ci_sessions_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clause_library`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clause_library` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `standard_id` bigint(20) unsigned NOT NULL,
  `clause_number` varchar(60) NOT NULL,
  `clause_title` varchar(255) NOT NULL,
  `requirement` text NOT NULL,
  `predefined_conformity_note` text DEFAULT NULL,
  `positive_finding` text DEFAULT NULL,
  `opportunity_for_improvement` text DEFAULT NULL,
  `minor_nc` text DEFAULT NULL,
  `major_nc` text DEFAULT NULL,
  `evidence_examples` text DEFAULT NULL,
  `auditor_guidance` text DEFAULT NULL,
  `risk_rating` varchar(40) DEFAULT NULL,
  `stage_applicability` varchar(80) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clause_library` (`tenant_id`,`standard_id`,`clause_number`),
  KEY `idx_clause_library_standard` (`standard_id`,`active`),
  CONSTRAINT `fk_clause_library_standard` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`),
  CONSTRAINT `fk_clause_library_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=250 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(80) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `checksum_sha256` char(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_attachments_client` (`client_id`,`category`),
  KEY `idx_client_attachments_user` (`uploaded_by`),
  CONSTRAINT `fk_client_attachments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_attachments_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_feedback` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `audit_program_id` bigint(20) unsigned DEFAULT NULL,
  `certificate_id` bigint(20) unsigned DEFAULT NULL,
  `contact_name` varchar(180) DEFAULT NULL,
  `contact_email` varchar(190) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `overall_rating` tinyint(3) unsigned DEFAULT NULL,
  `communication_rating` tinyint(3) unsigned DEFAULT NULL,
  `auditor_rating` tinyint(3) unsigned DEFAULT NULL,
  `report_quality_rating` tinyint(3) unsigned DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `improvement_suggestion` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_feedback_client` (`tenant_id`,`client_id`),
  KEY `idx_client_feedback_program` (`audit_program_id`),
  KEY `idx_client_feedback_certificate` (`certificate_id`),
  KEY `idx_client_feedback_created_by` (`created_by`),
  KEY `fk_client_feedback_client` (`client_id`),
  CONSTRAINT `fk_client_feedback_certificate` FOREIGN KEY (`certificate_id`) REFERENCES `certificates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_feedback_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_feedback_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_feedback_program` FOREIGN KEY (`audit_program_id`) REFERENCES `audit_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_feedback_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_processes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `process_name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_processes` (`client_id`,`process_name`),
  CONSTRAINT `fk_client_processes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_sites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `site_name` varchar(180) NOT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `employee_count` int(10) unsigned DEFAULT NULL,
  `processes` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_sites_client` (`client_id`,`active`),
  CONSTRAINT `fk_client_sites_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_standards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_standards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `standard_id` bigint(20) unsigned NOT NULL,
  `iaf_code_id` bigint(20) unsigned DEFAULT NULL,
  `nace_code_id` bigint(20) unsigned DEFAULT NULL,
  `food_chain_category_id` bigint(20) unsigned DEFAULT NULL,
  `medical_device_category_id` bigint(20) unsigned DEFAULT NULL,
  `scope` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_standards` (`client_id`,`standard_id`),
  KEY `idx_client_standards_standard` (`standard_id`),
  KEY `idx_client_standards_iaf` (`iaf_code_id`),
  KEY `idx_client_standards_nace` (`nace_code_id`),
  KEY `idx_client_standards_food` (`food_chain_category_id`),
  KEY `idx_client_standards_medical` (`medical_device_category_id`),
  CONSTRAINT `fk_client_standards_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_standards_food` FOREIGN KEY (`food_chain_category_id`) REFERENCES `food_chain_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_standards_iaf` FOREIGN KEY (`iaf_code_id`) REFERENCES `iaf_codes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_standards_medical` FOREIGN KEY (`medical_device_category_id`) REFERENCES `medical_device_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_standards_nace` FOREIGN KEY (`nace_code_id`) REFERENCES `nace_codes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_client_standards_standard` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `legacy_import_batch_id` bigint(20) unsigned DEFAULT NULL,
  `company` varchar(220) NOT NULL,
  `legal_name` varchar(220) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `contact_person` varchar(180) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(220) DEFAULT NULL,
  `scope` text DEFAULT NULL,
  `employee_count` int(10) unsigned DEFAULT NULL,
  `permanent_employees` int(10) unsigned DEFAULT NULL,
  `temporary_employees` int(10) unsigned DEFAULT NULL,
  `shift_pattern` varchar(180) DEFAULT NULL,
  `seasonal_operations` varchar(180) DEFAULT NULL,
  `number_of_sites` int(10) unsigned NOT NULL DEFAULT 1,
  `certification_status` varchar(60) NOT NULL DEFAULT 'enquiry',
  `risk_category` varchar(60) DEFAULT NULL,
  `certificate_number` varchar(80) DEFAULT NULL,
  `initial_certification_date` date DEFAULT NULL,
  `certificate_issue_date` date DEFAULT NULL,
  `certificate_expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_legacy` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clients_tenant_certificate` (`tenant_id`,`certificate_number`),
  KEY `idx_clients_tenant_company` (`tenant_id`,`company`),
  KEY `idx_clients_status` (`tenant_id`,`certification_status`),
  KEY `idx_clients_expiry` (`tenant_id`,`certificate_expiry_date`),
  KEY `idx_clients_import_batch` (`legacy_import_batch_id`),
  KEY `idx_clients_created_by` (`created_by`),
  CONSTRAINT `fk_clients_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clients_import_batch` FOREIGN KEY (`legacy_import_batch_id`) REFERENCES `legacy_import_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clients_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot_json`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_versions` (`contract_id`,`version_number`),
  KEY `idx_contract_versions_user` (`created_by`),
  CONSTRAINT `fk_contract_versions_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contract_versions_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `proposal_id` bigint(20) unsigned NOT NULL,
  `contract_number` varchar(80) NOT NULL,
  `document_number` varchar(40) NOT NULL DEFAULT 'F 27',
  `revision_number` varchar(20) NOT NULL DEFAULT '2',
  `issue_number` varchar(20) NOT NULL DEFAULT '2',
  `document_date` date NOT NULL DEFAULT '2022-05-15',
  `version_number` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `signed_at` datetime DEFAULT NULL,
  `signed_by_name` varchar(180) DEFAULT NULL,
  `contract_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`contract_payload`)),
  `qsi_signatory_name` varchar(180) DEFAULT NULL,
  `qsi_signatory_date` date DEFAULT NULL,
  `client_signatory_name` varchar(180) DEFAULT NULL,
  `client_signatory_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contracts_tenant_number` (`tenant_id`,`contract_number`),
  KEY `idx_contracts_client` (`client_id`,`status`),
  KEY `idx_contracts_proposal` (`proposal_id`),
  KEY `idx_contracts_created_by` (`created_by`),
  CONSTRAINT `fk_contracts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_contracts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contracts_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`),
  CONSTRAINT `fk_contracts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_template_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_template_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `body_html` mediumtext NOT NULL,
  `header_html` mediumtext DEFAULT NULL,
  `footer_html` mediumtext DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_template_versions` (`document_template_id`,`version_number`),
  KEY `idx_document_template_versions_created_by` (`created_by`),
  KEY `idx_document_template_versions_approved_by` (`approved_by`),
  CONSTRAINT `fk_document_template_versions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_document_template_versions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_document_template_versions_template` FOREIGN KEY (`document_template_id`) REFERENCES `document_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `template_key` varchar(120) NOT NULL,
  `name` varchar(180) NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `active_version` int(10) unsigned DEFAULT NULL,
  `allowed_placeholders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`allowed_placeholders`)),
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_templates_key` (`tenant_id`,`template_key`),
  CONSTRAINT `fk_document_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `food_chain_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `food_chain_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_chain_categories_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `generated_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generated_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `document_key` varchar(120) NOT NULL,
  `document_title` varchar(255) NOT NULL,
  `related_table` varchar(80) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `storage_path` varchar(500) NOT NULL,
  `mime_type` varchar(120) NOT NULL DEFAULT 'application/pdf',
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_generated_documents_client` (`tenant_id`,`client_id`,`generated_at`),
  KEY `idx_generated_documents_related` (`related_table`,`related_id`),
  KEY `idx_generated_documents_user` (`generated_by`),
  KEY `fk_generated_documents_client` (`client_id`),
  CONSTRAINT `fk_generated_documents_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_generated_documents_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `fk_generated_documents_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `global_search_index`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `global_search_index` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `module` varchar(80) NOT NULL,
  `entity_table` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `status` varchar(40) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_global_search_entity` (`tenant_id`,`entity_table`,`entity_id`),
  KEY `idx_global_search_module` (`tenant_id`,`module`,`status`),
  FULLTEXT KEY `ft_global_search` (`title`,`summary`,`keywords`),
  CONSTRAINT `fk_global_search_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `iaf_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iaf_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `title` varchar(180) NOT NULL,
  `risk_level` varchar(40) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_iaf_codes_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internal_audit_findings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_audit_findings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internal_audit_id` bigint(20) unsigned NOT NULL,
  `capa_id` bigint(20) unsigned DEFAULT NULL,
  `finding_type` varchar(60) NOT NULL,
  `description` text NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_internal_audit_findings_audit` (`internal_audit_id`,`status`),
  KEY `idx_internal_audit_findings_capa` (`capa_id`),
  CONSTRAINT `fk_internal_audit_findings_audit` FOREIGN KEY (`internal_audit_id`) REFERENCES `internal_audits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_internal_audit_findings_capa` FOREIGN KEY (`capa_id`) REFERENCES `capas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internal_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `audit_number` varchar(80) NOT NULL,
  `scope` text NOT NULL,
  `planned_date` date NOT NULL,
  `completed_date` date DEFAULT NULL,
  `checklist_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist_json`)),
  `status` varchar(40) NOT NULL DEFAULT 'planned',
  `lead_auditor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_internal_audits_tenant_number` (`tenant_id`,`audit_number`),
  KEY `idx_internal_audits_date` (`tenant_id`,`planned_date`,`status`),
  KEY `idx_internal_audits_lead` (`lead_auditor_id`),
  CONSTRAINT `fk_internal_audits_lead` FOREIGN KEY (`lead_auditor_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_internal_audits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `invoice_number` varchar(80) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'SAR',
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_tenant_number` (`tenant_id`,`invoice_number`),
  KEY `idx_invoices_client` (`client_id`,`status`),
  KEY `idx_invoices_date` (`tenant_id`,`invoice_date`),
  CONSTRAINT `fk_invoices_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `legacy_import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `source_type` varchar(20) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `column_mapping` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`column_mapping`)),
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `valid_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `invalid_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `duplicate_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'preview',
  `imported_by` bigint(20) unsigned DEFAULT NULL,
  `imported_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_legacy_import_batches_tenant` (`tenant_id`,`status`),
  KEY `idx_legacy_import_batches_user` (`imported_by`),
  CONSTRAINT `fk_legacy_import_batches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `fk_legacy_import_batches_user` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_import_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `legacy_import_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint(20) unsigned NOT NULL,
  `row_number` int(10) unsigned NOT NULL,
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`raw_payload`)),
  `normalized_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`normalized_payload`)),
  `validation_errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_errors`)),
  `duplicate_key` varchar(190) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_legacy_import_rows_batch_status` (`batch_id`,`status`),
  KEY `idx_legacy_import_rows_client` (`client_id`),
  CONSTRAINT `fk_legacy_import_rows_batch` FOREIGN KEY (`batch_id`) REFERENCES `legacy_import_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(120) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_throttle` (`email`,`ip_address`,`attempted_at`),
  KEY `idx_login_attempts_tenant` (`tenant_id`,`attempted_at`),
  CONSTRAINT `fk_login_attempts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_review_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `management_review_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `management_review_id` bigint(20) unsigned NOT NULL,
  `capa_id` bigint(20) unsigned DEFAULT NULL,
  `action_text` text NOT NULL,
  `responsible_person` varchar(180) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_management_review_actions_review` (`management_review_id`,`status`),
  KEY `idx_management_review_actions_capa` (`capa_id`),
  KEY `idx_management_review_actions_due` (`target_date`,`status`),
  CONSTRAINT `fk_management_review_actions_capa` FOREIGN KEY (`capa_id`) REFERENCES `capas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_management_review_actions_review` FOREIGN KEY (`management_review_id`) REFERENCES `management_reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `management_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `review_number` varchar(80) NOT NULL,
  `meeting_date` date NOT NULL,
  `agenda` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`agenda`)),
  `inputs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`inputs`)),
  `outputs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`outputs`)),
  `minutes` mediumtext DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'planned',
  `chairperson_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_management_reviews_tenant_number` (`tenant_id`,`review_number`),
  KEY `idx_management_reviews_date` (`tenant_id`,`meeting_date`,`status`),
  KEY `idx_management_reviews_chair` (`chairperson_id`),
  CONSTRAINT `fk_management_reviews_chair` FOREIGN KEY (`chairperson_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_management_reviews_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_device_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_device_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medical_device_categories_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nace_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nace_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `title` varchar(220) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nace_codes_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ncr_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ncr_evidence` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ncr_id` bigint(20) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ncr_evidence_ncr` (`ncr_id`),
  KEY `idx_ncr_evidence_user` (`uploaded_by`),
  CONSTRAINT `fk_ncr_evidence_ncr` FOREIGN KEY (`ncr_id`) REFERENCES `ncrs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ncr_evidence_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ncrs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ncrs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `audit_event_id` bigint(20) unsigned NOT NULL,
  `clause_library_id` bigint(20) unsigned DEFAULT NULL,
  `ncr_number` varchar(80) NOT NULL,
  `requirement` text NOT NULL,
  `finding` text NOT NULL,
  `objective_evidence` text NOT NULL,
  `classification` varchar(40) NOT NULL,
  `correction` text DEFAULT NULL,
  `root_cause` text DEFAULT NULL,
  `corrective_action` text DEFAULT NULL,
  `responsible_person` varchar(180) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `verification` text DEFAULT NULL,
  `closure_notes` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ncrs_tenant_number` (`tenant_id`,`ncr_number`),
  KEY `idx_ncrs_event_status` (`audit_event_id`,`status`),
  KEY `idx_ncrs_clause` (`clause_library_id`),
  KEY `idx_ncrs_due` (`target_date`,`status`),
  KEY `idx_ncrs_created_by` (`created_by`),
  CONSTRAINT `fk_ncrs_clause` FOREIGN KEY (`clause_library_id`) REFERENCES `clause_library` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ncrs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ncrs_event` FOREIGN KEY (`audit_event_id`) REFERENCES `audit_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ncrs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `rule_key` varchar(120) NOT NULL,
  `channel` varchar(40) NOT NULL,
  `trigger_event` varchar(120) NOT NULL,
  `days_offset` int(11) NOT NULL DEFAULT 0,
  `recipient_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipient_roles`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_rules` (`tenant_id`,`rule_key`),
  CONSTRAINT `fk_notification_rules_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `notification_rule_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `body` text NOT NULL,
  `channel` varchar(40) NOT NULL DEFAULT 'dashboard',
  `related_module` varchar(80) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'unread',
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_status` (`user_id`,`status`,`created_at`),
  KEY `idx_notifications_tenant` (`tenant_id`,`created_at`),
  KEY `idx_notifications_rule` (`notification_rule_id`),
  CONSTRAINT `fk_notifications_rule` FOREIGN KEY (`notification_rule_id`) REFERENCES `notification_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `selector` varchar(32) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_selector` (`selector`),
  KEY `idx_password_reset_user` (`user_id`,`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(60) DEFAULT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_received_by` (`received_by`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(80) NOT NULL,
  `action` varchar(40) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_module_action` (`module`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=1588 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personnel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personnel` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `full_name` varchar(180) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `personnel_type` varchar(80) NOT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'pending',
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`languages`)),
  `countries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`countries`)),
  `experience_summary` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_personnel_tenant_type` (`tenant_id`,`personnel_type`,`approval_status`),
  KEY `idx_personnel_user` (`user_id`),
  KEY `idx_personnel_client` (`client_id`),
  CONSTRAINT `fk_personnel_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_personnel_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `fk_personnel_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personnel_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personnel_availability` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `unavailable_from` date NOT NULL,
  `unavailable_to` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_personnel_availability_personnel` (`personnel_id`,`unavailable_from`,`unavailable_to`),
  CONSTRAINT `fk_personnel_availability_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personnel_competencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personnel_competencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `standard_id` bigint(20) unsigned DEFAULT NULL,
  `iaf_code_id` bigint(20) unsigned DEFAULT NULL,
  `food_chain_category_id` bigint(20) unsigned DEFAULT NULL,
  `medical_device_category_id` bigint(20) unsigned DEFAULT NULL,
  `competency_type` varchar(80) NOT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'pending',
  `evidence_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_personnel_competencies_personnel` (`personnel_id`,`approval_status`),
  KEY `idx_personnel_competencies_standard` (`standard_id`),
  KEY `idx_personnel_competencies_expiry` (`valid_until`),
  KEY `idx_personnel_competencies_iaf` (`iaf_code_id`),
  KEY `idx_personnel_competencies_food` (`food_chain_category_id`),
  KEY `idx_personnel_competencies_medical` (`medical_device_category_id`),
  CONSTRAINT `fk_personnel_competencies_food` FOREIGN KEY (`food_chain_category_id`) REFERENCES `food_chain_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_personnel_competencies_iaf` FOREIGN KEY (`iaf_code_id`) REFERENCES `iaf_codes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_personnel_competencies_medical` FOREIGN KEY (`medical_device_category_id`) REFERENCES `medical_device_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_personnel_competencies_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_personnel_competencies_standard` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personnel_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personnel_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `valid_until` date DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_personnel_documents_personnel` (`personnel_id`,`document_type`),
  KEY `idx_personnel_documents_expiry` (`valid_until`),
  KEY `idx_personnel_documents_user` (`uploaded_by`),
  CONSTRAINT `fk_personnel_documents_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_personnel_documents_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personnel_witness_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personnel_witness_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `audit_event_id` bigint(20) unsigned DEFAULT NULL,
  `witness_date` date NOT NULL,
  `witness_by` bigint(20) unsigned DEFAULT NULL,
  `result` varchar(40) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_personnel_witness_personnel` (`personnel_id`,`witness_date`),
  KEY `idx_personnel_witness_event` (`audit_event_id`),
  KEY `idx_personnel_witness_by` (`witness_by`),
  CONSTRAINT `fk_personnel_witness_by` FOREIGN KEY (`witness_by`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_personnel_witness_event` FOREIGN KEY (`audit_event_id`) REFERENCES `audit_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_personnel_witness_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proposal_approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `proposal_id` bigint(20) unsigned NOT NULL,
  `approver_id` bigint(20) unsigned NOT NULL,
  `decision` varchar(30) NOT NULL,
  `comments` text DEFAULT NULL,
  `decided_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proposal_approvals_proposal` (`proposal_id`,`decision`),
  KEY `idx_proposal_approvals_approver` (`approver_id`),
  CONSTRAINT `fk_proposal_approvals_approver` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_proposal_approvals_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_line_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proposal_line_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `proposal_id` bigint(20) unsigned NOT NULL,
  `item_type` varchar(80) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_proposal_line_items_proposal` (`proposal_id`),
  CONSTRAINT `fk_proposal_line_items_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proposal_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `proposal_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot_json`)),
  `change_summary` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proposal_versions` (`proposal_id`,`version_number`),
  KEY `idx_proposal_versions_user` (`created_by`),
  CONSTRAINT `fk_proposal_versions_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_proposal_versions_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proposals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `application_review_id` bigint(20) unsigned DEFAULT NULL,
  `proposal_number` varchar(80) NOT NULL,
  `version_number` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `proposal_date` date DEFAULT NULL,
  `client_reference` varchar(120) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `certification_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `surveillance1_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `surveillance2_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `training_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `travel_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `accommodation_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_percent` decimal(5,2) NOT NULL DEFAULT 15.00,
  `vat_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'SAR',
  `proposal_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`proposal_payload`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proposals_tenant_number` (`tenant_id`,`proposal_number`),
  KEY `idx_proposals_client` (`client_id`,`status`),
  KEY `idx_proposals_review` (`application_review_id`),
  KEY `idx_proposals_created_by` (`created_by`),
  KEY `idx_proposals_approved_by` (`approved_by`),
  CONSTRAINT `fk_proposals_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_proposals_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_proposals_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_proposals_review` FOREIGN KEY (`application_review_id`) REFERENCES `application_reviews` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_proposals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_library`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_library` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_key` varchar(120) NOT NULL,
  `question_text` varchar(500) NOT NULL,
  `question_type` varchar(40) NOT NULL DEFAULT 'text',
  `applicable_standards` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`applicable_standards`)),
  `mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `section` varchar(120) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_rules`)),
  `help_text` varchar(500) DEFAULT NULL,
  `default_answer` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_question_library_key` (`question_key`),
  KEY `idx_question_library_section` (`section`,`display_order`),
  KEY `idx_question_library_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=1093 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questionnaire_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questionnaire_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `questionnaire_version_id` bigint(20) unsigned NOT NULL,
  `parent_question_id` bigint(20) unsigned DEFAULT NULL,
  `question_key` varchar(120) NOT NULL,
  `question_text` text NOT NULL,
  `answer_type` varchar(40) NOT NULL,
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_json`)),
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `conditional_logic` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conditional_logic`)),
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_questionnaire_questions_key` (`questionnaire_version_id`,`question_key`),
  KEY `idx_questionnaire_questions_parent` (`parent_question_id`),
  CONSTRAINT `fk_questionnaire_questions_parent` FOREIGN KEY (`parent_question_id`) REFERENCES `questionnaire_questions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_questionnaire_questions_version` FOREIGN KEY (`questionnaire_version_id`) REFERENCES `questionnaire_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questionnaire_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questionnaire_responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `questionnaire_version_id` bigint(20) unsigned NOT NULL,
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`response_payload`)),
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_questionnaire_responses_client` (`client_id`,`status`),
  KEY `idx_questionnaire_responses_version` (`questionnaire_version_id`),
  KEY `idx_questionnaire_responses_user` (`submitted_by`),
  CONSTRAINT `fk_questionnaire_responses_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_questionnaire_responses_user` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_questionnaire_responses_version` FOREIGN KEY (`questionnaire_version_id`) REFERENCES `questionnaire_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questionnaire_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questionnaire_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(180) NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `effective_from` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_questionnaire_versions` (`tenant_id`,`name`,`version_number`),
  KEY `idx_questionnaire_versions_created_by` (`created_by`),
  KEY `idx_questionnaire_versions_approved_by` (`approved_by`),
  CONSTRAINT `fk_questionnaire_versions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_questionnaire_versions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_questionnaire_versions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_drafts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `audit_event_id` bigint(20) unsigned NOT NULL,
  `report_type` varchar(80) NOT NULL,
  `version_number` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `generated_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`generated_payload`)),
  `editable_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`editable_payload`)),
  `prepared_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_drafts_version` (`audit_event_id`,`report_type`,`version_number`),
  KEY `idx_report_drafts_tenant_status` (`tenant_id`,`status`),
  KEY `idx_report_drafts_prepared_by` (`prepared_by`),
  KEY `idx_report_drafts_approved_by` (`approved_by`),
  KEY `idx_report_drafts_submitted_at` (`submitted_at`),
  CONSTRAINT `fk_report_drafts_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_drafts_event` FOREIGN KEY (`audit_event_id`) REFERENCES `audit_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_drafts_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_drafts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_draft_id` bigint(20) unsigned NOT NULL,
  `clause_library_id` bigint(20) unsigned DEFAULT NULL,
  `section_key` varchar(120) NOT NULL,
  `section_title` varchar(255) NOT NULL,
  `section_content` mediumtext NOT NULL,
  `source_type` varchar(40) NOT NULL DEFAULT 'system_draft',
  `auditor_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `confirmed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `confirmation_note` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_report_sections_report` (`report_draft_id`,`sort_order`),
  KEY `idx_report_sections_clause` (`clause_library_id`),
  KEY `idx_report_sections_confirmation` (`report_draft_id`,`section_key`,`auditor_confirmed`),
  KEY `idx_report_sections_confirmed_by` (`confirmed_by_user_id`),
  CONSTRAINT `fk_report_sections_clause` FOREIGN KEY (`clause_library_id`) REFERENCES `clause_library` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_sections_report` FOREIGN KEY (`report_draft_id`) REFERENCES `report_drafts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3536 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permissions` (`role_id`,`permission_id`),
  KEY `idx_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15368 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(80) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `system_role` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_tenant_code` (`tenant_id`,`code`),
  CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `standards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `standards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `name` varchar(180) NOT NULL,
  `version` varchar(80) DEFAULT NULL,
  `scheme_type` varchar(80) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_standards_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `technical_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `technical_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `audit_event_id` bigint(20) unsigned NOT NULL,
  `reviewer_personnel_id` bigint(20) unsigned NOT NULL,
  `checklist_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`checklist_payload`)),
  `competency_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `duration_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `application_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `reports_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `ncr_capa_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `scope_dates_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `impartiality_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `recommendation` varchar(80) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technical_reviews_event` (`audit_event_id`),
  KEY `idx_technical_reviews_tenant_status` (`tenant_id`,`status`),
  KEY `idx_technical_reviews_reviewer` (`reviewer_personnel_id`),
  CONSTRAINT `fk_technical_reviews_event` FOREIGN KEY (`audit_event_id`) REFERENCES `audit_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_technical_reviews_reviewer` FOREIGN KEY (`reviewer_personnel_id`) REFERENCES `personnel` (`id`),
  CONSTRAINT `fk_technical_reviews_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `legal_name` varchar(220) NOT NULL,
  `code` varchar(40) NOT NULL,
  `timezone` varchar(80) NOT NULL DEFAULT 'Asia/Riyadh',
  `currency` char(3) NOT NULL DEFAULT 'SAR',
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenants_code` (`code`),
  KEY `idx_tenants_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_role_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_role_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_role_assignments` (`user_id`,`role_id`),
  KEY `idx_user_role_assignments_role` (`role_id`),
  CONSTRAINT `fk_user_role_assignments_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_role_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `primary_role_id` bigint(20) unsigned DEFAULT NULL,
  `full_name` varchar(180) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_tenant_email` (`tenant_id`,`email`),
  KEY `idx_users_role` (`primary_role_id`),
  KEY `idx_users_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_users_primary_role` FOREIGN KEY (`primary_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


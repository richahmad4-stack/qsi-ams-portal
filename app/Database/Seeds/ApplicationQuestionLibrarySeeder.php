<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ApplicationQuestionLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $order = 10;
        foreach ($this->questions() as $question) {
            $question['display_order'] ??= $order;
            $question['mandatory'] ??= 0;
            $question['active'] = 1;
            $question['applicable_standards'] = json_encode($question['applicable_standards'], JSON_THROW_ON_ERROR);
            $question['validation_rules'] = isset($question['validation_rules'])
                ? json_encode($question['validation_rules'], JSON_THROW_ON_ERROR)
                : null;

            $this->db->query(
                'INSERT INTO question_library
                    (question_key, question_text, question_type, applicable_standards, mandatory, section, display_order, validation_rules, help_text, default_answer, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    question_text = VALUES(question_text),
                    question_type = VALUES(question_type),
                    applicable_standards = VALUES(applicable_standards),
                    mandatory = VALUES(mandatory),
                    section = VALUES(section),
                    display_order = VALUES(display_order),
                    validation_rules = VALUES(validation_rules),
                    help_text = VALUES(help_text),
                    default_answer = VALUES(default_answer),
                    active = 1',
                [
                    $question['question_key'],
                    $question['question_text'],
                    $question['question_type'],
                    $question['applicable_standards'],
                    $question['mandatory'],
                    $question['section'],
                    $question['display_order'],
                    $question['validation_rules'],
                    $question['help_text'] ?? null,
                    $question['default_answer'] ?? null,
                    $question['active'],
                ]
            );
            $order += 10;
        }
    }

    private function questions(): array
    {
        return array_merge(
            $this->commonQuestions(),
            $this->iso9001Questions(),
            $this->iso45001Questions(),
            $this->haccpQuestions()
        );
    }

    private function q(string $key, string $text, string $type, array $standards, string $section, int $mandatory = 0, ?array $rules = null, ?string $help = null): array
    {
        return [
            'question_key' => $key,
            'question_text' => $text,
            'question_type' => $type,
            'applicable_standards' => $standards,
            'mandatory' => $mandatory,
            'section' => $section,
            'validation_rules' => $rules,
            'help_text' => $help,
        ];
    }

    private function yesNoRules(): array
    {
        return ['options' => ['Yes', 'No', 'Partially', 'Not Applicable']];
    }

    private function commonQuestions(): array
    {
        $s = ['COMMON'];

        return [
            $this->q('company_name', 'Company Name', 'text', $s, 'Company / Organisation Details', 1),
            $this->q('legal_name', 'Legal Name', 'text', $s, 'Company / Organisation Details'),
            $this->q('commercial_registration_number', 'Commercial Registration Number', 'text', $s, 'Company / Organisation Details'),
            $this->q('vat_number', 'VAT Number', 'text', $s, 'Company / Organisation Details'),
            $this->q('license_number', 'License Number', 'text', $s, 'Company / Organisation Details'),
            $this->q('address', 'Address', 'textarea', $s, 'Company / Organisation Details', 1),
            $this->q('country', 'Country', 'text', $s, 'Company / Organisation Details', 1),
            $this->q('city', 'City', 'text', $s, 'Company / Organisation Details', 1),
            $this->q('website', 'Website', 'text', $s, 'Company / Organisation Details'),
            $this->q('contact_person', 'Contact Person', 'text', $s, 'Company / Organisation Details', 1),
            $this->q('designation', 'Designation', 'text', $s, 'Company / Organisation Details'),
            $this->q('email', 'Email', 'email', $s, 'Company / Organisation Details', 1),
            $this->q('phone', 'Phone', 'text', $s, 'Company / Organisation Details'),
            $this->q('mobile', 'Mobile', 'text', $s, 'Company / Organisation Details'),
            $this->q('previous_qsi_contact', 'Has previous contact been made with QSI personnel?', 'select', $s, 'Background Information', 0, $this->yesNoRules()),
            $this->q('qsi_contact_details', 'If yes, state the person name and meeting/visit details', 'textarea', $s, 'Background Information'),
            $this->q('heard_about_qsi', 'Where did you hear about QSI?', 'text', $s, 'Background Information'),
            $this->q('other_qsi_services', 'Do you currently use any other QSI services?', 'select', $s, 'Background Information', 0, $this->yesNoRules()),
            $this->q('scope_of_certification', 'Scope of Certification', 'textarea', $s, 'Scope / Processes', 1),
            $this->q('products', 'Products', 'textarea', $s, 'Scope / Processes'),
            $this->q('services', 'Services', 'textarea', $s, 'Scope / Processes'),
            $this->q('processes', 'Processes', 'textarea', $s, 'Scope / Processes', 1),
            $this->q('employee_count', 'Number of Employees', 'number', $s, 'Employees and Working Patterns', 1),
            $this->q('certification_employee_count', 'Number of employees in the activities to be certified', 'number', $s, 'Employees and Working Patterns'),
            $this->q('permanent_employees', 'Permanent Employees', 'number', $s, 'Employees and Working Patterns'),
            $this->q('temporary_employees', 'Temporary Employees', 'number', $s, 'Employees and Working Patterns'),
            $this->q('contract_workers', 'Contract Workers', 'number', $s, 'Employees and Working Patterns'),
            $this->q('number_of_shifts', 'Number of Shifts', 'text', $s, 'Employees and Working Patterns'),
            $this->q('working_hours', 'Working Hours', 'text', $s, 'Employees and Working Patterns'),
            $this->q('seasonal_operations', 'Seasonal Operations', 'select', $s, 'Employees and Working Patterns', 0, $this->yesNoRules()),
            $this->q('number_of_sites', 'Number of Sites', 'number', $s, 'Locations', 1),
            $this->q('head_office', 'Head Office', 'textarea', $s, 'Locations'),
            $this->q('branches', 'Branches', 'textarea', $s, 'Locations'),
            $this->q('remote_locations', 'Remote Locations', 'textarea', $s, 'Locations'),
            $this->q('outsourced_processes', 'Outsourced Processes', 'textarea', $s, 'Scope / Processes'),
            $this->q('previous_certification', 'Previous Certification', 'select', $s, 'Existing Registrations / Transfer', 0, $this->yesNoRules()),
            $this->q('certification_body', 'Certification Body', 'text', $s, 'Existing Registrations / Transfer'),
            $this->q('certificate_number', 'Certificate Number', 'text', $s, 'Existing Registrations / Transfer'),
            $this->q('transfer_certification', 'Transfer Certification', 'select', $s, 'Existing Registrations / Transfer', 0, $this->yesNoRules()),
            $this->q('certification_status', 'Certification Status', 'text', $s, 'Existing Registrations / Transfer'),
            $this->q('expiry_date', 'Expiry Date', 'date', $s, 'Existing Registrations / Transfer'),
            $this->q('audit_reports_available', 'Audit Reports Available', 'select', $s, 'Existing Registrations / Transfer', 0, $this->yesNoRules()),
            $this->q('nc_status', 'NC Status', 'textarea', $s, 'Existing Registrations / Transfer'),
            $this->q('customer_complaints', 'Customer Complaints', 'textarea', $s, 'Existing Registrations / Transfer'),
            $this->q('consultant_used', 'Consultant Used', 'textarea', $s, 'Consultant Information'),
            $this->q('language_of_audit', 'Language of Audit', 'text', $s, 'Audit Preferences'),
            $this->q('preferred_audit_dates', 'Preferred Audit Dates', 'textarea', $s, 'Audit Preferences'),
            $this->q('preferred_auditor', 'Preferred Auditor', 'text', $s, 'Audit Preferences'),
            $this->q('integrated_management_system', 'Integrated Management System', 'select', $s, 'Certification Required', 0, ['options' => ['Fully integrated', 'Partially integrated', 'Not integrated', 'Not Applicable']]),
            $this->q('legal_statutory_requirements', 'Legal and Statutory Requirements', 'textarea', $s, 'Certification Required'),
            $this->q('incident_accident_history', 'Any incident / accident in the past?', 'textarea', $s, 'Certification Required'),
            $this->q('management_system_status', 'Management System Status', 'select', $s, 'Management System Readiness', 0, ['options' => ['Paper', 'Electronic', 'Mixed', 'Not Implemented']]),
            $this->q('implementation_status', 'Implementation of the system completed?', 'select', $s, 'Management System Readiness', 0, $this->yesNoRules()),
            $this->q('preassessment_required', 'Certification pre-assessment required?', 'select', $s, 'Management System Readiness', 0, $this->yesNoRules()),
            $this->q('management_review_conducted', 'Management Review conducted?', 'select', $s, 'Management System Readiness', 0, $this->yesNoRules()),
            $this->q('internal_audit_conducted', 'Internal Audit conducted?', 'select', $s, 'Management System Readiness', 0, $this->yesNoRules()),
            $this->q('supporting_documents_upload', 'Supporting Documents Upload', 'file', $s, 'Supporting Documents'),
            $this->q('organization_chart', 'Organization Chart', 'file', $s, 'Supporting Documents'),
            $this->q('process_map', 'Process Map', 'file', $s, 'Supporting Documents'),
            $this->q('site_map', 'Site Map', 'file', $s, 'Supporting Documents'),
            $this->q('legal_permits', 'Legal Permits', 'file', $s, 'Supporting Documents'),
            $this->q('management_manual', 'Management Manual', 'file', $s, 'Supporting Documents'),
            $this->q('applicant_declaration', 'Declaration / submitted by', 'text', $s, 'Declaration', 1),
            $this->q('applicant_signature', 'Applicant Signature', 'text', $s, 'Declaration'),
        ];
    }

    private function iso9001Questions(): array
    {
        $s = ['ISO 9001', 'ISO 9001:2015'];
        $items = [
            ['iso9001_products_manufactured', 'Products manufactured'],
            ['iso9001_services_provided', 'Services provided'],
            ['iso9001_design_development', 'Design and development applicable?', 'select'],
            ['iso9001_customer_property', 'Customer property handled?', 'select'],
            ['iso9001_calibration_required', 'Calibration required?', 'select'],
            ['iso9001_measuring_equipment', 'Measuring equipment used?', 'select'],
            ['iso9001_outsourced_processes', 'Outsourced processes?'],
            ['iso9001_production_locations', 'Production locations'],
            ['iso9001_warehouses', 'Warehouses'],
            ['iso9001_sales_offices', 'Sales offices'],
            ['iso9001_project_sites', 'Project sites'],
            ['iso9001_complaints_process', 'Customer complaints process'],
            ['iso9001_risk_assessment', 'Risk assessment implemented?', 'select'],
            ['iso9001_interested_parties', 'Interested parties identified?', 'select'],
            ['iso9001_legal_requirements', 'Legal requirements maintained?', 'select'],
            ['iso9001_quality_objectives', 'Quality objectives available?', 'select'],
            ['iso9001_excluded_clauses', 'Excluded clauses'],
            ['iso9001_special_processes', 'Special processes'],
            ['iso9001_regulated_products', 'Regulated products'],
            ['iso9001_software_development', 'Software development'],
            ['iso9001_field_services', 'Field services'],
            ['iso9001_after_sales', 'After sales services'],
            ['iso9001_production_lines', 'Number of production lines', 'number'],
            ['iso9001_shift_details', 'Shift details'],
            ['iso9001_multisite', 'Multi-site activities'],
            ['iso9001_complex_processes', 'Complex processes'],
        ];

        return array_map(fn ($item) => $this->q($item[0], $item[1], $item[2] ?? 'textarea', $s, 'ISO 9001 Specific Questions', 0, ($item[2] ?? '') === 'select' ? $this->yesNoRules() : null), $items);
    }

    private function iso45001Questions(): array
    {
        $s = ['ISO 45001', 'ISO 45001:2018'];
        $names = [
            'high_risk_activities' => 'High risk activities',
            'construction' => 'Construction',
            'working_at_height' => 'Working at height',
            'confined_space' => 'Confined space',
            'hot_work' => 'Hot work',
            'excavation' => 'Excavation',
            'electrical_work' => 'Electrical work',
            'chemical_handling' => 'Chemical handling',
            'forklift_operations' => 'Forklift operations',
            'crane_operations' => 'Crane operations',
            'lifting_equipment' => 'Lifting equipment',
            'contractors' => 'Contractors',
            'subcontractors' => 'Subcontractors',
            'hazardous_chemicals' => 'Hazardous chemicals',
            'msds_available' => 'MSDS available',
            'emergency_response_team' => 'Emergency response team',
            'first_aid_facilities' => 'First aid facilities',
            'medical_surveillance' => 'Medical surveillance',
            'accident_statistics' => 'Accident statistics',
            'near_miss_records' => 'Near miss records',
            'lost_time_injuries' => 'Lost time injuries',
            'legal_compliance_register' => 'Legal compliance register',
            'ppe_used' => 'PPE used',
            'fire_protection' => 'Fire protection',
            'risk_assessments' => 'Risk assessments',
            'method_statements' => 'Method statements',
            'safety_committee' => 'Safety committee',
            'worker_consultation' => 'Worker consultation',
            'occupational_health_monitoring' => 'Occupational health monitoring',
            'dangerous_machines' => 'Number of dangerous machines',
            'high_voltage' => 'High voltage',
            'pressure_vessels' => 'Pressure vessels',
            'boilers' => 'Boilers',
            'compressed_gas' => 'Compressed gas',
            'radiation' => 'Radiation',
            'noise_exposure' => 'Noise exposure',
            'dust_exposure' => 'Dust exposure',
            'heat_stress' => 'Heat stress',
            'working_alone' => 'Working alone',
            'transportation_risks' => 'Transportation risks',
        ];

        $questions = [];
        foreach ($names as $key => $text) {
            $type = str_starts_with($key, 'number') || $key === 'dangerous_machines' ? 'number' : 'textarea';
            $questions[] = $this->q('iso45001_' . $key, $text, $type, $s, 'ISO 45001 Specific Questions');
        }

        return $questions;
    }

    private function haccpQuestions(): array
    {
        $s = ['HACCP'];
        $names = [
            'food_category' => 'Food Category',
            'raw_materials' => 'Raw Materials',
            'finished_products' => 'Finished Products',
            'processing_activities' => 'Processing Activities',
            'storage' => 'Storage',
            'distribution' => 'Distribution',
            'cold_storage' => 'Cold Storage',
            'frozen_storage' => 'Frozen Storage',
            'ambient_storage' => 'Ambient Storage',
            'shelf_life' => 'Shelf Life',
            'packaging_materials' => 'Packaging Materials',
            'food_contact_materials' => 'Food Contact Materials',
            'prps_implemented' => 'PRPs Implemented',
            'haccp_team_established' => 'HACCP Team Established',
            'haccp_studies' => 'Number of HACCP Studies',
            'flow_diagrams' => 'Flow Diagrams',
            'hazard_analysis' => 'Hazard Analysis',
            'biological_hazards' => 'Biological Hazards',
            'chemical_hazards' => 'Chemical Hazards',
            'physical_hazards' => 'Physical Hazards',
            'allergen_hazards' => 'Allergen Hazards',
            'ccps' => 'CCPs',
            'oprps' => 'OPRPs',
            'validation_conducted' => 'Validation Conducted',
            'verification_conducted' => 'Verification Conducted',
            'traceability_system' => 'Traceability System',
            'recall_procedure' => 'Recall Procedure',
            'food_defense' => 'Food Defense',
            'food_fraud' => 'Food Fraud',
            'allergen_management' => 'Allergen Management',
            'cleaning_programme' => 'Cleaning Programme',
            'pest_control' => 'Pest Control',
            'supplier_approval' => 'Supplier Approval',
            'water_source' => 'Water Source',
            'ice_source' => 'Ice Source',
            'steam_contact' => 'Steam Contact',
            'air_quality' => 'Air Quality',
            'metal_detection' => 'Metal Detection',
            'x_ray' => 'X-Ray',
            'foreign_body_control' => 'Foreign Body Control',
            'temperature_monitoring' => 'Temperature Monitoring',
            'transportation_control' => 'Transportation Control',
            'laboratory_testing' => 'Laboratory Testing',
            'environmental_monitoring' => 'Environmental Monitoring',
            'regulatory_authority' => 'Regulatory Authority',
            'export_countries' => 'Export Countries',
            'import_activities' => 'Import Activities',
            'customer_categories' => 'Customer Categories',
            'food_safety_incidents' => 'Food Safety Incidents',
            'previous_product_recall' => 'Previous Product Recall',
            'high_risk_products' => 'High Risk Products',
            'sensitive_consumers' => 'Sensitive Consumers',
        ];

        $questions = [];
        foreach ($names as $key => $text) {
            $type = in_array($key, ['prps_implemented', 'haccp_team_established', 'validation_conducted', 'verification_conducted', 'traceability_system', 'recall_procedure', 'food_defense', 'food_fraud', 'metal_detection', 'x_ray', 'previous_product_recall'], true) ? 'select' : 'textarea';
            $questions[] = $this->q('haccp_' . $key, $text, $type, $s, 'HACCP Specific Questions', 0, $type === 'select' ? $this->yesNoRules() : null);
        }

        return $questions;
    }
}

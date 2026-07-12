<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use DateInterval;
use DateTimeImmutable;

class DemoWorkflowSeeder extends Seeder
{
    private int $tenantId = 1;
    private array $users = [];
    private array $personnel = [];
    private array $standards = [];
    private array $iaf = [];
    private array $nace = [];
    private array $food = [];
    private array $medical = [];

    public function run(): void
    {
        $this->call(InitialAmsSeeder::class);

        $this->standards = $this->lookup('standards', 'code');
        $this->iaf = $this->lookup('iaf_codes', 'code');
        $this->nace = $this->lookup('nace_codes', 'code');
        $this->food = $this->lookup('food_chain_categories', 'code');
        $this->medical = $this->lookup('medical_device_categories', 'code');

        $scenarios = $this->scenarios();

        $this->db->transStart();
        $this->mapOriginalUsersAndPersonnel();
        $this->resetDemoClients();

        foreach ($scenarios as $index => $scenario) {
            $this->seedClientLifecycle($scenario, $index + 1);
        }

        $this->seedManagementSystemRecords();
        $this->db->transComplete();
    }

    private function scenarios(): array
    {
        return [
            [
                'company' => 'QSI Demo HACCP Foods',
                'contact' => 'Mariam Al Harbi',
                'email' => 'demo.haccp.client@qsi.test',
                'phone' => '+966 11 502 9101',
                'city' => 'Riyadh',
                'address' => 'Demo Food Storage Zone, Riyadh',
                'scope' => 'Storage and distribution of food products including receiving, temperature-controlled storage, order picking and dispatch.',
                'employees' => 42,
                'sites' => 1,
                'risk' => 'medium',
                'standards' => ['HACCP'],
                'food' => 'G',
                'medical' => null,
                'iaf' => '03',
                'nace' => '52',
                'processes' => ['Receiving inspection', 'Ambient storage', 'Cold storage', 'Order picking', 'Dispatch control'],
                'base' => '2024-10-04',
                'fees' => [8200, 4100, 4100],
                'ncrs' => ['initial_stage1' => 1, 'initial_stage2' => 3, 'surveillance1' => 0, 'surveillance2' => 2],
            ],
            [
                'company' => 'QSI Demo ISO 9001 Manufacturing',
                'contact' => 'Saad Al Jaber',
                'email' => 'demo.iso9001.client@qsi.test',
                'phone' => '+966 11 410 1181',
                'city' => 'Riyadh',
                'address' => 'Second Industrial City, Riyadh',
                'scope' => 'Manufacture and quality inspection of machined metal components for industrial customers.',
                'employees' => 58,
                'sites' => 1,
                'risk' => 'medium',
                'standards' => ['ISO 9001:2015'],
                'food' => null,
                'medical' => null,
                'iaf' => '17',
                'nace' => '25',
                'processes' => ['Contract review', 'Production planning', 'Machining', 'Final inspection', 'Calibration control'],
                'base' => '2024-07-05',
                'fees' => [7800, 3900, 3900],
                'ncrs' => ['initial_stage1' => 0, 'initial_stage2' => 3, 'surveillance1' => 1, 'surveillance2' => 1],
            ],
            [
                'company' => 'QSI Demo ISO 22000 Dairy',
                'contact' => 'Khalid Mansour',
                'email' => 'demo.iso22000.client@qsi.test',
                'phone' => '+966 16 320 2189',
                'city' => 'Qassim',
                'address' => 'Food Industrial City, Qassim',
                'scope' => 'Receiving, pasteurization, filling, cold storage and dispatch of dairy products.',
                'employees' => 118,
                'sites' => 2,
                'risk' => 'high',
                'standards' => ['ISO 22000:2018'],
                'food' => 'CI',
                'medical' => null,
                'iaf' => '03',
                'nace' => '10',
                'processes' => ['Milk receiving', 'Pasteurization', 'Filling and packing', 'Cold storage', 'Traceability and recall'],
                'base' => '2024-11-03',
                'fees' => [12400, 6200, 6200],
                'ncrs' => ['initial_stage1' => 1, 'initial_stage2' => 4, 'surveillance1' => 2, 'surveillance2' => 1],
            ],
            [
                'company' => 'QSI Demo ISO 14001 Environmental Services',
                'contact' => 'Noura Al Mutairi',
                'email' => 'demo.iso14001.client@qsi.test',
                'phone' => '+966 13 620 4461',
                'city' => 'Dammam',
                'address' => 'Environmental Services Park, Dammam',
                'scope' => 'Waste collection, transfer coordination, environmental monitoring and compliance support services.',
                'employees' => 76,
                'sites' => 2,
                'risk' => 'medium',
                'standards' => ['ISO 14001:2015'],
                'food' => null,
                'medical' => null,
                'iaf' => '24',
                'nace' => '38',
                'processes' => ['Waste collection planning', 'Transfer station control', 'Environmental monitoring', 'Emergency preparedness', 'Compliance evaluation'],
                'base' => '2024-08-08',
                'fees' => [8800, 4400, 4400],
                'ncrs' => ['initial_stage1' => 1, 'initial_stage2' => 3, 'surveillance1' => 1, 'surveillance2' => 1],
            ],
            [
                'company' => 'QSI Demo ISO 45001 Contracting',
                'contact' => 'Fahad Al Otaibi',
                'email' => 'demo.iso45001.client@qsi.test',
                'phone' => '+966 12 733 8811',
                'city' => 'Jeddah',
                'address' => 'Construction Support Zone, Jeddah',
                'scope' => 'Civil maintenance, scaffolding, access works and site support contracting.',
                'employees' => 148,
                'sites' => 3,
                'risk' => 'high',
                'standards' => ['ISO 45001:2018'],
                'food' => null,
                'medical' => null,
                'iaf' => '28',
                'nace' => '43',
                'processes' => ['Project mobilization', 'Hazard identification', 'Permit control', 'Incident reporting', 'Worker consultation'],
                'base' => '2024-09-10',
                'fees' => [11600, 5800, 5800],
                'ncrs' => ['initial_stage1' => 1, 'initial_stage2' => 4, 'surveillance1' => 2, 'surveillance2' => 1],
            ],
        ];
    }

    private function mapOriginalUsersAndPersonnel(): void
    {
        $assignments = [
            'super_admin' => 'admin@qsi.local',
            'general_manager' => 'rana.amjad.hanif@qsi.local',
            'coo' => 'mohammad.ahmad@qsi.local',
            'quality_manager' => 'rimsha.mahmoud@qsi.local',
            'certification_manager' => 'rana.arslan.khan@qsi.local',
            'technical_manager' => 'rimsha.mahmoud@qsi.local',
            'technical_reviewer' => 'rimsha.mahmoud@qsi.local',
            'decision_maker' => 'rana.amjad.hanif@qsi.local',
            'lead_auditor' => 'rifki.el.sherbeny@qsi.local',
            'auditor' => 'mohammad.arshad.ali@qsi.local',
            'trainer' => 'mohammad.raheel@qsi.local',
            'finance' => 'mohammad.ahmad@qsi.local',
            'sales' => 'rana.arslan.khan@qsi.local',
            'document_controller' => 'rana.arslan.khan@qsi.local',
            'client_rep' => 'admin@qsi.local',
            'administrator' => 'rana.arslan.khan@qsi.local',
        ];

        foreach ($assignments as $key => $email) {
            $user = $this->db->table('users')
                ->select('id')
                ->where('tenant_id', $this->tenantId)
                ->where('email', $email)
                ->get(1)
                ->getRowArray();

            if ($user === null) {
                throw new \RuntimeException('Original AMS user not found for demo assignment: ' . $email);
            }

            $this->users[$key] = (int) $user['id'];

            $personnel = $this->db->table('personnel')
                ->select('id')
                ->where('tenant_id', $this->tenantId)
                ->where('email', $email)
                ->get(1)
                ->getRowArray();

            if ($personnel !== null) {
                $this->personnel[$key] = (int) $personnel['id'];
            }
        }

        foreach (['lead_auditor', 'auditor', 'trainer', 'technical_reviewer', 'decision_maker'] as $key) {
            if (! isset($this->personnel[$key])) {
                throw new \RuntimeException('Original AMS personnel record not found for demo assignment: ' . $key);
            }
        }

        $this->seedPersonnelCompetencies();
    }

    private function seedPersonnelCompetencies(): void
    {
        foreach (['lead_auditor', 'auditor', 'technical_reviewer', 'decision_maker'] as $key) {
            foreach ($this->standards as $standard) {
                $exists = $this->db->table('personnel_competencies')
                    ->where('personnel_id', $this->personnel[$key])
                    ->where('standard_id', (int) $standard['id'])
                    ->where('competency_type', $key === 'decision_maker' ? 'decision' : 'audit')
                    ->get(1)
                    ->getRowArray();

                if ($exists !== null) {
                    continue;
                }

                $this->db->table('personnel_competencies')->insert([
                    'personnel_id' => $this->personnel[$key],
                    'standard_id' => (int) $standard['id'],
                    'competency_type' => $key === 'decision_maker' ? 'decision' : 'audit',
                    'valid_from' => '2024-01-01',
                    'valid_until' => '2027-12-31',
                    'approval_status' => 'approved',
                    'evidence_notes' => 'Demo competence approved based on qualification, witnessed audit and experience review.',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function resetDemoClients(): void
    {
        $clients = $this->db->table('clients')
            ->select('id')
            ->where('tenant_id', $this->tenantId)
            ->groupStart()
                ->like('company', 'Demo ', 'after')
                ->orLike('company', 'QSI Demo ', 'after')
                ->orLike('email', 'demo.', 'after')
                ->orLike('email', '@demo-qsi.test', 'both')
            ->groupEnd()
            ->get()
            ->getResultArray();
        $clientIds = array_map(static fn (array $row): int => (int) $row['id'], $clients);

        $this->db->table('notifications')->where('tenant_id', $this->tenantId)->like('title', 'Demo:', 'after')->delete();
        $this->db->table('audit_logs')->where('tenant_id', $this->tenantId)->where('module', 'demo_data')->delete();
        $this->db->table('global_search_index')->where('tenant_id', $this->tenantId)->where('module', 'demo')->delete();

        if ($clientIds === []) {
            return;
        }

        $programIds = $this->ids('audit_programs', 'client_id', $clientIds);
        $eventIds = $programIds === [] ? [] : $this->ids('audit_events', 'audit_program_id', $programIds);
        $planIds = $eventIds === [] ? [] : $this->ids('audit_plans', 'audit_event_id', $eventIds);
        $reportIds = $eventIds === [] ? [] : $this->ids('report_drafts', 'audit_event_id', $eventIds);
        $ncrIds = $eventIds === [] ? [] : $this->ids('ncrs', 'audit_event_id', $eventIds);
        $capaIds = $ncrIds === [] ? [] : $this->ids('capas', 'ncr_id', $ncrIds);
        $technicalReviewIds = $eventIds === [] ? [] : $this->ids('technical_reviews', 'audit_event_id', $eventIds);
        $certificateIds = $this->ids('certificates', 'client_id', $clientIds);
        $proposalIds = $this->ids('proposals', 'client_id', $clientIds);
        $contractIds = $this->ids('contracts', 'client_id', $clientIds);
        $invoiceIds = $this->ids('invoices', 'client_id', $clientIds);
        $applicationIds = $this->ids('certification_applications', 'client_id', $clientIds);
        $applicationQuestionIds = $applicationIds === [] ? [] : $this->ids('application_questions', 'application_id', $applicationIds);

        $this->deleteWhereIn('automation_runs', 'client_id', $clientIds);
        $this->deleteWhereIn('questionnaire_responses', 'client_id', $clientIds);
        $this->deleteWhereIn('certificate_public_events', 'certificate_id', $certificateIds);
        $this->deleteWhereIn('client_feedback', 'client_id', $clientIds);
        $this->deleteWhereIn('certificates', 'client_id', $clientIds);
        $this->deleteWhereIn('certification_decisions', 'technical_review_id', $technicalReviewIds);
        $this->deleteWhereIn('technical_reviews', 'audit_event_id', $eventIds);
        $this->deleteWhereIn('capa_evidence', 'capa_id', $capaIds);
        $this->deleteWhereIn('capas', 'ncr_id', $ncrIds);
        $this->deleteWhereIn('ncr_evidence', 'ncr_id', $ncrIds);
        $this->deleteWhereIn('ncrs', 'audit_event_id', $eventIds);
        $this->deleteWhereIn('report_sections', 'report_draft_id', $reportIds);
        $this->deleteWhereIn('report_drafts', 'audit_event_id', $eventIds);
        $this->deleteWhereIn('audit_plan_items', 'audit_plan_id', $planIds);
        $this->deleteWhereIn('audit_plans', 'audit_event_id', $eventIds);
        $this->deleteWhereIn('auditor_appointments', 'audit_event_id', $eventIds);
        $this->deleteWhereIn('audit_reminders', 'audit_event_id', $eventIds);
        $this->deleteWhereIn('audit_events', 'audit_program_id', $programIds);
        $this->deleteWhereIn('audit_programs', 'client_id', $clientIds);
        $this->deleteWhereIn('contract_versions', 'contract_id', $contractIds);
        $this->deleteWhereIn('contracts', 'client_id', $clientIds);
        $this->deleteWhereIn('proposal_approvals', 'proposal_id', $proposalIds);
        $this->deleteWhereIn('proposal_versions', 'proposal_id', $proposalIds);
        $this->deleteWhereIn('proposal_line_items', 'proposal_id', $proposalIds);
        $this->deleteWhereIn('proposals', 'client_id', $clientIds);
        $this->deleteWhereIn('payments', 'invoice_id', $invoiceIds);
        $this->deleteWhereIn('invoices', 'client_id', $clientIds);
        $this->deleteWhereIn('application_attachments', 'application_id', $applicationIds);
        $this->deleteWhereIn('application_answers', 'application_question_id', $applicationQuestionIds);
        $this->deleteWhereIn('application_selected_standards', 'application_id', $applicationIds);
        $this->deleteWhereIn('application_questions', 'application_id', $applicationIds);
        $this->deleteWhereIn('certification_applications', 'client_id', $clientIds);
        $this->deleteWhereIn('application_reviews', 'client_id', $clientIds);
        $this->deleteWhereIn('generated_documents', 'client_id', $clientIds);
        $this->deleteWhereIn('client_attachments', 'client_id', $clientIds);
        $this->deleteWhereIn('client_sites', 'client_id', $clientIds);
        $this->deleteWhereIn('client_processes', 'client_id', $clientIds);
        $this->deleteWhereIn('client_standards', 'client_id', $clientIds);
        $this->db->table('personnel')->whereIn('client_id', $clientIds)->update(['client_id' => null]);
        $this->deleteWhereIn('clients', 'id', $clientIds);
    }

    private function seedClientLifecycle(array $scenario, int $index): void
    {
        $base = new DateTimeImmutable($scenario['base']);
        $issue = $base->add(new DateInterval('P53D'));
        $expiry = $issue->add(new DateInterval('P3Y'))->sub(new DateInterval('P1D'));
        $surv1 = $issue->add(new DateInterval('P1Y'))->sub(new DateInterval('P1D'));
        $surv2 = $issue->add(new DateInterval('P2Y'))->sub(new DateInterval('P1D'));
        $clientNo = str_pad((string) $index, 3, '0', STR_PAD_LEFT);

        $this->db->table('clients')->insert([
            'tenant_id' => $this->tenantId,
            'company' => $scenario['company'],
            'legal_name' => $scenario['company'],
            'address' => $scenario['address'],
            'country' => 'Saudi Arabia',
            'city' => $scenario['city'],
            'contact_person' => $scenario['contact'],
            'designation' => 'Management Representative',
            'email' => $scenario['email'],
            'phone' => $scenario['phone'],
            'website' => 'https://demo-' . $index . '.qsi.test',
            'scope' => $scenario['scope'],
            'employee_count' => $scenario['employees'],
            'permanent_employees' => max(1, $scenario['employees'] - 8),
            'temporary_employees' => 8,
            'shift_pattern' => $index % 2 === 0 ? 'Two shifts' : 'Single shift',
            'seasonal_operations' => $index === 2 ? 'Peak season from Ramadan to Eid' : 'No significant seasonal operation',
            'number_of_sites' => $scenario['sites'],
            'certification_status' => 'certified',
            'risk_category' => $scenario['risk'],
            'certificate_number' => null,
            'initial_certification_date' => $issue->format('Y-m-d'),
            'certificate_issue_date' => $issue->format('Y-m-d'),
            'certificate_expiry_date' => $expiry->format('Y-m-d'),
            'notes' => 'Comprehensive seeded demo client. CR: 10' . $index . '445589; VAT: 30' . $index . '44558900003.',
            'created_by' => $this->users['sales'],
            'created_at' => $this->dateTime($base->format('Y-m-d'), '08:00:00'),
        ]);
        $clientId = (int) $this->db->insertID();

        $this->seedClientRepresentativePersonnel($clientId, $scenario);

        $standardIds = $this->seedClientMasterData($clientId, $scenario);
        $applicationId = $this->seedApplication($clientId, $scenario, $standardIds, $base, $clientNo);
        $reviewId = $this->seedApplicationReview($clientId, $applicationId, $scenario, $base, $standardIds, $clientNo);
        $proposalId = $this->seedProposal($clientId, $reviewId, $scenario, $base, $clientNo);
        $contractId = $this->seedContract($clientId, $proposalId, $scenario, $base, $clientNo);
        $this->seedInvoiceAndPayment($clientId, $scenario, $base, $clientNo);
        $programId = $this->seedAuditProgram($clientId, $contractId, $scenario, $base, $issue, $surv1, $surv2, $expiry, $clientNo, $standardIds);
        $eventIds = $this->seedAuditEventsAndWorkflow($programId, $clientId, $scenario, $base, $issue, $surv1, $surv2, $expiry, $clientNo, $standardIds);
        $stage2DecisionId = $eventIds['initial_stage2']['decision_id'] ?? null;
        $this->seedCertificates($clientId, $scenario, $standardIds, $stage2DecisionId, $issue, $expiry, $clientNo);
        $this->seedFeedbackAndNotifications($clientId, $programId, $scenario, $issue, $expiry, $clientNo);
        $this->seedSearchAndAuditLogs($clientId, $scenario, $base, $clientNo);
    }

    private function seedClientRepresentativePersonnel(int $clientId, array $scenario): void
    {
        $existing = $this->db->table('personnel')
            ->where('tenant_id', $this->tenantId)
            ->where('email', $scenario['email'])
            ->get(1)
            ->getRowArray();

        $payload = [
            'tenant_id' => $this->tenantId,
            'user_id' => null,
            'client_id' => $clientId,
            'full_name' => $scenario['contact'],
            'email' => $scenario['email'],
            'phone' => $scenario['phone'],
            'personnel_type' => 'client_representative',
            'approval_status' => 'approved',
            'languages' => json_encode(['English', 'Arabic'], JSON_THROW_ON_ERROR),
            'countries' => json_encode(['Saudi Arabia'], JSON_THROW_ON_ERROR),
            'experience_summary' => 'Client representative linked to ' . $scenario['company'] . '.',
        ];

        if ($existing === null) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('personnel')->insert($payload);
            return;
        }

        $this->db->table('personnel')
            ->where('id', (int) $existing['id'])
            ->update($payload);
    }

    private function seedClientMasterData(int $clientId, array $scenario): array
    {
        $standardIds = [];
        $iafId = $this->iaf[$scenario['iaf']]['id'] ?? null;
        $naceId = $this->nace[$scenario['nace']]['id'] ?? null;
        $foodId = $scenario['food'] === null ? null : ($this->food[$scenario['food']]['id'] ?? null);
        $medicalId = ($scenario['medical'] ?? null) === null ? null : ($this->medical[$scenario['medical']]['id'] ?? null);

        foreach ($scenario['standards'] as $code) {
            $standardId = (int) $this->standards[$code]['id'];
            $standardIds[$code] = $standardId;
            $this->db->table('client_standards')->insert([
                'client_id' => $clientId,
                'standard_id' => $standardId,
                'iaf_code_id' => $iafId,
                'nace_code_id' => $naceId,
                'food_chain_category_id' => str_contains($code, 'HACCP') || str_contains($code, '22000') ? $foodId : null,
                'medical_device_category_id' => str_contains($code, '13485') ? $medicalId : null,
                'scope' => $scenario['scope'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        for ($site = 1; $site <= $scenario['sites']; $site++) {
            $this->db->table('client_sites')->insert([
                'client_id' => $clientId,
                'site_name' => $site === 1 ? 'Head Office / Main Site' : 'Operational Site ' . $site,
                'address' => $scenario['address'] . ($site === 1 ? '' : ' - Site ' . $site),
                'country' => 'Saudi Arabia',
                'city' => $scenario['city'],
                'employee_count' => (int) ceil($scenario['employees'] / $scenario['sites']),
                'processes' => implode(', ', $scenario['processes']),
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        foreach ($scenario['processes'] as $process) {
            $this->db->table('client_processes')->insert([
                'client_id' => $clientId,
                'process_name' => $process,
                'description' => 'Demo process coverage for ' . $process . ' including inputs, outputs, controls, resources and records.',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        foreach (['Commercial_Registration.pdf', 'VAT_Certificate.pdf', 'Organization_Chart.pdf', 'Process_Flow.pdf'] as $file) {
            $this->db->table('client_attachments')->insert([
                'client_id' => $clientId,
                'uploaded_by' => $this->users['client_rep'],
                'category' => 'demo_document',
                'original_filename' => $file,
                'storage_path' => 'demo/client_' . $clientId . '/' . $file,
                'mime_type' => 'application/pdf',
                'file_size' => 125000,
                'checksum_sha256' => hash('sha256', $clientId . $file),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $standardIds;
    }

    private function seedApplication(int $clientId, array $scenario, array $standardIds, DateTimeImmutable $base, string $clientNo): int
    {
        $this->db->table('certification_applications')->insert([
            'tenant_id' => $this->tenantId,
            'client_id' => $clientId,
            'application_number' => 'APP-DEMO-' . $clientNo,
            'document_number' => 'F 25',
            'revision_number' => '1',
            'issue_number' => '2',
            'issue_date' => '2024-11-01',
            'status' => 'approved',
            'submitted_at' => $this->dateTime($base->format('Y-m-d'), '09:15:00'),
            'declaration_name' => $scenario['contact'],
            'declaration_position' => 'Management Representative',
            'declaration_date' => $base->format('Y-m-d'),
            'cb_review_status' => 'accepted',
            'cb_review_notes' => 'Application reviewed and accepted for certification processing.',
            'reviewed_by' => $this->users['technical_manager'],
            'reviewed_at' => $this->dateTime($this->plus($base, 1), '11:30:00'),
            'created_by' => $this->users['client_rep'],
            'created_at' => $this->dateTime($base->format('Y-m-d'), '09:00:00'),
        ]);
        $applicationId = (int) $this->db->insertID();

        foreach ($standardIds as $code => $standardId) {
            $this->db->table('application_selected_standards')->insert([
                'application_id' => $applicationId,
                'standard_id' => $standardId,
                'standard_code' => $code,
                'created_at' => $this->dateTime($base->format('Y-m-d'), '09:05:00'),
            ]);
        }

        $questions = $this->applicationQuestionsForStandards(array_keys($standardIds));

        foreach ($questions as $question) {
            $applicableStandards = $this->normaliseStandardCodes(json_decode((string) $question['applicable_standards'], true) ?: []);
            $this->db->table('application_questions')->insert([
                'application_id' => $applicationId,
                'question_library_id' => (int) $question['id'],
                'question_key' => $question['question_key'],
                'question_text' => $question['question_text'],
                'question_type' => $question['question_type'],
                'section' => $question['section'],
                'display_order' => (int) $question['display_order'],
                'mandatory' => (int) $question['mandatory'],
                'validation_rules' => $question['validation_rules'],
                'help_text' => $question['help_text'],
                'standard_codes' => json_encode($applicableStandards, JSON_THROW_ON_ERROR),
                'created_at' => $this->dateTime($base->format('Y-m-d'), '09:10:00'),
            ]);
            $applicationQuestionId = (int) $this->db->insertID();
            $this->db->table('application_answers')->insert([
                'application_id' => $applicationId,
                'application_question_id' => $applicationQuestionId,
                'question_library_id' => (int) $question['id'],
                'answer_text' => $this->answerForQuestion((string) $question['question_text'], $scenario),
                'answered_by' => $this->users['client_rep'],
                'answered_at' => $this->dateTime($base->format('Y-m-d'), '09:25:00'),
                'created_at' => $this->dateTime($base->format('Y-m-d'), '09:25:00'),
            ]);
        }

        foreach (['Application_Checklist.pdf', 'Legal_Documents.pdf', 'Scope_Statement.pdf'] as $file) {
            $this->db->table('application_attachments')->insert([
                'application_id' => $applicationId,
                'uploaded_by' => $this->users['client_rep'],
                'category' => 'application_evidence',
                'original_filename' => $file,
                'storage_path' => 'demo/application_' . $applicationId . '/' . $file,
                'mime_type' => 'application/pdf',
                'file_size' => 99000,
                'created_at' => $this->dateTime($base->format('Y-m-d'), '09:45:00'),
            ]);
        }

        return $applicationId;
    }

    private function applicationQuestionsForStandards(array $standardCodes): array
    {
        $selected = $this->normaliseStandardCodes($standardCodes);
        $questions = $this->db->table('question_library')
            ->where('active', 1)
            ->orderBy('section', 'ASC')
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_filter(
            $questions,
            fn (array $question): bool => ! $this->applicationQuestionExcluded($question)
                && $this->questionAppliesToStandards($question, $selected)
        ));
    }

    private function applicationQuestionExcluded(array $question): bool
    {
        return $this->applicationSectionExcluded((string) ($question['section'] ?? ''))
            || (string) ($question['question_type'] ?? '') === 'file';
    }

    private function applicationSectionExcluded(string $section): bool
    {
        return in_array($section, $this->excludedApplicationSections(), true)
            || str_ends_with(strtoupper(trim($section)), 'SPECIFIC QUESTIONS');
    }

    private function excludedApplicationSections(): array
    {
        return [
            'Supporting Documents',
            'Declaration',
            'HACCP Specific Questions',
        ];
    }

    private function questionAppliesToStandards(array $question, array $selectedStandards): bool
    {
        $applicable = $this->normaliseStandardCodes(json_decode((string) ($question['applicable_standards'] ?? '[]'), true) ?: []);

        return in_array('COMMON', $applicable, true) || array_intersect($applicable, $selectedStandards) !== [];
    }

    private function normaliseStandardCodes(array $codes): array
    {
        return array_values(array_unique(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            array_filter(array_map('strval', $codes), static fn (string $code): bool => trim($code) !== '')
        )));
    }

    private function seedApplicationReview(int $clientId, int $applicationId, array $scenario, DateTimeImmutable $base, array $standardIds, string $clientNo): int
    {
        $days = $this->durationDays($scenario);
        $payload = [
            'application_id' => 'APP-DEMO-' . $clientNo,
            'communication_language' => 'English and Arabic',
            'client_type' => 'New Client',
            'effective_employees' => (string) $scenario['employees'],
            'haccp_plans_processes' => $scenario['food'] === null ? '' : (string) max(2, count($scenario['processes']) - 1),
            'shifts_auditing' => $scenario['risk'] === 'high' ? 'Two shifts' : 'Single shift',
            'risk_classification' => ucfirst($scenario['risk']),
            'standards_text' => implode(', ', array_keys($standardIds)),
            'certification_route' => $this->demoCertificationRoute($scenario),
            'accreditation_body' => $this->demoAccreditationBody($scenario),
            'audit_category' => $scenario['food'] ?? $scenario['iaf'],
            'competence_requirements' => 'Competence confirmed for selected standards, scope, IAF/food category and risk profile.',
            'days_allotted' => number_format($days['total'], 2),
            'stage1_days' => number_format($days['stage1'], 2),
            'stage2_days' => number_format($days['stage2'], 2),
            'surveillance1_days' => number_format($days['surveillance'], 2),
            'surveillance2_days' => number_format($days['surveillance'], 2),
            'recertification_days' => number_format($days['recertification'], 2),
            'calculation_basis' => 'Demo calculation uses selected standards, employee count, site count, risk, and integrated audit logic.',
        ];

        $this->db->table('application_reviews')->insert([
            'client_id' => $clientId,
            'certification_application_id' => $applicationId,
            'application_review_number' => 'AR-DEMO-' . $clientNo,
            'document_number' => 'F 28',
            'revision_number' => '4',
            'issue_number' => '2',
            'document_date' => '2025-02-01',
            'technical_manager_id' => $this->users['technical_manager'],
            'quality_manager_id' => $this->users['quality_manager'],
            'completeness_status' => 'complete',
            'risk_rating' => $scenario['risk'],
            'recommendation' => 'Proceed to proposal',
            'md5_duration_days' => $days['total'],
            'iso22003_duration_days' => str_contains(implode(',', array_keys($standardIds)), '22000') ? $days['total'] : null,
            'integrated_reduction_percent' => count($standardIds) > 1 ? 10.00 : 0.00,
            'stage1_days' => $days['stage1'],
            'stage2_days' => $days['stage2'],
            'review_notes' => 'Scope, competence, resources and impartiality were reviewed. Certification service can be provided.',
            'review_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'qm_approved',
            'reviewed_at' => $this->dateTime($this->plus($base, 1), '14:00:00'),
            'technical_reviewer_name' => 'Ms. Rimsha Mahmoud',
            'technical_review_date' => $this->plus($base, 1),
            'quality_manager_status' => 'approved',
            'quality_manager_comments' => 'Independent quality approval granted.',
            'quality_manager_name' => 'Ms. Rimsha Mahmoud',
            'quality_manager_date' => $this->plus($base, 2),
            'general_manager_status' => 'not_required',
            'general_manager_comments' => 'GM approval is completed at certification decision stage.',
            'created_at' => $this->dateTime($this->plus($base, 1), '10:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function demoCertificationRoute(array $scenario): string
    {
        $codes = array_map('strtoupper', array_map('strval', $scenario['standards'] ?? []));
        if (count($codes) === 1 && str_contains($codes[0], 'HACCP')) {
            return 'unaccredited';
        }

        return 'accredited';
    }

    private function demoAccreditationBody(array $scenario): string
    {
        if ($this->demoCertificationRoute($scenario) !== 'accredited') {
            return '';
        }

        $joined = strtoupper(implode(' ', array_map('strval', $scenario['standards'] ?? [])));

        return str_contains($joined, '14001') || str_contains($joined, '45001') ? 'SAAC' : 'IAS';
    }

    private function seedProposal(int $clientId, int $reviewId, array $scenario, DateTimeImmutable $base, string $clientNo): int
    {
        [$certFee, $s1Fee, $s2Fee] = $scenario['fees'];
        $travel = 1200 + (int) $scenario['sites'] * 400;
        $subtotal = $certFee + $s1Fee + $s2Fee + $travel;
        $vat = round($subtotal * 0.15, 2);
        $total = $subtotal + $vat;

        $payload = [
            'intro_message' => 'Thank you for the opportunity to provide certification services. This proposal is based on the submitted application and application review.',
            'standards_text' => implode(', ', $scenario['standards']),
            'certification_route' => $this->demoCertificationRoute($scenario),
            'accreditation_body' => $this->demoAccreditationBody($scenario),
            'initial_audit_type' => 'Initial Certification',
            'total_audit_days' => number_format($this->durationDays($scenario)['total'], 2),
            'payment_terms' => '50% before Stage 1 audit and 50% before certificate issue.',
        ];

        $this->db->table('proposals')->insert([
            'tenant_id' => $this->tenantId,
            'client_id' => $clientId,
            'application_review_id' => $reviewId,
            'proposal_number' => 'PROP-DEMO-' . $clientNo,
            'version_number' => 1,
            'status' => 'accepted',
            'proposal_date' => $this->plus($base, 3),
            'client_reference' => 'CLIENT-REF-' . $clientNo,
            'valid_until' => $this->plus($base, 33),
            'certification_fee' => $certFee,
            'surveillance1_fee' => $s1Fee,
            'surveillance2_fee' => $s2Fee,
            'travel_fee' => $travel,
            'vat_percent' => 15.00,
            'vat_amount' => $vat,
            'grand_total' => $total,
            'currency' => 'SAR',
            'proposal_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_by' => $this->users['sales'],
            'approved_by' => $this->users['certification_manager'],
            'approved_at' => $this->dateTime($this->plus($base, 3), '16:00:00'),
            'created_at' => $this->dateTime($this->plus($base, 3), '10:00:00'),
        ]);
        $proposalId = (int) $this->db->insertID();

        foreach ([['certification', 'Initial certification audit', 1, $certFee], ['surveillance1', 'Surveillance audit 1', 1, $s1Fee], ['surveillance2', 'Surveillance audit 2', 1, $s2Fee], ['travel', 'Travel and logistics estimate', 1, $travel]] as $sort => [$type, $desc, $qty, $price]) {
            $this->db->table('proposal_line_items')->insert([
                'proposal_id' => $proposalId,
                'item_type' => $type,
                'description' => $desc,
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => $qty * $price,
                'sort_order' => $sort + 1,
            ]);
        }

        $this->db->table('proposal_versions')->insert([
            'proposal_id' => $proposalId,
            'version_number' => 1,
            'snapshot_json' => json_encode(['status' => 'accepted', 'grand_total' => $total], JSON_THROW_ON_ERROR),
            'change_summary' => 'Initial demo proposal accepted by client.',
            'created_by' => $this->users['sales'],
            'created_at' => $this->dateTime($this->plus($base, 4), '09:00:00'),
        ]);

        $this->db->table('proposal_approvals')->insert([
            'proposal_id' => $proposalId,
            'approver_id' => $this->users['certification_manager'],
            'decision' => 'approved',
            'comments' => 'Commercial proposal reviewed and approved.',
            'decided_at' => $this->dateTime($this->plus($base, 3), '16:00:00'),
        ]);

        return $proposalId;
    }

    private function seedContract(int $clientId, int $proposalId, array $scenario, DateTimeImmutable $base, string $clientNo): int
    {
        $payload = [
            'scope' => $scenario['scope'],
            'standards_text' => implode(', ', $scenario['standards']),
            'terms' => 'The client shall maintain compliance with certification requirements, allow access to audit evidence, and close nonconformities within agreed timelines.',
            'cycle_requirements' => 'Initial certification, Surveillance 1, Surveillance 2 and recertification are controlled in the three-year cycle.',
        ];

        $this->db->table('contracts')->insert([
            'tenant_id' => $this->tenantId,
            'client_id' => $clientId,
            'proposal_id' => $proposalId,
            'contract_number' => 'CON-DEMO-' . $clientNo,
            'document_number' => 'F 27',
            'revision_number' => '2',
            'issue_number' => '2',
            'document_date' => '2022-05-15',
            'version_number' => 1,
            'status' => 'signed',
            'signed_at' => $this->dateTime($this->plus($base, 6), '11:00:00'),
            'signed_by_name' => $scenario['contact'],
            'contract_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'qsi_signatory_name' => 'Dr. Rana Amjad Hanif',
            'qsi_signatory_date' => $this->plus($base, 6),
            'client_signatory_name' => $scenario['contact'],
            'client_signatory_date' => $this->plus($base, 6),
            'created_by' => $this->users['sales'],
            'created_at' => $this->dateTime($this->plus($base, 6), '10:00:00'),
        ]);
        $contractId = (int) $this->db->insertID();

        $this->db->table('contract_versions')->insert([
            'contract_id' => $contractId,
            'version_number' => 1,
            'snapshot_json' => json_encode(['status' => 'signed', 'signed_by' => $scenario['contact']], JSON_THROW_ON_ERROR),
            'created_by' => $this->users['sales'],
            'created_at' => $this->dateTime($this->plus($base, 6), '11:05:00'),
        ]);

        return $contractId;
    }

    private function seedInvoiceAndPayment(int $clientId, array $scenario, DateTimeImmutable $base, string $clientNo): void
    {
        $subtotal = array_sum($scenario['fees']);
        $vat = round($subtotal * 0.15, 2);
        $total = $subtotal + $vat;

        $this->db->table('invoices')->insert([
            'tenant_id' => $this->tenantId,
            'client_id' => $clientId,
            'invoice_number' => 'INV-DEMO-' . $clientNo,
            'invoice_date' => $this->plus($base, 7),
            'due_date' => $this->plus($base, 21),
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'total_amount' => $total,
            'currency' => 'SAR',
            'status' => 'paid',
            'created_at' => $this->dateTime($this->plus($base, 7), '10:30:00'),
        ]);
        $invoiceId = (int) $this->db->insertID();

        $this->db->table('payments')->insert([
            'invoice_id' => $invoiceId,
            'payment_date' => $this->plus($base, 8),
            'amount' => $total,
            'method' => 'Bank transfer',
            'reference_number' => 'BNK-DEMO-' . $clientNo,
            'received_by' => $this->users['finance'],
            'notes' => 'Demo payment received and allocated against certification invoice.',
            'created_at' => $this->dateTime($this->plus($base, 8), '12:00:00'),
        ]);
    }

    private function seedAuditProgram(int $clientId, int $contractId, array $scenario, DateTimeImmutable $base, DateTimeImmutable $issue, DateTimeImmutable $surv1, DateTimeImmutable $surv2, DateTimeImmutable $expiry, string $clientNo, array $standardIds): int
    {
        $programPayload = [
            'profile_version' => 2,
            'standard_signature' => implode('|', $scenario['standards']),
            'client_reference' => 'CON-DEMO-' . $clientNo,
            'standards_text' => implode(', ', $scenario['standards']),
            'category_label' => $scenario['food'] === null ? 'IAF scope code(s)' : 'Food chain category / sub-category',
            'process_label' => $scenario['food'] === null ? 'Key audited processes' : 'HACCP studies / food safety plans',
            'category_subcategory' => $scenario['food'] ?? $scenario['iaf'],
            'audit_language' => 'English and Arabic',
            'audit_type' => 'Initial Certification',
            'organization_name' => $scenario['company'],
            'head_office_address' => $scenario['address'],
            'site_addresses' => $scenario['sites'] > 1 ? 'Multiple sites as listed in client site records.' : 'Same as head office.',
            'scope' => $scenario['scope'],
            'exclusions' => 'None identified during application review.',
            'employee_count' => (string) $scenario['employees'],
            'shifts' => $scenario['risk'] === 'high' ? 'Two shifts' : 'Single shift',
            'haccp_studies' => $scenario['food'] === null ? implode(', ', $scenario['processes']) : (string) max(2, count($scenario['processes']) - 1),
            'audit_duration_days' => number_format($this->durationDays($scenario)['total'], 2),
            'stage1_days' => number_format($this->durationDays($scenario)['stage1'], 2),
            'stage2_days' => number_format($this->durationDays($scenario)['stage2'], 2),
            'surveillance1_days' => number_format($this->durationDays($scenario)['surveillance'], 2),
            'surveillance2_days' => number_format($this->durationDays($scenario)['surveillance'], 2),
            'recertification_days' => number_format($this->durationDays($scenario)['recertification'], 2),
            'coverage' => $this->coverageRows($standardIds, $scenario),
            'committee' => $this->committeeRows(),
            'nc_summary' => array_map(static fn (string $code): array => ['standard' => $code, 'initial_stage1' => 0, 'initial_stage2' => 0, 'surveillance1' => 0, 'surveillance2' => 0, 'recertification' => 0], $scenario['standards']),
            'legend_notes' => 'X indicates planned audit coverage. NC summary numbers are updated from seeded audit findings.',
        ];

        $this->db->table('audit_programs')->insert([
            'tenant_id' => $this->tenantId,
            'client_id' => $clientId,
            'contract_id' => $contractId,
            'program_number' => 'AP-DEMO-' . $clientNo,
            'document_number' => 'F 42',
            'revision_number' => '2',
            'issue_number' => '2',
            'document_date' => '2022-05-15',
            'cycle_type' => 'initial',
            'certificate_issue_date' => $issue->format('Y-m-d'),
            'surveillance_1_due_date' => $surv1->format('Y-m-d'),
            'surveillance_2_due_date' => $surv2->format('Y-m-d'),
            'certificate_expiry_date' => $expiry->format('Y-m-d'),
            'surveillance_1_status' => 'completed',
            'surveillance_2_status' => $surv2 < new DateTimeImmutable('today') ? 'completed' : 'active',
            'status' => 'active',
            'program_payload' => json_encode($programPayload, JSON_THROW_ON_ERROR),
            'prepared_by_name' => 'Rana Arslan Khan',
            'prepared_date' => $this->plus($base, 10),
            'approved_by_name' => 'Ms. Rimsha Mahmoud',
            'approved_date' => $this->plus($base, 11),
            'created_by' => $this->users['certification_manager'],
            'created_at' => $this->dateTime($this->plus($base, 10), '10:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function seedAuditEventsAndWorkflow(int $programId, int $clientId, array $scenario, DateTimeImmutable $base, DateTimeImmutable $issue, DateTimeImmutable $surv1, DateTimeImmutable $surv2, DateTimeImmutable $expiry, string $clientNo, array $standardIds): array
    {
        $durations = $this->durationDays($scenario);
        $definitions = [
            'initial_stage1' => [$this->plusDate($base, 17), $durations['stage1'], 'completed'],
            'initial_stage2' => [$this->plusDate($base, 41), $durations['stage2'], 'completed'],
            'surveillance1' => [$surv1->sub(new DateInterval('P12D')), $durations['surveillance'], 'completed'],
            'surveillance2' => [$surv2->sub(new DateInterval('P12D')), $durations['surveillance'], $surv2 < new DateTimeImmutable('today') ? 'completed' : 'in_progress'],
            'recertification' => [$expiry->sub(new DateInterval('P90D')), $durations['recertification'], 'planned'],
        ];

        $result = [];
        foreach ($definitions as $type => [$start, $duration, $status]) {
            $end = $this->endDate($start, $duration);
            $eventNo = strtoupper(str_replace(['initial_', 'surveillance'], ['', 'SV'], $type));
            $auditNumber = 'AUD-DEMO-' . $clientNo . '-' . strtoupper(str_replace('_', '-', $eventNo));
            $this->db->table('audit_events')->insert([
                'audit_program_id' => $programId,
                'event_type' => $type,
                'audit_number' => $auditNumber,
                'planned_start_date' => $start->format('Y-m-d'),
                'planned_end_date' => $end->format('Y-m-d'),
                'actual_start_date' => $status === 'planned' ? null : $start->format('Y-m-d'),
                'actual_end_date' => $status === 'planned' ? null : $end->format('Y-m-d'),
                'audit_window_start' => $start->sub(new DateInterval('P14D'))->format('Y-m-d'),
                'audit_window_end' => $end->add(new DateInterval('P14D'))->format('Y-m-d'),
                'duration_days' => $duration,
                'status' => $status,
                'created_at' => $this->dateTime($start->format('Y-m-d'), '08:00:00'),
            ]);
            $eventId = (int) $this->db->insertID();

            $this->seedAppointment($eventId, $start);
            $this->seedAuditPlan($eventId, $type, $start, $end, $scenario, $clientNo);
            $reportId = $this->seedAuditReport($eventId, $type, $start, $scenario, $standardIds, $status);
            $ncrIds = $this->seedNcrsAndCapas($eventId, $type, $scenario, $standardIds, $start, $clientNo);
            $reviewId = $this->seedTechnicalReview($eventId, $type, $start, $ncrIds);
            $decisionId = $this->seedDecision($reviewId, $type, $start, $status);
            $this->seedEventReminders($eventId, $type, $start);

            $result[$type] = [
                'event_id' => $eventId,
                'report_id' => $reportId,
                'review_id' => $reviewId,
                'decision_id' => $decisionId,
            ];
        }

        return $result;
    }

    private function seedAppointment(int $eventId, DateTimeImmutable $start): void
    {
        foreach ([['lead_auditor', 'lead_auditor'], ['auditor', 'auditor']] as [$key, $role]) {
            $this->db->table('auditor_appointments')->insert([
                'audit_event_id' => $eventId,
                'personnel_id' => $this->personnel[$key],
                'appointment_role' => $role,
                'appointed_by' => $this->users['technical_manager'],
                'appointed_at' => $this->dateTime($start->sub(new DateInterval('P6D'))->format('Y-m-d'), '09:30:00'),
                'status' => 'appointed',
                'conflict_check_json' => json_encode([
                    'competence_confirmed' => true,
                    'impartiality_confirmed' => true,
                    'conflict_of_interest' => false,
                    'notes' => 'No consulting, training or financial conflict identified.',
                ], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    private function seedAuditPlan(int $eventId, string $type, DateTimeImmutable $start, DateTimeImmutable $end, array $scenario, string $clientNo): void
    {
        $this->db->table('audit_plans')->insert([
            'audit_event_id' => $eventId,
            'plan_number' => 'PLAN-DEMO-' . $clientNo . '-' . strtoupper(str_replace('_', '-', $type)),
            'version_number' => 1,
            'status' => $type === 'recertification' ? 'prepared' : 'approved',
            'prepared_by' => $this->users['lead_auditor'],
            'approved_by' => $type === 'recertification' ? null : $this->users['technical_manager'],
            'approved_at' => $type === 'recertification' ? null : $this->dateTime($start->sub(new DateInterval('P4D'))->format('Y-m-d'), '14:00:00'),
            'created_at' => $this->dateTime($start->sub(new DateInterval('P5D'))->format('Y-m-d'), '10:00:00'),
        ]);
        $planId = (int) $this->db->insertID();

        $rows = [
            ['09:00:00', '09:30:00', 'Opening meeting', 'Top Management', 'Opening meeting and audit confirmation', 'All applicable'],
            ['09:30:00', '11:30:00', 'Process audit', 'Operations', $scenario['processes'][0], 'Context, planning and operation'],
            ['11:30:00', '12:30:00', 'Support process audit', 'Support', $scenario['processes'][1] ?? 'Support process', 'Resources and competence'],
            ['13:30:00', '15:00:00', 'Performance review', 'Quality / HSE / Food Safety', 'Monitoring, internal audit and management review', 'Performance evaluation'],
            ['15:00:00', '16:00:00', 'Closing meeting', 'Top Management', 'Findings, conclusions and recommendation', 'Improvement'],
        ];

        foreach ($rows as $sort => [$st, $et, $activity, $department, $process, $clauses]) {
            $this->db->table('audit_plan_items')->insert([
                'audit_plan_id' => $planId,
                'audit_date' => $start->format('Y-m-d'),
                'start_time' => $st,
                'end_time' => $et,
                'activity_type' => $activity,
                'department' => $department,
                'process_name' => $process,
                'clauses' => $clauses,
                'auditor_personnel_id' => $sort % 2 === 0 ? $this->personnel['lead_auditor'] : $this->personnel['auditor'],
                'notes' => 'Objective evidence will be sampled through interviews, document review and site observation.',
                'sort_order' => $sort + 1,
            ]);
        }
    }

    private function seedAuditReport(int $eventId, string $type, DateTimeImmutable $start, array $scenario, array $standardIds, string $status): int
    {
        $payload = [
            'audit_objectives' => 'Determine conformity, implementation effectiveness and ability to meet certification requirements.',
            'audit_criteria' => implode(', ', array_keys($standardIds)) . ', client procedures, statutory and regulatory requirements.',
            'audit_scope' => $scenario['scope'],
            'opening_meeting' => 'Opening meeting conducted with top management and process owners.',
            'closing_meeting' => 'Closing meeting conducted. Findings and next steps were communicated.',
            'departments_audited' => ['Top Management', 'Operations', 'Quality / Food Safety / HSE', 'Support Processes'],
            'positive_observations' => 'Management demonstrated good awareness of certification requirements and customer obligations.',
            'opportunities_for_improvement' => 'Improve trend analysis and link KPIs more clearly to process risks and objectives.',
            'audit_conclusion' => $status === 'planned' ? 'Audit not yet conducted.' : 'The audit objectives were achieved and recommendation is recorded in the workflow.',
            'recommendation' => $status === 'planned' ? 'Pending audit execution' : ($type === 'initial_stage1' ? 'Proceed to Stage 2 subject to readiness actions.' : 'Certification status can be maintained subject to NCR/CAPA closure.'),
        ];

        $this->db->table('report_drafts')->insert([
            'tenant_id' => $this->tenantId,
            'audit_event_id' => $eventId,
            'report_type' => 'audit_execution',
            'version_number' => 1,
            'status' => $status === 'planned' ? 'draft' : 'approved',
            'generated_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'editable_payload' => json_encode(['auditor_notes' => 'Demo report populated with realistic audit notes and conclusions.'], JSON_THROW_ON_ERROR),
            'prepared_by' => $this->users['lead_auditor'],
            'approved_by' => $status === 'planned' ? null : $this->users['technical_manager'],
            'approved_at' => $status === 'planned' ? null : $this->dateTime($start->add(new DateInterval('P1D'))->format('Y-m-d'), '16:00:00'),
            'created_at' => $this->dateTime($start->add(new DateInterval('P1D'))->format('Y-m-d'), '10:00:00'),
        ]);
        $reportId = (int) $this->db->insertID();

        $clauses = $this->clausesForStandards($standardIds, 10);
        foreach ($clauses as $sort => $clause) {
            $this->db->table('report_sections')->insert([
                'report_draft_id' => $reportId,
                'clause_library_id' => (int) $clause['id'],
                'section_key' => 'conformity',
                'section_title' => $clause['standard_code'] . ' ' . $clause['clause_number'] . ' - ' . $clause['clause_title'],
                'section_content' => 'Conformity evidence reviewed. Records, interviews and observations support implementation. Auditor may edit this note during execution.',
                'sort_order' => $sort + 1,
            ]);
        }

        return $reportId;
    }

    private function seedNcrsAndCapas(int $eventId, string $type, array $scenario, array $standardIds, DateTimeImmutable $start, string $clientNo): array
    {
        $count = (int) ($scenario['ncrs'][$type] ?? 0);
        $clauses = $this->clausesForStandards($standardIds, max(1, $count));
        $ncrIds = [];
        $evidence = ['Training_Record_QA.pdf', 'Calibration_Certificate.pdf', 'Pest_Control_Report.pdf', 'Cleaning_Record_Line1.pdf', 'Supplier_Approval_Form.pdf', 'Maintenance_Log.pdf', 'CCP_Monitoring_Record.pdf', 'Internal_Audit_Report.pdf', 'Management_Review_Minutes.pdf'];

        for ($i = 1; $i <= $count; $i++) {
            $clause = $clauses[($i - 1) % count($clauses)];
            $classification = $i % 5 === 0 ? 'major' : 'minor';
            $status = $i % 4 === 0 ? 'open' : 'closed';
            $target = $start->add(new DateInterval('P' . (14 + $i * 2) . 'D'));
            $closed = $status === 'closed' ? $target->add(new DateInterval('P3D')) : null;
            $ncrNumber = 'NCR-DEMO-' . $clientNo . '-' . strtoupper(str_replace('_', '-', $type)) . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $nc = $this->ncrScenario($classification, $clause, $scenario, $type, $i);

            $this->db->table('ncrs')->insert([
                'tenant_id' => $this->tenantId,
                'audit_event_id' => $eventId,
                'clause_library_id' => (int) $clause['id'],
                'ncr_number' => $ncrNumber,
                'requirement' => $clause['requirement'] ?? ($clause['clause_title'] . ' requirement'),
                'finding' => $nc['finding'],
                'objective_evidence' => $nc['objective_evidence'],
                'classification' => $classification,
                'correction' => $nc['correction'],
                'root_cause' => $nc['root_cause'],
                'corrective_action' => $nc['corrective_action'],
                'responsible_person' => $scenario['contact'],
                'target_date' => $target->format('Y-m-d'),
                'verification' => $nc['verification'],
                'closure_notes' => $status === 'closed' ? 'Closed after evidence review and effectiveness verification.' : 'Awaiting complete evidence submission.',
                'status' => $status,
                'closed_at' => $closed?->format('Y-m-d 15:30:00'),
                'created_by' => $this->users['lead_auditor'],
                'created_at' => $this->dateTime($start->add(new DateInterval('P1D'))->format('Y-m-d'), '11:00:00'),
            ]);
            $ncrId = (int) $this->db->insertID();
            $ncrIds[] = $ncrId;

            $this->db->table('ncr_evidence')->insert([
                'ncr_id' => $ncrId,
                'uploaded_by' => $this->users['lead_auditor'],
                'original_filename' => $evidence[($i - 1) % count($evidence)],
                'storage_path' => 'demo/ncr/' . $ncrNumber . '/' . $evidence[($i - 1) % count($evidence)],
                'created_at' => $this->dateTime($start->add(new DateInterval('P1D'))->format('Y-m-d'), '11:15:00'),
            ]);

            $capaStatus = $status === 'closed' ? 'closed' : ($i % 2 === 0 ? 'rejected' : 'open');
            $this->db->table('capas')->insert([
                'tenant_id' => $this->tenantId,
                'ncr_id' => $ncrId,
                'capa_number' => str_replace('NCR', 'CAPA', $ncrNumber),
                'source' => 'audit_ncr',
                'issue' => $nc['finding'],
                'immediate_correction' => $nc['correction'],
                'root_cause' => $nc['root_cause'],
                'five_why' => json_encode($nc['five_why'], JSON_THROW_ON_ERROR),
                'fishbone' => json_encode($nc['fishbone'], JSON_THROW_ON_ERROR),
                'corrective_action' => $nc['corrective_action'],
                'preventive_action' => $nc['preventive_action'],
                'responsible_person' => $scenario['contact'],
                'target_date' => $target->format('Y-m-d'),
                'evidence_reference' => $nc['evidence_reference'] . '; ' . $evidence[($i + 2) % count($evidence)],
                'verification' => $capaStatus === 'closed' ? $nc['verification'] : 'Evidence review pending or returned for correction.',
                'effectiveness' => $capaStatus === 'closed' ? $nc['effectiveness'] : 'Effectiveness verification pending.',
                'closure_notes' => $capaStatus === 'closed' ? 'CAPA closed after verification.' : 'CAPA remains under client action.',
                'status' => $capaStatus,
                'closed_at' => $capaStatus === 'closed' ? $target->add(new DateInterval('P5D'))->format('Y-m-d 13:00:00') : null,
                'created_by' => $this->users['client_rep'],
                'created_at' => $this->dateTime($target->sub(new DateInterval('P2D'))->format('Y-m-d'), '10:00:00'),
            ]);
            $capaId = (int) $this->db->insertID();

            $this->db->table('capa_evidence')->insert([
                'capa_id' => $capaId,
                'uploaded_by' => $this->users['client_rep'],
                'original_filename' => $evidence[($i + 2) % count($evidence)],
                'storage_path' => 'demo/capa/' . str_replace('NCR', 'CAPA', $ncrNumber) . '/' . $evidence[($i + 2) % count($evidence)],
                'created_at' => $this->dateTime($target->format('Y-m-d'), '10:30:00'),
            ]);
        }

        return $ncrIds;
    }

    private function seedTechnicalReview(int $eventId, string $type, DateTimeImmutable $start, array $ncrIds): int
    {
        $pending = $type === 'recertification';
        $this->db->table('technical_reviews')->insert([
            'tenant_id' => $this->tenantId,
            'audit_event_id' => $eventId,
            'reviewer_personnel_id' => $this->personnel['technical_reviewer'],
            'checklist_payload' => json_encode([
                'review_notes' => $pending ? 'Review pending until recertification audit is completed.' : 'Audit file, plan, report, NCR/CAPA and competence records reviewed.',
                'ncr_count' => count($ncrIds),
                'recommendation_basis' => 'Demo technical review populated for workflow testing.',
            ], JSON_THROW_ON_ERROR),
            'competency_confirmed' => $pending ? 0 : 1,
            'duration_confirmed' => $pending ? 0 : 1,
            'application_confirmed' => $pending ? 0 : 1,
            'reports_confirmed' => $pending ? 0 : 1,
            'ncr_capa_confirmed' => $pending ? 0 : 1,
            'scope_dates_confirmed' => $pending ? 0 : 1,
            'impartiality_confirmed' => $pending ? 0 : 1,
            'recommendation' => $pending ? 'pending' : 'approve',
            'status' => $pending ? 'pending' : 'approved',
            'reviewed_at' => $pending ? null : $this->dateTime($start->add(new DateInterval('P9D'))->format('Y-m-d'), '14:00:00'),
            'created_at' => $this->dateTime($start->add(new DateInterval('P8D'))->format('Y-m-d'), '10:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function seedDecision(int $reviewId, string $type, DateTimeImmutable $start, string $status): int
    {
        $pending = $type === 'recertification' || $status === 'planned';
        $decision = match ($type) {
            'initial_stage1' => 'continue_to_stage2',
            'initial_stage2' => 'grant',
            'surveillance1', 'surveillance2' => 'maintain',
            'recertification' => 'renew',
            default => 'approve',
        };

        $this->db->table('certification_decisions')->insert([
            'tenant_id' => $this->tenantId,
            'technical_review_id' => $reviewId,
            'decision_maker_personnel_id' => $this->personnel['decision_maker'],
            'decision' => $decision,
            'reason' => $pending ? 'Decision pending completion of planned audit activity.' : 'Certification decision made based on approved technical review and audit file.',
            'electronic_signature' => $pending ? null : 'Dr. Reem Mansour / e-signature',
            'decided_at' => $pending ? null : $this->dateTime($start->add(new DateInterval('P10D'))->format('Y-m-d'), '11:00:00'),
            'status' => $pending ? 'pending' : 'approved',
            'gm_approved_by_user_id' => $pending ? null : $this->users['general_manager'],
            'gm_approval_notes' => $pending ? null : 'Final management approval granted for certification workflow.',
            'gm_approved_at' => $pending ? null : $this->dateTime($start->add(new DateInterval('P10D'))->format('Y-m-d'), '15:00:00'),
            'created_at' => $this->dateTime($start->add(new DateInterval('P10D'))->format('Y-m-d'), '10:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function seedCertificates(int $clientId, array $scenario, array $standardIds, ?int $decisionId, DateTimeImmutable $issue, DateTimeImmutable $expiry, string $clientNo): void
    {
        foreach (array_keys($standardIds) as $idx => $code) {
            $certificateNumber = $this->nextCertificateNumber($code);
            $slug = strtolower(str_replace([' ', ':'], '-', $certificateNumber));
            $this->db->table('certificates')->insert([
                'tenant_id' => $this->tenantId,
                'client_id' => $clientId,
                'certification_decision_id' => $decisionId,
                'certificate_number' => $certificateNumber,
                'standard_id' => (int) $standardIds[$code],
                'scope' => $scenario['scope'],
                'issue_date' => $issue->format('Y-m-d'),
                'expiry_date' => $expiry->format('Y-m-d'),
                'initial_certification_date' => $issue->format('Y-m-d'),
                'status' => 'active',
                'qr_payload' => 'https://verify.qsi.local/certificates/' . $slug,
                'public_slug' => $slug,
                'created_at' => $this->dateTime($issue->format('Y-m-d'), '10:00:00'),
            ]);
            $certificateId = (int) $this->db->insertID();

            if ($idx === 0) {
                $this->db->table('clients')->where('id', $clientId)->update([
                    'certificate_number' => $certificateNumber,
                ]);
            }

            $this->db->table('certificate_public_events')->insert([
                'certificate_id' => $certificateId,
                'search_term' => $certificateNumber,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Demo verification check',
                'created_at' => $this->dateTime('2026-07-04', '13:00:00'),
            ]);
        }
    }

    private function nextCertificateNumber(string $standardCode): string
    {
        $prefix = 'QSI-' . $this->certificateStandardPrefix($standardCode);
        $rows = $this->db->table('certificates')
            ->select('certificate_number')
            ->where('tenant_id', $this->tenantId)
            ->like('certificate_number', $prefix . '-', 'after')
            ->get()
            ->getResultArray();

        $max = 0;
        foreach ($rows as $row) {
            $number = (string) ($row['certificate_number'] ?? '');
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d{4,})$/', $number, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $prefix . '-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function certificateStandardPrefix(string $standardCode): string
    {
        $code = strtoupper(trim($standardCode));
        $code = str_replace([':', '.', '/', '\\'], ' ', $code);
        $parts = preg_split('/[^A-Z0-9]+/', $code) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== '' && ! preg_match('/^(19|20)\d{2}$/', $part)));

        if ($parts === []) {
            return 'STANDARD';
        }

        return substr(implode('', $parts), 0, 24);
    }

    private function seedFeedbackAndNotifications(int $clientId, int $programId, array $scenario, DateTimeImmutable $issue, DateTimeImmutable $expiry, string $clientNo): void
    {
        $this->db->table('client_feedback')->insert([
            'tenant_id' => $this->tenantId,
            'client_id' => $clientId,
            'audit_program_id' => $programId,
            'certificate_id' => null,
            'contact_name' => $scenario['contact'],
            'contact_email' => $scenario['email'],
            'submitted_at' => $this->dateTime($issue->add(new DateInterval('P7D'))->format('Y-m-d'), '12:00:00'),
            'overall_rating' => 4 + ($clientNo % 2),
            'communication_rating' => 5,
            'auditor_rating' => 4,
            'report_quality_rating' => 5,
            'comments' => 'Professional audit team, clear communication and useful findings.',
            'improvement_suggestion' => 'Send audit plan one week earlier where possible.',
            'status' => 'submitted',
            'created_by' => $this->users['client_rep'],
            'created_at' => $this->dateTime($issue->add(new DateInterval('P7D'))->format('Y-m-d'), '12:00:00'),
        ]);

        foreach ([
            ['Proposal Approved', 'Proposal approved and sent to client.'],
            ['Audit Assigned', 'Lead auditor and audit team assigned.'],
            ['CAPA Due', 'CAPA response is due soon.'],
            ['Certificate Issued', 'Certificate issued and sent to client.'],
            ['Surveillance Due', 'Surveillance audit due date is approaching.'],
            ['Technical Review Pending', 'Audit file is awaiting technical review.'],
            ['Decision Pending', 'Certification decision is pending.'],
            ['Expiry Reminder', 'Certificate expiry reminder generated for recertification planning.'],
            ['Email: Proposal Sent', 'Email log: proposal document sent to client.'],
            ['Email: Certificate Sent', 'Email log: certificate sent to client.'],
        ] as $offset => [$title, $body]) {
            $this->db->table('notifications')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->users[$offset % 2 === 0 ? 'quality_manager' : 'technical_manager'],
                'title' => 'Demo: ' . $title,
                'body' => $scenario['company'] . ' - ' . $body,
                'channel' => str_starts_with($title, 'Email:') ? 'email' : 'dashboard',
                'related_module' => 'demo_workflow',
                'related_id' => $clientId,
                'status' => $offset % 3 === 0 ? 'unread' : 'read',
                'sent_at' => $this->dateTime($issue->add(new DateInterval('P' . (1 + $offset) . 'D'))->format('Y-m-d'), '09:00:00'),
                'read_at' => $offset % 3 === 0 ? null : $this->dateTime($issue->add(new DateInterval('P' . (1 + $offset) . 'D'))->format('Y-m-d'), '10:00:00'),
                'created_at' => $this->dateTime($issue->add(new DateInterval('P' . (1 + $offset) . 'D'))->format('Y-m-d'), '09:00:00'),
            ]);
        }
    }

    private function seedSearchAndAuditLogs(int $clientId, array $scenario, DateTimeImmutable $base, string $clientNo): void
    {
        $this->db->table('global_search_index')->insert([
            'tenant_id' => $this->tenantId,
            'module' => 'demo',
            'entity_table' => 'clients',
            'entity_id' => $clientId,
            'title' => $scenario['company'],
            'summary' => $scenario['scope'],
            'keywords' => implode(' ', array_merge($scenario['standards'], $scenario['processes'])),
            'status' => 'certified',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        foreach (['application_submitted', 'application_review_approved', 'proposal_accepted', 'contract_signed', 'audit_program_created', 'certificate_issued', 'feedback_received'] as $offset => $action) {
            $this->db->table('audit_logs')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->users['administrator'],
                'action' => $action,
                'module' => 'demo_data',
                'entity_table' => 'clients',
                'entity_id' => $clientId,
                'after_json' => json_encode(['client' => $scenario['company'], 'step' => $action], JSON_THROW_ON_ERROR),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'QSI AMS Demo Seeder',
                'created_at' => $this->dateTime($this->plus($base, $offset + 1), '08:00:00'),
            ]);
        }
    }

    private function seedEventReminders(int $eventId, string $type, DateTimeImmutable $start): void
    {
        foreach ([30, 7] as $days) {
            $this->db->table('audit_reminders')->insert([
                'audit_event_id' => $eventId,
                'reminder_type' => $type . '_due_' . $days,
                'due_date' => $start->sub(new DateInterval('P' . $days . 'D'))->format('Y-m-d'),
                'status' => $start < new DateTimeImmutable('today') ? 'sent' : 'open',
                'sent_at' => $start < new DateTimeImmutable('today') ? $this->dateTime($start->sub(new DateInterval('P' . $days . 'D'))->format('Y-m-d'), '08:00:00') : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function seedManagementSystemRecords(): void
    {
        $this->db->table('management_reviews')->where('tenant_id', $this->tenantId)->like('review_number', 'MR-DEMO', 'after')->delete();
        $this->db->table('internal_audits')->where('tenant_id', $this->tenantId)->like('audit_number', 'IA-DEMO', 'after')->delete();

        $this->db->table('management_reviews')->insert([
            'tenant_id' => $this->tenantId,
            'review_number' => 'MR-DEMO-2026-001',
            'meeting_date' => '2026-06-20',
            'agenda' => json_encode(['Audit performance', 'Client feedback', 'Impartiality', 'Competence', 'Revenue and resources'], JSON_THROW_ON_ERROR),
            'inputs' => json_encode(['open_capas' => 6, 'customer_satisfaction' => 4.6, 'upcoming_surveillance' => 5], JSON_THROW_ON_ERROR),
            'outputs' => json_encode(['actions' => ['Improve audit plan lead time', 'Enhance CAPA reminder process']], JSON_THROW_ON_ERROR),
            'minutes' => 'Demo management review minutes covering the operating performance of the certification body.',
            'status' => 'completed',
            'chairperson_id' => $this->users['general_manager'],
            'created_at' => '2026-06-20 09:00:00',
        ]);

        $this->db->table('internal_audits')->insert([
            'tenant_id' => $this->tenantId,
            'audit_number' => 'IA-DEMO-2026-001',
            'scope' => 'Internal audit of certification process, impartiality controls, file review and decision making.',
            'planned_date' => '2026-06-10',
            'completed_date' => '2026-06-11',
            'checklist_json' => json_encode(['ISO 17021 clauses' => 'Completed', 'Records sampled' => 15], JSON_THROW_ON_ERROR),
            'status' => 'completed',
            'lead_auditor_id' => $this->personnel['lead_auditor'],
            'created_at' => '2026-06-10 09:00:00',
        ]);
    }

    private function coverageRows(array $standardIds, array $scenario): array
    {
        $rows = [];
        foreach ($this->clausesForStandards($standardIds, 40) as $clause) {
            $number = (string) $clause['clause_number'];
            $stage1 = str_starts_with($number, '4') || str_starts_with($number, '5') || str_starts_with($number, '6') || str_starts_with($number, '7');
            $rows[] = [
                'standard' => $clause['standard_code'],
                'clause_number' => $number,
                'clause_title' => $clause['clause_title'],
                'initial_stage1' => $stage1 ? 'X' : '',
                'initial_stage2' => 'X',
                'surveillance1' => 'X',
                'surveillance2' => 'X',
                'recertification' => 'X',
            ];
        }

        $additional = $scenario['food'] === null
            ? ['Customer and statutory/regulatory requirements', 'Scope and complexity of the management system', 'Process performance and operational control', 'Internal audit and management review results', 'Customer satisfaction, complaints and improvement trends']
            : ['Product and process food safety review', 'PRP / OPRP / CCP control effectiveness', 'Traceability, withdrawal and recall arrangements', 'Food safety hazard analysis and validation status', 'Food safety legal and customer requirements'];

        foreach ($additional as $title) {
            $rows[] = [
                'standard' => 'Additional Requirement',
                'clause_number' => '',
                'clause_title' => $title,
                'initial_stage1' => '',
                'initial_stage2' => 'X',
                'surveillance1' => 'X',
                'surveillance2' => 'X',
                'recertification' => 'X',
            ];
        }

        return $rows;
    }

    private function committeeRows(): array
    {
        return [
            ['role' => 'Lead Auditor', 'initial_stage1' => 'Mr. Rifki El-Sherbeny', 'initial_stage2' => 'Mr. Rifki El-Sherbeny', 'surveillance1' => 'Mr. Rifki El-Sherbeny', 'surveillance2' => 'Mr. Rifki El-Sherbeny', 'recertification' => 'Mr. Rifki El-Sherbeny'],
            ['role' => 'Auditor 1', 'initial_stage1' => 'Mohammad Arshad Ali', 'initial_stage2' => 'Mohammad Arshad Ali', 'surveillance1' => 'Mohammad Arshad Ali', 'surveillance2' => 'Mohammad Arshad Ali', 'recertification' => 'Mohammad Arshad Ali'],
            ['role' => 'Auditor 2', 'initial_stage1' => '', 'initial_stage2' => '', 'surveillance1' => '', 'surveillance2' => '', 'recertification' => ''],
            ['role' => 'Technical Specialist', 'initial_stage1' => 'Mohammad Raheel', 'initial_stage2' => 'Mohammad Raheel', 'surveillance1' => '', 'surveillance2' => '', 'recertification' => 'Mohammad Raheel'],
            ['role' => 'Additional / Trainee Auditor', 'initial_stage1' => '', 'initial_stage2' => '', 'surveillance1' => '', 'surveillance2' => '', 'recertification' => ''],
            ['role' => 'Observer', 'initial_stage1' => '', 'initial_stage2' => '', 'surveillance1' => '', 'surveillance2' => '', 'recertification' => ''],
        ];
    }

    private function durationDays(array $scenario): array
    {
        $total = 3.0 + (count($scenario['standards']) - 1) * 0.75 + max(0, $scenario['sites'] - 1) * 0.5;
        if ($scenario['risk'] === 'high') {
            $total += 0.5;
        }
        $stage1 = $total <= 3 ? 1.0 : 1.5;
        $stage2 = max(1.0, $total - $stage1);

        return [
            'total' => round($total * 2) / 2,
            'stage1' => $stage1,
            'stage2' => round($stage2 * 2) / 2,
            'surveillance' => max(1.0, round(($total / 3) * 2) / 2),
            'recertification' => max(1.5, round(($total * 2 / 3) * 2) / 2),
        ];
    }

    private function clausesForStandards(array $standardIds, int $limit): array
    {
        return $this->db->table('clause_library')
            ->select('clause_library.*, standards.code AS standard_code')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->whereIn('clause_library.standard_id', array_values($standardIds))
            ->where('clause_library.active', 1)
            ->orderBy('standards.code', 'ASC')
            ->orderBy('clause_number', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function ncrScenario(string $classification, array $clause, array $scenario, string $eventType, int $index): array
    {
        $standard = strtoupper((string) ($clause['standard_code'] ?? ''));
        $title = (string) ($clause['clause_title'] ?? 'applicable requirement');
        $clauseContext = strtolower($standard . ' ' . $title . ' ' . (string) ($clause['requirement'] ?? ''));
        $context = $clauseContext . ' ' . strtolower((string) $scenario['scope']);
        $process = $scenario['processes'][($index - 1) % count($scenario['processes'])];
        $severityText = $classification === 'major'
            ? 'The absence of effective control could affect confidence in the system if not corrected before certification/maintenance decision.'
            : 'The issue was isolated in the sampled record and no direct product/service release impact was confirmed from the audit sample.';

        if ($scenario['food'] !== null || str_contains($context, 'haccp') || str_contains($context, 'food') || str_contains($context, '22000') || str_contains($context, 'fssc')) {
            $foodTheme = $this->foodNcrTheme($clauseContext, $index);
            if ($foodTheme === 'traceability') {
                return $this->ncrPayload(
                    $classification,
                    "Traceability sample for {$process} was not fully completed from receiving lot to dispatch reference; one intermediate preparation/packing record was not linked.",
                    'Traceability exercise sample selected during ' . str_replace('_', ' ', $eventType) . ' could not demonstrate complete one-step-back/one-step-forward linkage for the selected lot.',
                    'The affected traceability record was completed after reconciliation with receiving, production and dispatch logs, and the lot file was rechecked by QA.',
                    'The traceability form did not require verification of all intermediate process references before release of the file.',
                    'Revise the traceability checklist to include receiving, processing/packing, holding and dispatch references; brief QA/dispatch staff; add monthly mock trace sample.',
                    'Updated traceability checklist, corrected lot file, staff briefing record and next mock traceability result.',
                    'Auditor reviewed corrected lot file and sampled one additional dispatch lot for full linkage.',
                    $severityText
                );
            }

            if ($foodTheme === 'prp') {
                return $this->ncrPayload(
                    $classification,
                    "Cleaning verification for {$process} was not recorded for one sampled shift although the cleaning activity was marked as completed.",
                    'Cleaning record sample showed completion tick/signature, but verification column and supervisor release were blank for the selected area/date.',
                    'Supervisor completed verification of the affected area and recorded the result before next production use.',
                    'Shift handover practice did not clearly define who verifies cleaning records before area release.',
                    'Update sanitation record review responsibility, brief supervisors, and include cleaning verification in weekly PRP verification checks.',
                    'Revised sanitation checklist, supervisor briefing record and weekly PRP verification log.',
                    'Auditor sampled subsequent cleaning records and confirmed verification was completed before area release.',
                    $severityText
                );
            }

            if ($foodTheme === 'supplier') {
                return $this->ncrPayload(
                    $classification,
                    "Supplier/material approval file for {$process} did not include complete food-safety approval evidence for one sampled input.",
                    'Supplier file sample was missing current specification/approval evidence although the material remained on the approved supplier list.',
                    'QA obtained the missing supplier approval evidence and confirmed the affected material remained acceptable for use.',
                    'Supplier review criteria did not require evidence-completeness verification before continued approval.',
                    'Revise supplier approval review criteria, review active food-safety critical suppliers and brief purchasing/QA personnel.',
                    'Updated supplier approval form, supplier document sample, approved supplier list review and staff briefing.',
                    'Auditor checked the revised supplier file and sampled another food-safety critical supplier for complete approval evidence.',
                    $severityText
                );
            }

            if ($foodTheme === 'release') {
                return $this->ncrPayload(
                    $classification,
                    "Product release verification for {$process} did not fully evidence allergen/label or final QA release checks for one sampled lot.",
                    'Release record sample showed product dispatch approval, but the allergen/label verification field was incomplete for the selected lot.',
                    'QA reviewed the affected lot, confirmed label/release status and completed the missing verification evidence.',
                    'The release checklist did not clearly require independent allergen/label verification before final release.',
                    'Revise release checklist, brief QA/release personnel and sample released lots for allergen/label verification completion.',
                    'Corrected release record, revised release checklist, briefing record and subsequent release sample.',
                    'Auditor reviewed corrected release evidence and sampled a subsequent product/lot for completed allergen/label verification.',
                    $severityText
                );
            }

            return $this->ncrPayload(
                $classification,
                "CCP/OPRP monitoring record for {$process} did not show documented action when a value approached the defined action criterion.",
                'Sampled monitoring sheet contained a borderline/out-of-trend entry without documented evaluation, correction or supervisor review.',
                'QA reviewed the affected batch/shift record, documented evaluation, and confirmed product disposition was acceptable.',
                'Monitoring personnel understood the limit but the form did not prompt action recording for near-limit or abnormal trend situations.',
                'Revise monitoring form to require action/comment for near-limit readings, retrain monitoring staff, and review first month of records.',
                'Updated CCP/OPRP monitoring form, training attendance and QA review of subsequent monitoring records.',
                'Auditor verified revised form use and sampled subsequent monitoring records for action recording.',
                $severityText
            );
        }

        if (str_contains($context, '14001') || str_contains($context, 'environment') || str_contains($context, 'aspect') || str_contains($context, 'compliance')) {
            return $this->ncrPayload(
                $classification,
                "Environmental control evidence for {$process} was incomplete; the sampled operational inspection did not record follow-up for an identified observation.",
                'Environmental inspection record identified an issue, but corrective follow-up, responsible person and closure evidence were not recorded.',
                'The observation was corrected and the inspection record was updated with action owner and closure evidence.',
                'Inspection form did not make action owner, due date and closure evidence mandatory for environmental observations.',
                'Revise the inspection form, brief responsible supervisors and review open environmental actions weekly until closed.',
                'Updated inspection form, action tracker and closed environmental observation evidence.',
                'Auditor reviewed the updated tracker and verified closure evidence for the sampled observation.',
                $severityText
            );
        }

        if (str_contains($context, '45001') || str_contains($context, 'safety') || str_contains($context, 'hazard') || str_contains($context, 'incident')) {
            return $this->ncrPayload(
                $classification,
                "OH&S risk control record for {$process} did not show verification that assigned controls remained effective after a process change.",
                'Risk assessment/process change sample showed control updates, but no documented post-change verification or worker consultation evidence.',
                'Responsible supervisor completed post-change verification and recorded consultation with affected workers.',
                'Change review checklist did not explicitly require post-change effectiveness confirmation for OH&S controls.',
                'Update change review checklist, brief supervisors, and add post-change control verification to the monthly HSE review.',
                'Updated change checklist, consultation record and HSE review evidence.',
                'Auditor reviewed the revised checklist and sampled one completed post-change verification record.',
                $severityText
            );
        }

        return $this->ncrPayload(
            $classification,
            "Sampled evidence for {$title} in {$process} was incomplete and did not fully demonstrate implementation of the defined control.",
            'Record/interview sample showed the activity was performed, but required review, approval or follow-up evidence was missing for one selected case.',
            'The affected record was completed/reviewed by the process owner and the sample was checked for any similar missing entries.',
            'Responsibility for record review and escalation was not clear enough in the local process arrangement.',
            'Clarify review responsibility, update the checklist/procedure, brief responsible personnel and verify implementation through next internal audit sample.',
            'Updated checklist/procedure, briefing record and internal audit follow-up sample.',
            'Auditor verified the corrected record and sampled one additional case for complete review evidence.',
            $severityText
        );
    }

    private function foodNcrTheme(string $context, int $index): string
    {
        if (str_contains($context, 'trace') || str_contains($context, 'recall') || str_contains($context, 'withdrawal')) {
            return 'traceability';
        }

        if (str_contains($context, 'clean') || str_contains($context, 'sanitation') || str_contains($context, 'prp') || str_contains($context, 'hygiene') || str_contains($context, 'storage')) {
            return 'prp';
        }

        if (str_contains($context, 'supplier') || str_contains($context, 'purchase') || str_contains($context, 'external')) {
            return 'supplier';
        }

        if (str_contains($context, 'release') || str_contains($context, 'allergen') || str_contains($context, 'label')) {
            return 'release';
        }

        $themes = ['traceability', 'prp', 'monitoring', 'supplier', 'release'];

        return $themes[($index - 1) % count($themes)];
    }

    private function ncrPayload(
        string $classification,
        string $finding,
        string $objectiveEvidence,
        string $correction,
        string $rootCause,
        string $correctiveAction,
        string $evidenceReference,
        string $verification,
        string $effectiveness
    ): array {
        return [
            'finding' => ucfirst($classification) . ' nonconformity: ' . $finding,
            'objective_evidence' => $objectiveEvidence,
            'correction' => $correction,
            'root_cause' => $rootCause,
            'corrective_action' => $correctiveAction,
            'preventive_action' => 'Apply the revised control to similar records/processes and trend recurrence during internal audit or management review.',
            'evidence_reference' => $evidenceReference,
            'verification' => $verification,
            'effectiveness' => $effectiveness,
            'five_why' => [
                'Why 1' => 'Required evidence was missing or incomplete in the sampled record.',
                'Why 2' => 'Review did not detect the gap before the audit sample.',
                'Why 3' => 'The local checklist/procedure did not define the verification point clearly enough.',
            ],
            'fishbone' => [
                'People' => 'Role awareness or handover weakness',
                'Method' => 'Checklist/procedure lacked a clear verification step',
                'Measurement' => 'Review frequency or escalation criteria were weak',
            ],
        ];
    }

    private function findingText(string $classification, string $clauseTitle, array $scenario): string
    {
        if ($classification === 'ofi') {
            return 'Opportunity for improvement: strengthen trend analysis for ' . $clauseTitle . ' within ' . $scenario['processes'][0] . '.';
        }

        return ucfirst($classification) . ' nonconformity: sampled records did not fully demonstrate effective implementation of ' . $clauseTitle . ' for ' . $scenario['processes'][0] . '.';
    }

    private function answerForQuestion(string $question, array $scenario): string
    {
        $lower = strtolower($question);
        if (str_contains($lower, 'employee')) {
            return (string) $scenario['employees'];
        }
        if (str_contains($lower, 'scope')) {
            return $scenario['scope'];
        }
        if (str_contains($lower, 'site')) {
            return (string) $scenario['sites'];
        }
        if (str_contains($lower, 'standard')) {
            return implode(', ', $scenario['standards']);
        }
        if (str_contains($lower, 'contact')) {
            return $scenario['contact'];
        }

        return 'Demo response: information provided and verified during application review.';
    }

    private function lookup(string $table, string $key): array
    {
        $rows = $this->db->table($table)->get()->getResultArray();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row[$key]] = $row;
        }

        return $map;
    }

    private function roleId(string $code): int
    {
        $role = $this->db->table('roles')
            ->where('tenant_id', $this->tenantId)
            ->where('code', $code)
            ->get(1)
            ->getRowArray();

        return (int) $role['id'];
    }

    private function ids(string $table, string $column, array $values): array
    {
        if ($values === []) {
            return [];
        }

        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->table($table)->select('id')->whereIn($column, $values)->get()->getResultArray()
        );
    }

    private function deleteWhereIn(string $table, string $column, array $values): void
    {
        if ($values === []) {
            return;
        }

        $this->db->table($table)->whereIn($column, $values)->delete();
    }

    private function plus(DateTimeImmutable $date, int $days): string
    {
        return $date->add(new DateInterval('P' . $days . 'D'))->format('Y-m-d');
    }

    private function plusDate(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        return $date->add(new DateInterval('P' . $days . 'D'));
    }

    private function endDate(DateTimeImmutable $start, float $duration): DateTimeImmutable
    {
        return $start->add(new DateInterval('P' . max(0, (int) ceil($duration) - 1) . 'D'));
    }

    private function dateTime(string $date, string $time): string
    {
        return $date . ' ' . $time;
    }
}

<?php

namespace App\Database\Seeds;

use App\Services\CertificationApplicationDefaults;
use App\Services\SmartAuditContentEngine;
use CodeIgniter\Database\Seeder;

class RepairCanonicalDemoDataSeeder extends Seeder
{
    private const CLIENTS = [
        'Demo 2024 Riyadh Central Catering LLC',
        'Demo 2024 Fresh Valley Dairy Factory',
        'Demo 2024 Gulf Ready Meals Industries',
    ];

    public function run(): void
    {
        $this->call(ApplicationQuestionLibrarySeeder::class);
        $defaults = new CertificationApplicationDefaults();

        foreach (self::CLIENTS as $company) {
            $client = $this->db->table('clients')->where('tenant_id', 1)->where('company', $company)->get(1)->getRowArray();
            if ($client === null) {
                continue;
            }

            $application = $this->db->table('certification_applications')
                ->where('tenant_id', 1)
                ->where('client_id', (int) $client['id'])
                ->orderBy('id', 'DESC')
                ->get(1)
                ->getRowArray();
            if ($application === null) {
                continue;
            }

            $standards = $this->db->table('application_selected_standards')
                ->select('standard_id, standard_code')
                ->where('application_id', (int) $application['id'])
                ->get()
                ->getResultArray();
            $this->repairApplication($application, $client, $standards, $defaults);
            $this->repairApplicationReview((int) $client['id'], (int) $application['id']);
            $this->repairLinkedNcrPackages($client);
            $this->repairAuditResponses((int) $client['id']);
            $this->repairTechnicalReviewsAndDecisions((int) $client['id']);
        }
    }

    private function repairLinkedNcrPackages(array $client): void
    {
        $engine = new SmartAuditContentEngine();
        $rows = $this->db->table('ncrs ncr')
            ->select('ncr.*, requirements.requirement_code, requirements.title, requirements.audit_question, requirements.evidence_guidance, events.event_type')
            ->join('audit_requirement_responses responses', 'responses.id = ncr.audit_requirement_response_id')
            ->join('integrated_audit_requirements requirements', 'requirements.id = responses.audit_requirement_id')
            ->join('report_drafts reports', 'reports.id = responses.report_draft_id')
            ->join('audit_events events', 'events.id = reports.audit_event_id')
            ->join('audit_programs programs', 'programs.id = events.audit_program_id')
            ->where('programs.client_id', (int) $client['id'])
            ->orderBy('ncr.id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as $index => $ncr) {
            $snapshots = $this->db->table('audit_response_clause_snapshots')
                ->where('audit_requirement_response_id', (int) $ncr['audit_requirement_response_id'])
                ->orderBy('mapping_role', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $primary = $snapshots[0] ?? [];
            $clause = [
                'integrated_requirement_id' => (int) $ncr['audit_requirement_response_id'],
                'requirement_code' => (string) $ncr['requirement_code'],
                'standard_code' => (string) ($primary['standard_code_snapshot'] ?? ''),
                'clause_number' => (string) ($primary['clause_reference_snapshot'] ?? $ncr['requirement_code']),
                'clause_title' => (string) $ncr['title'],
                'requirement' => (string) $ncr['audit_question'],
                'evidence_guidance' => (string) $ncr['evidence_guidance'],
            ];
            $package = $engine->ncrCapaPackage(
                $client,
                ['event_type' => (string) $ncr['event_type']],
                $clause,
                (string) $ncr['classification'],
                $index + 1
            );
            $ncrNumber = preg_replace('/^NCR-AUTO-/', 'NCR-', (string) $ncr['ncr_number']);

            $this->db->table('ncrs')->where('id', (int) $ncr['id'])->update([
                'ncr_number' => $ncrNumber,
                'requirement' => $package['requirement'],
                'finding' => $package['finding'],
                'objective_evidence' => $package['objective_evidence'],
                'correction' => $package['correction'],
                'root_cause' => $package['root_cause'],
                'corrective_action' => $package['corrective_action'],
                'verification' => $package['verification'],
                'closure_notes' => $package['closure_notes'],
            ]);
            $this->db->table('audit_requirement_responses')->where('id', (int) $ncr['audit_requirement_response_id'])->update([
                'finding_type' => (string) $ncr['classification'],
                'response_text' => $package['finding'],
            ]);
            $this->db->table('capas')->where('ncr_id', (int) $ncr['id'])->update([
                'capa_number' => str_replace('NCR-', 'CAPA-', $ncrNumber),
                'issue' => $package['finding'],
                'immediate_correction' => $package['correction'],
                'root_cause' => $package['root_cause'],
                'corrective_action' => $package['corrective_action'],
                'preventive_action' => $package['preventive_action'],
                'evidence_reference' => $package['evidence_reference'],
                'verification' => $package['verification'],
                'effectiveness' => $package['effectiveness'],
                'closure_notes' => $package['closure_notes'],
            ]);
        }
    }

    private function repairAuditResponses(int $clientId): void
    {
        if (! $this->db->tableExists('audit_requirement_responses')) {
            return;
        }

        $responses = $this->db->table('audit_requirement_responses responses')
            ->select('responses.id, requirements.title, events.event_type')
            ->join('integrated_audit_requirements requirements', 'requirements.id = responses.audit_requirement_id')
            ->join('report_drafts reports', 'reports.id = responses.report_draft_id')
            ->join('audit_events events', 'events.id = reports.audit_event_id')
            ->join('audit_programs programs', 'programs.id = events.audit_program_id')
            ->where('programs.client_id', $clientId)
            ->get()
            ->getResultArray();

        foreach ($responses as $response) {
            $snapshots = $this->db->table('audit_response_clause_snapshots')
                ->select('standard_code_snapshot, clause_reference_snapshot')
                ->where('audit_requirement_response_id', (int) $response['id'])
                ->orderBy('standard_code_snapshot', 'ASC')
                ->orderBy('clause_reference_snapshot', 'ASC')
                ->get()
                ->getResultArray();
            $criteria = implode(', ', array_map(
                static fn (array $row): string => trim((string) $row['standard_code_snapshot'] . ' ' . (string) $row['clause_reference_snapshot']),
                $snapshots
            ));
            $criteria = $criteria !== '' ? $criteria : 'the applicable audit criteria';
            $stage = strtolower(preg_replace('/\bstage\s*(\d)\b/i', 'stage $1', str_replace('_', ' ', (string) $response['event_type'])));
            $text = 'Conformity recorded. Controls relating to ' . strtolower((string) $response['title'])
                . ' were reviewed against ' . $criteria . ' during ' . $stage . '. '
                . 'The recorded implementation and sampled objective evidence support conformity for this requirement.';

            $linkedNcr = $this->db->table('ncrs')
                ->select('classification, finding')
                ->where('audit_requirement_response_id', (int) $response['id'])
                ->orderBy('id', 'DESC')
                ->get(1)
                ->getRowArray();

            $this->db->table('audit_requirement_responses')->where('id', (int) $response['id'])->update([
                'response_text' => $linkedNcr === null ? $text : (string) $linkedNcr['finding'],
                'finding_type' => $linkedNcr === null ? 'conformity' : (string) $linkedNcr['classification'],
            ]);
        }
    }

    private function repairApplication(array $application, array $client, array $standards, CertificationApplicationDefaults $defaults): void
    {
        $applicationId = (int) $application['id'];
        $oldRows = $this->db->table('application_questions questions')
            ->select('questions.question_key, questions.question_text, application_answers.answer_text')
            ->join('application_answers', 'application_answers.application_question_id = questions.id', 'left')
            ->where('questions.application_id', $applicationId)
            ->get()
            ->getResultArray();
        $oldByKey = [];
        $oldByText = [];
        foreach ($oldRows as $row) {
            $answer = trim((string) ($row['answer_text'] ?? ''));
            if ($answer !== '') {
                $oldByKey[(string) $row['question_key']] = $answer;
                $oldByText[(string) $row['question_text']] = $answer;
            }
        }

        $this->db->table('application_questions')->where('application_id', $applicationId)->delete();
        $selectedCodes = array_values(array_column($standards, 'standard_code'));
        $library = $this->db->table('question_library')->where('active', 1)->orderBy('display_order', 'ASC')->get()->getResultArray();
        $order = 1;
        foreach ($library as $question) {
            $section = (string) $question['section'];
            if ((string) $question['question_type'] === 'file'
                || in_array($section, ['Supporting Documents', 'Declaration', 'HACCP Specific Questions'], true)
                || str_ends_with(strtoupper(trim($section)), 'SPECIFIC QUESTIONS')) {
                continue;
            }
            $applicable = json_decode((string) $question['applicable_standards'], true) ?: [];
            if (! $this->isApplicable($applicable, $selectedCodes)) {
                continue;
            }

            $questionPayload = [
                'application_id' => $applicationId,
                'question_library_id' => (int) $question['id'],
                'question_key' => (string) $question['question_key'],
                'question_text' => (string) $question['question_text'],
                'question_type' => (string) $question['question_type'],
                'section' => $section,
                'display_order' => $order++,
                'mandatory' => (int) $question['mandatory'],
                'validation_rules' => $question['validation_rules'],
                'help_text' => $question['help_text'],
                'standard_codes' => json_encode($applicable, JSON_THROW_ON_ERROR),
            ];
            $this->db->table('application_questions')->insert($questionPayload);
            $questionId = (int) $this->db->insertID();
            $key = (string) $question['question_key'];
            $scopeAwareKeys = [
                'legal_statutory_requirements', 'product_process_risks', 'technical_issues',
                'safety_requirements', 'technological_regulatory_context', 'scope_of_certification',
                'products', 'services', 'processes', 'outsourced_processes', 'haccp_plans_processes',
            ];
            $seededAdministrativeKeys = [
                'previous_certification', 'certification_body', 'certificate_number',
                'transfer_certification', 'certification_status', 'audit_reports_available',
                'nc_status', 'customer_complaints', 'preassessment_required',
            ];
            $clientRecordKeys = [
                'company_name', 'legal_name', 'address', 'country', 'city', 'website',
                'contact_person', 'designation', 'email', 'phone',
            ];
            $controlledDefault = $defaults->applicationAnswer($key, $client, $standards);
            $fallback = $this->fallbackAnswer($key, $client, $application, $standards);
            $answer = in_array($key, $scopeAwareKeys, true) && $controlledDefault !== null
                ? $controlledDefault
                : (in_array($key, [...$seededAdministrativeKeys, ...$clientRecordKeys], true)
                    ? $fallback
                : ($oldByKey[$key]
                    ?? $oldByText[(string) $question['question_text']]
                    ?? $controlledDefault
                    ?? $fallback));
            $this->db->table('application_answers')->insert([
                'application_id' => $applicationId,
                'application_question_id' => $questionId,
                'question_library_id' => (int) $question['id'],
                'answer_text' => $answer,
                'answered_by' => (int) ($application['created_by'] ?? 1),
                'answered_at' => (string) ($application['submitted_at'] ?? date('Y-m-d H:i:s')),
            ]);
        }
    }

    private function repairApplicationReview(int $clientId, int $applicationId): void
    {
        $answers = $this->answersByKey($applicationId);
        $review = $this->db->table('application_reviews')->where('client_id', $clientId)->orderBy('id', 'DESC')->get(1)->getRowArray();
        if ($review === null) {
            return;
        }
        $payload = json_decode((string) ($review['review_payload'] ?? '{}'), true) ?: [];
        $map = [
            'language_of_audit' => 'communication_language',
            'certification_employee_count' => 'effective_employees',
            'haccp_plans_processes' => 'haccp_plans_processes',
            'number_of_shifts' => 'shifts_auditing',
            'seasonal_operations' => 'seasonal_activity',
            'legal_statutory_requirements' => 'legal_requirements',
            'product_process_risks' => 'product_process_risks',
            'technical_issues' => 'technical_issues',
            'safety_requirements' => 'safety_requirements',
            'technological_regulatory_context' => 'technological_regulatory_context',
            'outsourced_processes' => 'outsourced_activity_details',
            'incident_accident_history' => 'incident',
        ];
        foreach ($map as $applicationKey => $reviewKey) {
            if (isset($answers[$applicationKey]) && trim($answers[$applicationKey]) !== '') {
                $payload[$reviewKey] = $answers[$applicationKey];
            }
        }
        $this->db->table('application_reviews')->where('id', (int) $review['id'])->update([
            'review_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    private function repairTechnicalReviewsAndDecisions(int $clientId): void
    {
        $program = $this->db->table('audit_programs')->where('client_id', $clientId)->orderBy('id', 'DESC')->get(1)->getRowArray();
        if ($program === null) {
            return;
        }
        $events = $this->db->table('audit_events')->select('id, audit_number, event_type')->where('audit_program_id', (int) $program['id'])->get()->getResultArray();
        foreach ($events as $event) {
            $review = $this->db->table('technical_reviews')->where('audit_event_id', (int) $event['id'])->orderBy('id', 'DESC')->get(1)->getRowArray();
            if ($review === null) {
                continue;
            }
            $reviewPayload = json_decode((string) ($review['checklist_payload'] ?? '{}'), true) ?: [];
            $reviewPayload['checklist_rows'] = $reviewPayload['checklist_rows'] ?? $this->technicalChecklistRows();
            $reviewPayload['audit_result'] ??= 'Audit file reviewed and found suitable for independent certification decision.';
            $reviewPayload['client_management_system_review'] ??= 'Application, contract, audit programme, audit report, objective evidence and NCR/CAPA status reviewed for consistency.';
            $reviewPayload['outstanding_items'] ??= 'No outstanding item preventing the recorded decision.';
            foreach (['review_notes', 'audit_evidence_summary'] as $textKey) {
                if (! empty($reviewPayload[$textKey])) {
                    $reviewPayload[$textKey] = str_replace(['Stage1', 'Stage2', '..'], ['Stage 1', 'Stage 2', '.'], (string) $reviewPayload[$textKey]);
                }
            }
            $this->db->table('technical_reviews')->where('id', (int) $review['id'])->update([
                'checklist_payload' => json_encode($reviewPayload, JSON_THROW_ON_ERROR),
            ]);

            $decision = $this->db->table('certification_decisions')->where('technical_review_id', (int) $review['id'])->orderBy('id', 'DESC')->get(1)->getRowArray();
            if ($decision === null) {
                continue;
            }
            $decisionPayload = json_decode((string) ($decision['decision_payload'] ?? '{}'), true) ?: [];
            $decisionPayload['checklist_rows'] = $decisionPayload['checklist_rows'] ?? $this->decisionChecklistRows();
            $decisionPayload['application_id'] ??= 'Application linked in the controlled client file';
            $decisionPayload['declaration_text'] ??= 'The decision was made independently from the audit team after review of the complete certification file.';
            $decisionPayload['declaration_confirmed'] = true;
            if (! empty($decisionPayload['decision_basis'])) {
                $decisionPayload['decision_basis'] = str_replace(['Stage1', 'Stage2', '..'], ['Stage 1', 'Stage 2', '.'], (string) $decisionPayload['decision_basis']);
            }
            $decisionValue = (string) $decision['decision'] === 'grant' ? 'granted' : (string) $decision['decision'];
            $this->db->table('certification_decisions')->where('id', (int) $decision['id'])->update([
                'decision' => $decisionValue,
                'reason' => str_replace(['Stage1', 'Stage2', '..'], ['Stage 1', 'Stage 2', '.'], (string) ($decision['reason'] ?? '')),
                'decision_payload' => json_encode($decisionPayload, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    private function answersByKey(int $applicationId): array
    {
        $rows = $this->db->table('application_answers answers')
            ->select('questions.question_key, answers.answer_text')
            ->join('application_questions questions', 'questions.id = answers.application_question_id')
            ->where('answers.application_id', $applicationId)
            ->get()
            ->getResultArray();
        $answers = [];
        foreach ($rows as $row) {
            $answers[(string) $row['question_key']] = (string) ($row['answer_text'] ?? '');
        }

        return $answers;
    }

    private function isApplicable(array $applicable, array $selected): bool
    {
        foreach ($applicable as $candidate) {
            if (strtoupper((string) $candidate) === 'COMMON') {
                return true;
            }
            foreach ($selected as $standard) {
                $candidateCode = strtoupper((string) $candidate);
                $selectedCode = strtoupper((string) $standard);
                if ($candidateCode === $selectedCode || str_starts_with($selectedCode, $candidateCode . ':')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function fallbackAnswer(string $key, array $client, array $application, array $standards): string
    {
        $values = [
            'company_name' => $client['company'] ?? '',
            'legal_name' => $client['legal_name'] ?? $client['company'] ?? '',
            'commercial_registration_number' => 'To be confirmed by client',
            'vat_number' => 'To be confirmed by client',
            'license_number' => 'To be confirmed by client',
            'address' => $client['address'] ?? '',
            'country' => trim((string) ($client['country'] ?? '')) !== '' ? $client['country'] : 'Saudi Arabia',
            'city' => trim((string) ($client['city'] ?? '')) !== '' ? $client['city'] : $this->cityFromAddress((string) ($client['address'] ?? '')),
            'website' => trim((string) ($client['website'] ?? '')) !== '' ? $client['website'] : 'Not provided',
            'contact_person' => $client['contact_person'] ?? '',
            'designation' => $client['designation'] ?? '',
            'email' => $client['email'] ?? '',
            'phone' => $client['phone'] ?? '',
            'mobile' => $client['phone'] ?? '',
            'employee_count' => (string) ($client['employee_count'] ?? 1),
            'certification_employee_count' => (string) ($client['employee_count'] ?? 1),
            'permanent_employees' => (string) ($client['employee_count'] ?? 1),
            'temporary_employees' => '0',
            'contract_workers' => '0',
            'number_of_shifts' => '1',
            'working_hours' => '08:00 to 17:00',
            'seasonal_operations' => 'No',
            'number_of_sites' => (string) ($client['number_of_sites'] ?? 1),
            'head_office' => $client['address'] ?? '',
            'branches' => 'Not applicable',
            'remote_locations' => 'Not applicable',
            'consultant_used' => 'No',
            'integrated_management_system' => count($standards) > 1 ? 'Partially integrated' : 'Not Applicable',
            'incident_accident_history' => 'No incident or accident reported at application stage.',
            'previous_certification' => 'No',
            'certification_body' => 'Not applicable',
            'certificate_number' => 'Not applicable',
            'transfer_certification' => 'No',
            'certification_status' => 'Not applicable',
            'audit_reports_available' => 'No',
            'nc_status' => 'Not applicable',
            'customer_complaints' => 'No complaint reported at application stage.',
            'preassessment_required' => 'No',
            'applicant_declaration' => $application['declaration_name'] ?? $client['contact_person'] ?? '',
        ];

        return (string) ($values[$key] ?? 'Information recorded and verified during application review.');
    }

    private function cityFromAddress(string $address): string
    {
        foreach (['Riyadh', 'Al Kharj', 'Jeddah', 'Dammam', 'Khobar', 'Makkah', 'Madinah'] as $city) {
            if (stripos($address, $city) !== false) {
                return $city;
            }
        }

        return 'Saudi Arabia';
    }

    private function technicalChecklistRows(): array
    {
        return [
            ['group' => 'File completeness', 'ref' => 'TR-01', 'action_by' => 'Technical Reviewer', 'requirement' => 'Application, proposal, contract and audit programme are consistent.', 'result' => 'Conforming', 'evidence' => 'Controlled client file records reviewed.'],
            ['group' => 'Audit delivery', 'ref' => 'TR-02', 'action_by' => 'Technical Reviewer', 'requirement' => 'Audit team competence, duration, plan and report coverage are adequate.', 'result' => 'Conforming', 'evidence' => 'Appointments, competence, audit plan and report reviewed.'],
            ['group' => 'Findings', 'ref' => 'TR-03', 'action_by' => 'Technical Reviewer', 'requirement' => 'NCR/CAPA records are complete and closure evidence is adequate.', 'result' => 'Conforming', 'evidence' => 'NCR and CAPA records reviewed against audit evidence.'],
            ['group' => 'Recommendation', 'ref' => 'TR-04', 'action_by' => 'Technical Reviewer', 'requirement' => 'The audit conclusion supports an independent certification decision.', 'result' => 'Conforming', 'evidence' => 'Audit conclusion and complete file found suitable for decision.'],
        ];
    }

    private function decisionChecklistRows(): array
    {
        return [
            ['group' => 'Independence', 'ref' => 'DM-01', 'requirement' => 'Decision maker is independent from the audit team.', 'result' => 'Conforming', 'evidence' => 'Personnel assignment and conflict controls reviewed.'],
            ['group' => 'Technical review', 'ref' => 'DM-02', 'requirement' => 'Technical Review is complete and supports the recommendation.', 'result' => 'Conforming', 'evidence' => 'Approved Technical Review record checked.'],
            ['group' => 'Certification basis', 'ref' => 'DM-03', 'requirement' => 'Scope, standard, audit evidence and NCR/CAPA status support the decision.', 'result' => 'Conforming', 'evidence' => 'Complete certification file and closure status reviewed.'],
        ];
    }
}

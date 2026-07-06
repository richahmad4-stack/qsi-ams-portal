<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

class CycleAutomationService
{
    private BaseConnection $db;
    private AuditDurationService $duration;
    private AuditLogger $logger;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->duration = new AuditDurationService();
        $this->logger = new AuditLogger();
    }

    public function preview(array $input, int $tenantId, int $userId): array
    {
        $input = $this->normalizeInput($input);
        $standards = $this->standardsByIds($input['standard_ids']);
        $cycle = $this->cycleDates($input['certificate_issue_date'], $input['certificate_expiry_date'] ?: null);
        $timeline = $this->timeline($cycle['issue']);
        $duration = $this->duration->calculateApplicationReview($this->clientShape($input), $standards, [
            'effective_employees' => $input['employee_count'],
            'standards_text' => implode(', ', array_column($standards, 'code')),
            'audit_category' => $input['food_category_id'] ?: $input['iaf_code_id'],
            'risk_classification' => $input['risk_category'],
        ]);
        $events = $this->eventPlan($cycle, $timeline, $duration);
        $assignments = $this->assignStaff($tenantId, $input, $standards);
        $warnings = $this->warnings($input, $standards, $cycle, $timeline, $events, $assignments);

        return [
            'input' => $input,
            'standards' => $standards,
            'cycle' => $cycle,
            'timeline' => $timeline,
            'duration' => $duration,
            'events' => $events,
            'assignments' => $assignments,
            'warnings' => $warnings,
            'can_generate' => ! array_filter($warnings, static fn (array $warning): bool => ($warning['level'] ?? '') === 'critical'),
            'previewed_by' => $userId,
            'previewed_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function generate(array $preview, int $tenantId, int $userId): array
    {
        if (! ($preview['can_generate'] ?? false)) {
            throw new RuntimeException('Automation cannot generate while critical preview warnings exist.');
        }

        $this->db->transStart();

        $input = $preview['input'];
        $clientId = $this->createClient($tenantId, $userId, $input, $preview['cycle']);
        $this->createClientRelatedRecords($clientId, $input, $preview['standards']);
        $applicationId = $this->createApplication($tenantId, $clientId, $userId, $input, $preview);
        $reviewId = $this->createApplicationReview($clientId, $applicationId, $preview);
        $proposalId = $this->createProposal($tenantId, $clientId, $reviewId, $userId, $preview);
        $contractId = $this->createContract($tenantId, $clientId, $proposalId, $userId, $preview);
        $this->createInvoiceAndPayment($tenantId, $clientId, $preview);
        $programId = $this->createAuditProgram($tenantId, $clientId, $contractId, $userId, $preview);
        $eventIds = $this->createEventsAndFiles($tenantId, $clientId, $programId, $userId, $preview);
        $certificateIds = $this->createCertificates($tenantId, $clientId, $eventIds['initial_stage2']['decision_id'] ?? null, $preview);
        $feedbackId = $this->createFeedback($tenantId, $clientId, $programId, $certificateIds[0] ?? null, $preview);
        $runId = $this->recordRun($tenantId, $clientId, $userId, $preview, [
            'client_id' => $clientId,
            'application_id' => $applicationId,
            'review_id' => $reviewId,
            'proposal_id' => $proposalId,
            'contract_id' => $contractId,
            'program_id' => $programId,
            'events' => $eventIds,
            'certificate_ids' => $certificateIds,
            'feedback_id' => $feedbackId,
        ]);

        $this->logAutomation($tenantId, $userId, $clientId, $runId, $preview);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Cycle automation failed during database generation.');
        }

        return [
            'run_id' => $runId,
            'client_id' => $clientId,
            'program_id' => $programId,
            'event_ids' => array_column($eventIds, 'event_id'),
            'certificate_ids' => $certificateIds,
        ];
    }

    public function standards(): array
    {
        return $this->db->table('standards')->where('active', 1)->orderBy('code')->get()->getResultArray();
    }

    public function iafCodes(): array
    {
        return $this->db->table('iaf_codes')->where('active', 1)->orderBy('code')->get()->getResultArray();
    }

    public function foodCategories(): array
    {
        return $this->db->table('food_chain_categories')->where('active', 1)->orderBy('code')->get()->getResultArray();
    }

    public function medicalCategories(): array
    {
        return $this->db->table('medical_device_categories')->where('active', 1)->orderBy('code')->get()->getResultArray();
    }

    private function normalizeInput(array $input): array
    {
        $standardIds = array_values(array_filter(array_map('intval', (array) ($input['standard_ids'] ?? []))));
        $issueDate = trim((string) ($input['certificate_issue_date'] ?? ''));

        if ($issueDate === '') {
            throw new RuntimeException('Certificate issue date is required.');
        }

        return [
            'client_name' => trim((string) ($input['client_name'] ?? '')),
            'client_address' => trim((string) ($input['client_address'] ?? '')),
            'contact_person' => trim((string) ($input['contact_person'] ?? '')),
            'designation' => trim((string) ($input['designation'] ?? 'Management Representative')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'standard_ids' => $standardIds,
            'scope' => trim((string) ($input['scope'] ?? '')),
            'iaf_code_id' => $this->intOrNull($input['iaf_code_id'] ?? null),
            'food_category_id' => $this->intOrNull($input['food_category_id'] ?? null),
            'medical_category_id' => $this->intOrNull($input['medical_category_id'] ?? null),
            'employee_count' => max(1, (int) ($input['employee_count'] ?? 1)),
            'number_of_sites' => max(1, (int) ($input['number_of_sites'] ?? 1)),
            'certificate_issue_date' => (new DateTimeImmutable($issueDate))->format('Y-m-d'),
            'certificate_expiry_date' => trim((string) ($input['certificate_expiry_date'] ?? '')),
            'certification_status' => trim((string) ($input['certification_status'] ?? 'certified')) ?: 'certified',
            'current_cycle_stage' => trim((string) ($input['current_cycle_stage'] ?? 'auto')) ?: 'auto',
            'risk_category' => trim((string) ($input['risk_category'] ?? 'medium')) ?: 'medium',
            'special_notes' => trim((string) ($input['special_notes'] ?? '')),
            'ncr_mode' => trim((string) ($input['ncr_mode'] ?? 'sample_minor')) ?: 'sample_minor',
        ];
    }

    private function standardsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->db->table('standards')
            ->whereIn('id', $ids)
            ->where('active', 1)
            ->orderBy('code')
            ->get()
            ->getResultArray();
    }

    private function cycleDates(string $issueDate, ?string $expiryDate): array
    {
        $issue = new DateTimeImmutable($issueDate);
        $expiry = $expiryDate !== null && trim($expiryDate) !== ''
            ? new DateTimeImmutable($expiryDate)
            : $issue->add(new DateInterval('P3Y'))->sub(new DateInterval('P1D'));

        return [
            'issue' => $issue->format('Y-m-d'),
            'surveillance1' => $issue->add(new DateInterval('P1Y'))->sub(new DateInterval('P1D'))->format('Y-m-d'),
            'surveillance2' => $issue->add(new DateInterval('P2Y'))->sub(new DateInterval('P1D'))->format('Y-m-d'),
            'expiry' => $expiry->format('Y-m-d'),
        ];
    }

    private function timeline(string $issueDate): array
    {
        $issue = new DateTimeImmutable($issueDate);

        return [
            'application_submitted' => $issue->sub(new DateInterval('P46D'))->format('Y-m-d'),
            'application_review' => $issue->sub(new DateInterval('P44D'))->format('Y-m-d'),
            'proposal_generated' => $issue->sub(new DateInterval('P42D'))->format('Y-m-d'),
            'proposal_sent' => $issue->sub(new DateInterval('P41D'))->format('Y-m-d'),
            'proposal_accepted' => $issue->sub(new DateInterval('P40D'))->format('Y-m-d'),
            'contract_signed' => $issue->sub(new DateInterval('P39D'))->format('Y-m-d'),
            'audit_program' => $issue->sub(new DateInterval('P38D'))->format('Y-m-d'),
            'appointment' => $issue->sub(new DateInterval('P37D'))->format('Y-m-d'),
            'stage1_plan' => $issue->sub(new DateInterval('P36D'))->format('Y-m-d'),
            'stage1_audit' => $issue->sub(new DateInterval('P31D'))->format('Y-m-d'),
            'stage1_report' => $issue->sub(new DateInterval('P30D'))->format('Y-m-d'),
            'stage1_capa_close' => $issue->sub(new DateInterval('P27D'))->format('Y-m-d'),
            'stage1_review' => $issue->sub(new DateInterval('P25D'))->format('Y-m-d'),
            'stage2_plan' => $issue->sub(new DateInterval('P15D'))->format('Y-m-d'),
            'stage2_audit' => $issue->sub(new DateInterval('P10D'))->format('Y-m-d'),
            'stage2_report' => $issue->sub(new DateInterval('P9D'))->format('Y-m-d'),
            'stage2_capa_close' => $issue->sub(new DateInterval('P5D'))->format('Y-m-d'),
            'final_review' => $issue->sub(new DateInterval('P3D'))->format('Y-m-d'),
            'decision' => $issue->sub(new DateInterval('P2D'))->format('Y-m-d'),
            'certificate_issue' => $issue->format('Y-m-d'),
        ];
    }

    private function eventPlan(array $cycle, array $timeline, array $duration): array
    {
        return [
            'initial_stage1' => $this->eventRow('initial_stage1', $timeline['stage1_audit'], (float) $duration['stage1_days']),
            'initial_stage2' => $this->eventRow('initial_stage2', $timeline['stage2_audit'], (float) $duration['stage2_days']),
            'surveillance1' => $this->eventRow('surveillance1', (new DateTimeImmutable($cycle['surveillance1']))->sub(new DateInterval('P12D'))->format('Y-m-d'), (float) $duration['surveillance1_days']),
            'surveillance2' => $this->eventRow('surveillance2', (new DateTimeImmutable($cycle['surveillance2']))->sub(new DateInterval('P12D'))->format('Y-m-d'), (float) $duration['surveillance2_days']),
            'recertification' => $this->eventRow('recertification', (new DateTimeImmutable($cycle['expiry']))->sub(new DateInterval('P90D'))->format('Y-m-d'), (float) $duration['recertification_days']),
        ];
    }

    private function eventRow(string $type, string $startDate, float $days): array
    {
        $start = new DateTimeImmutable($startDate);
        $end = $this->duration->endDateForDuration($start, max(0.50, $days));
        $today = new DateTimeImmutable(date('Y-m-d'));
        $status = $end < $today ? 'completed' : ($start <= $today && $end >= $today ? 'in_progress' : 'planned');

        return [
            'type' => $type,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'days' => $days,
            'status' => $status,
        ];
    }

    private function assignStaff(int $tenantId, array $input, array $standards): array
    {
        $requirements = [
            'lead_auditor' => ['lead_auditor'],
            'auditor' => ['auditor'],
            'technical_reviewer' => ['technical_reviewer', 'technical_manager'],
            'decision_maker' => ['certification_decision_maker'],
            'quality_manager' => ['quality_manager'],
            'certification_manager' => ['certification_manager', 'administrator'],
            'general_manager' => ['general_manager'],
            'finance' => ['finance'],
        ];
        $assignments = [];
        $blocked = [];

        foreach ($requirements as $key => $roles) {
            $person = $this->eligiblePerson($tenantId, $roles, $input, $standards, $blocked);
            $assignments[$key] = $person;
            if ($person !== null && in_array($key, ['lead_auditor', 'auditor', 'technical_reviewer', 'decision_maker'], true)) {
                $blocked[] = (int) $person['id'];
            }
        }

        return $assignments;
    }

    private function eligiblePerson(int $tenantId, array $roleCodes, array $input, array $standards, array $blocked): ?array
    {
        $personnelColumns = 'personnel.id, personnel.tenant_id, personnel.user_id, personnel.full_name, personnel.email, personnel.phone, personnel.personnel_type, personnel.approval_status, personnel.languages, personnel.countries, personnel.experience_summary';

        $builder = $this->db->table('personnel')
            ->select($personnelColumns . ', users.id AS user_id, users.email AS user_email, GROUP_CONCAT(DISTINCT roles.code ORDER BY roles.code) AS role_codes', false)
            ->join('users', 'users.id = personnel.user_id', 'left')
            ->join('user_role_assignments', 'user_role_assignments.user_id = users.id', 'left')
            ->join('roles', 'roles.id = user_role_assignments.role_id', 'left')
            ->where('personnel.tenant_id', $tenantId)
            ->where('personnel.approval_status', 'approved')
            ->where('personnel.deleted_at', null)
            ->whereIn('roles.code', $roleCodes)
            ->groupBy($personnelColumns . ', users.id, users.email');

        if ($blocked !== []) {
            $builder->whereNotIn('personnel.id', $blocked);
        }

        $people = $builder->get()->getResultArray();
        foreach ($people as $person) {
            if ($this->coversCompetence((int) $person['id'], $input, $standards)) {
                return $person;
            }
        }

        return $people[0] ?? null;
    }

    private function coversCompetence(int $personnelId, array $input, array $standards): bool
    {
        if ($standards === []) {
            return true;
        }

        foreach ($standards as $standard) {
            $builder = $this->db->table('personnel_competencies')
                ->where('personnel_id', $personnelId)
                ->where('approval_status', 'approved')
                ->groupStart()
                    ->where('standard_id', (int) $standard['id'])
                    ->orWhere('standard_id', null)
                ->groupEnd();

            if ($input['iaf_code_id'] !== null) {
                $builder->groupStart()->where('iaf_code_id', $input['iaf_code_id'])->orWhere('iaf_code_id', null)->groupEnd();
            }
            if ($input['food_category_id'] !== null) {
                $builder->groupStart()->where('food_chain_category_id', $input['food_category_id'])->orWhere('food_chain_category_id', null)->groupEnd();
            }
            if ($input['medical_category_id'] !== null) {
                $builder->groupStart()->where('medical_device_category_id', $input['medical_category_id'])->orWhere('medical_device_category_id', null)->groupEnd();
            }

            if ((int) $builder->countAllResults() === 0) {
                return false;
            }
        }

        return true;
    }

    private function warnings(array $input, array $standards, array $cycle, array $timeline, array $events, array $assignments): array
    {
        $warnings = [];
        foreach (['client_name', 'scope', 'certificate_issue_date'] as $field) {
            if (($input[$field] ?? '') === '') {
                $warnings[] = ['level' => 'critical', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.'];
            }
        }
        if ($standards === []) {
            $warnings[] = ['level' => 'critical', 'message' => 'At least one active standard must be selected.'];
        }
        foreach (['lead_auditor', 'technical_reviewer', 'decision_maker'] as $key) {
            if (($assignments[$key] ?? null) === null) {
                $warnings[] = ['level' => 'critical', 'message' => 'No eligible ' . str_replace('_', ' ', $key) . ' found. Add staff/competence or adjust Personnel Master.'];
            }
        }
        $ids = array_filter(array_map(static fn ($person): ?int => $person === null ? null : (int) $person['id'], [
            $assignments['lead_auditor'] ?? null,
            $assignments['auditor'] ?? null,
            $assignments['technical_reviewer'] ?? null,
            $assignments['decision_maker'] ?? null,
        ]));
        if (count($ids) !== count(array_unique($ids))) {
            $warnings[] = ['level' => 'critical', 'message' => 'Conflict detected: auditor/reviewer/decision assignments are not independent.'];
        }
        if ($timeline['decision'] >= $cycle['issue']) {
            $warnings[] = ['level' => 'critical', 'message' => 'Decision date must be before certificate issue date.'];
        }
        foreach ($events as $event) {
            if ($event['end'] < $event['start']) {
                $warnings[] = ['level' => 'critical', 'message' => $event['type'] . ' end date is before start date.'];
            }
        }
        if ($input['certificate_expiry_date'] !== '' && $cycle['expiry'] !== (new DateTimeImmutable($input['certificate_expiry_date']))->format('Y-m-d')) {
            $warnings[] = ['level' => 'info', 'message' => 'Custom certificate expiry date is being used. Verify it matches the approved cycle.'];
        }

        return $warnings;
    }

    private function createClient(int $tenantId, int $userId, array $input, array $cycle): int
    {
        $this->db->table('clients')->insert([
            'tenant_id' => $tenantId,
            'company' => $input['client_name'],
            'legal_name' => $input['client_name'],
            'address' => $input['client_address'],
            'country' => '',
            'city' => '',
            'contact_person' => $input['contact_person'],
            'designation' => $input['designation'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'scope' => $input['scope'],
            'employee_count' => $input['employee_count'],
            'permanent_employees' => $input['employee_count'],
            'temporary_employees' => 0,
            'number_of_sites' => $input['number_of_sites'],
            'certification_status' => $input['certification_status'],
            'risk_category' => $input['risk_category'],
            'certificate_issue_date' => $cycle['issue'],
            'initial_certification_date' => $cycle['issue'],
            'certificate_expiry_date' => $cycle['expiry'],
            'notes' => trim("Generated by Automation / Cycle Generator.\n" . $input['special_notes']),
            'is_legacy' => 0,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function createClientRelatedRecords(int $clientId, array $input, array $standards): void
    {
        foreach ($standards as $standard) {
            $this->db->table('client_standards')->insert([
                'client_id' => $clientId,
                'standard_id' => (int) $standard['id'],
                'iaf_code_id' => $input['iaf_code_id'],
                'food_chain_category_id' => $input['food_category_id'],
                'medical_device_category_id' => $input['medical_category_id'],
                'scope' => $input['scope'],
            ]);
        }
        $this->db->table('client_sites')->insert([
            'client_id' => $clientId,
            'site_name' => 'Main site',
            'address' => $input['client_address'],
            'employee_count' => $input['employee_count'],
            'processes' => $this->defaultProcesses($standards),
            'active' => 1,
        ]);
        foreach (explode(',', $this->defaultProcesses($standards)) as $process) {
            $process = trim($process);
            if ($process === '') {
                continue;
            }
            $this->db->table('client_processes')->insert([
                'client_id' => $clientId,
                'process_name' => $process,
                'description' => 'Generated cycle process coverage for ' . $process . '.',
            ]);
        }
    }

    private function createApplication(int $tenantId, int $clientId, int $userId, array $input, array $preview): int
    {
        $number = $this->number('APP-AUTO', $clientId);
        $this->db->table('certification_applications')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'application_number' => $number,
            'document_number' => 'F 25',
            'revision_number' => '1',
            'issue_number' => '2',
            'issue_date' => '2024-11-01',
            'status' => 'approved',
            'submitted_at' => $preview['timeline']['application_submitted'] . ' 10:00:00',
            'declaration_name' => $input['contact_person'],
            'declaration_position' => $input['designation'],
            'declaration_date' => $preview['timeline']['application_submitted'],
            'cb_review_status' => 'accepted',
            'cb_review_notes' => 'Application auto-generated and accepted by cycle automation after preview checks.',
            'reviewed_by' => $preview['assignments']['technical_reviewer']['user_id'] ?? $userId,
            'reviewed_at' => $preview['timeline']['application_review'] . ' 11:30:00',
            'created_by' => $userId,
            'created_at' => $preview['timeline']['application_submitted'] . ' 09:30:00',
        ]);
        $applicationId = (int) $this->db->insertID();
        foreach ($preview['standards'] as $standard) {
            $this->db->table('application_selected_standards')->insert([
                'application_id' => $applicationId,
                'standard_id' => (int) $standard['id'],
                'standard_code' => (string) $standard['code'],
            ]);
        }

        return $applicationId;
    }

    private function createApplicationReview(int $clientId, int $applicationId, array $preview): int
    {
        $duration = $preview['duration'];
        $input = $preview['input'];
        $this->db->table('application_reviews')->insert([
            'client_id' => $clientId,
            'certification_application_id' => $applicationId,
            'application_review_number' => $this->number('AR-AUTO', $clientId),
            'document_number' => 'F 28',
            'revision_number' => '4',
            'issue_number' => '2',
            'document_date' => '2025-02-01',
            'technical_manager_id' => $preview['assignments']['technical_reviewer']['user_id'] ?? null,
            'quality_manager_id' => $preview['assignments']['quality_manager']['user_id'] ?? null,
            'completeness_status' => 'complete',
            'risk_rating' => $input['risk_category'],
            'recommendation' => 'Proceed to proposal',
            'md5_duration_days' => (float) $duration['total_days'],
            'integrated_reduction_percent' => (float) ($duration['reduction_percent'] ?? 0),
            'stage1_days' => (float) $duration['stage1_days'],
            'stage2_days' => (float) $duration['stage2_days'],
            'review_notes' => 'Application review generated from automation preview. Scope, competence, resources and impartiality checked.',
            'review_payload' => json_encode([
                'standards_text' => implode(', ', array_column($preview['standards'], 'code')),
                'effective_employees' => $input['employee_count'],
                'days_allotted' => number_format((float) $duration['total_days'], 2),
                'stage1_days' => number_format((float) $duration['stage1_days'], 2),
                'stage2_days' => number_format((float) $duration['stage2_days'], 2),
                'surveillance1_days' => number_format((float) $duration['surveillance1_days'], 2),
                'surveillance2_days' => number_format((float) $duration['surveillance2_days'], 2),
                'recertification_days' => number_format((float) $duration['recertification_days'], 2),
                'calculation_basis' => $duration['basis'],
            ], JSON_THROW_ON_ERROR),
            'status' => 'qm_approved',
            'reviewed_at' => $preview['timeline']['application_review'] . ' 14:00:00',
            'technical_reviewer_name' => $preview['assignments']['technical_reviewer']['full_name'] ?? '',
            'technical_review_date' => $preview['timeline']['application_review'],
            'quality_manager_status' => 'approved',
            'quality_manager_comments' => 'Independent quality approval generated by automation.',
            'quality_manager_name' => $preview['assignments']['quality_manager']['full_name'] ?? '',
            'quality_manager_date' => $preview['timeline']['application_review'],
            'general_manager_status' => 'not_required',
            'general_manager_comments' => 'GM approval is controlled at decision/certificate stage.',
            'created_at' => $preview['timeline']['application_review'] . ' 10:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function createProposal(int $tenantId, int $clientId, int $reviewId, int $userId, array $preview): int
    {
        $duration = $preview['duration'];
        $certFee = round((float) $duration['total_days'] * 1500, 2);
        $s1Fee = round((float) $duration['surveillance1_days'] * 1500, 2);
        $s2Fee = round((float) $duration['surveillance2_days'] * 1500, 2);
        $travel = max(500, (int) $preview['input']['number_of_sites'] * 500);
        $subtotal = $certFee + $s1Fee + $s2Fee + $travel;
        $vat = round($subtotal * 0.15, 2);
        $total = $subtotal + $vat;

        $this->db->table('proposals')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'application_review_id' => $reviewId,
            'proposal_number' => $this->number('PROP-AUTO', $clientId),
            'version_number' => 1,
            'status' => 'accepted',
            'proposal_date' => $preview['timeline']['proposal_generated'],
            'client_reference' => $this->number('CLIENT-AUTO', $clientId),
            'valid_until' => (new DateTimeImmutable($preview['timeline']['proposal_generated']))->add(new DateInterval('P30D'))->format('Y-m-d'),
            'certification_fee' => $certFee,
            'surveillance1_fee' => $s1Fee,
            'surveillance2_fee' => $s2Fee,
            'travel_fee' => $travel,
            'vat_percent' => 15.00,
            'vat_amount' => $vat,
            'grand_total' => $total,
            'currency' => 'SAR',
            'proposal_payload' => json_encode(['payment_terms' => '50% before Stage 1 audit and 50% before certificate issue.', 'automation' => true], JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'approved_by' => $preview['assignments']['certification_manager']['user_id'] ?? $userId,
            'approved_at' => $preview['timeline']['proposal_accepted'] . ' 16:00:00',
            'created_at' => $preview['timeline']['proposal_generated'] . ' 09:00:00',
        ]);
        $proposalId = (int) $this->db->insertID();
        foreach ([['certification', 'Initial certification audit', $certFee], ['surveillance1', 'Surveillance Audit 1', $s1Fee], ['surveillance2', 'Surveillance Audit 2', $s2Fee], ['travel', 'Travel and logistics estimate', $travel]] as $idx => [$type, $desc, $amount]) {
            $this->db->table('proposal_line_items')->insert([
                'proposal_id' => $proposalId,
                'item_type' => $type,
                'description' => $desc,
                'quantity' => 1,
                'unit_price' => $amount,
                'total' => $amount,
                'sort_order' => $idx + 1,
            ]);
        }

        return $proposalId;
    }

    private function createContract(int $tenantId, int $clientId, int $proposalId, int $userId, array $preview): int
    {
        $input = $preview['input'];
        $this->db->table('contracts')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'proposal_id' => $proposalId,
            'contract_number' => $this->number('CON-AUTO', $clientId),
            'document_number' => 'F 27',
            'revision_number' => '2',
            'issue_number' => '2',
            'document_date' => '2022-05-15',
            'version_number' => 1,
            'status' => 'signed',
            'signed_at' => $preview['timeline']['contract_signed'] . ' 11:00:00',
            'signed_by_name' => $input['contact_person'],
            'contract_payload' => json_encode(['scope' => $input['scope'], 'cycle' => $preview['cycle'], 'automation' => true], JSON_THROW_ON_ERROR),
            'qsi_signatory_name' => $preview['assignments']['general_manager']['full_name'] ?? '',
            'qsi_signatory_date' => $preview['timeline']['contract_signed'],
            'client_signatory_name' => $input['contact_person'],
            'client_signatory_date' => $preview['timeline']['contract_signed'],
            'created_by' => $userId,
            'created_at' => $preview['timeline']['contract_signed'] . ' 10:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function createInvoiceAndPayment(int $tenantId, int $clientId, array $preview): void
    {
        $proposal = $this->db->table('proposals')->where('tenant_id', $tenantId)->where('client_id', $clientId)->orderBy('id', 'DESC')->get()->getRowArray();
        $amount = (float) ($proposal['grand_total'] ?? 0);
        $paid = (new DateTimeImmutable($preview['timeline']['contract_signed'])) < new DateTimeImmutable(date('Y-m-d'));
        $this->db->table('invoices')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'invoice_number' => $this->number('INV-AUTO', $clientId),
            'invoice_date' => $preview['timeline']['contract_signed'],
            'due_date' => (new DateTimeImmutable($preview['timeline']['contract_signed']))->add(new DateInterval('P15D'))->format('Y-m-d'),
            'subtotal' => round($amount / 1.15, 2),
            'vat_amount' => round($amount - ($amount / 1.15), 2),
            'total_amount' => $amount,
            'currency' => 'SAR',
            'status' => $paid ? 'paid' : 'issued',
            'created_at' => $preview['timeline']['contract_signed'] . ' 12:00:00',
        ]);
        $invoiceId = (int) $this->db->insertID();
        if ($paid) {
            $this->db->table('payments')->insert([
                'invoice_id' => $invoiceId,
                'payment_date' => $preview['timeline']['contract_signed'],
                'amount' => $amount,
                'method' => 'Automation entry',
                'reference_number' => $this->number('PAY-AUTO', $clientId),
                'received_by' => $preview['assignments']['finance']['user_id'] ?? null,
                'notes' => 'Payment status generated by cycle automation.',
            ]);
        }
    }

    private function createAuditProgram(int $tenantId, int $clientId, int $contractId, int $userId, array $preview): int
    {
        $cycle = $preview['cycle'];
        $duration = $preview['duration'];
        $today = new DateTimeImmutable(date('Y-m-d'));
        $this->db->table('audit_programs')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'contract_id' => $contractId,
            'program_number' => $this->number('AP-AUTO', $clientId),
            'document_number' => 'F 42',
            'revision_number' => '2',
            'issue_number' => '2',
            'document_date' => '2022-05-15',
            'cycle_type' => 'initial',
            'certificate_issue_date' => $cycle['issue'],
            'surveillance_1_due_date' => $cycle['surveillance1'],
            'surveillance_2_due_date' => $cycle['surveillance2'],
            'certificate_expiry_date' => $cycle['expiry'],
            'surveillance_1_status' => (new DateTimeImmutable($cycle['surveillance1'])) < $today ? 'completed' : 'active',
            'surveillance_2_status' => (new DateTimeImmutable($cycle['surveillance2'])) < $today ? 'completed' : 'locked',
            'status' => 'active',
            'program_payload' => json_encode([
                'standards_text' => implode(', ', array_column($preview['standards'], 'code')),
                'scope' => $preview['input']['scope'],
                'audit_duration_days' => number_format((float) $duration['total_days'], 2),
                'stage1_days' => number_format((float) $duration['stage1_days'], 2),
                'stage2_days' => number_format((float) $duration['stage2_days'], 2),
                'surveillance1_days' => number_format((float) $duration['surveillance1_days'], 2),
                'surveillance2_days' => number_format((float) $duration['surveillance2_days'], 2),
                'recertification_days' => number_format((float) $duration['recertification_days'], 2),
                'legend_notes' => 'Generated by Automation / Cycle Generator.',
            ], JSON_THROW_ON_ERROR),
            'prepared_by_name' => $preview['assignments']['certification_manager']['full_name'] ?? '',
            'prepared_date' => $preview['timeline']['audit_program'],
            'approved_by_name' => $preview['assignments']['technical_reviewer']['full_name'] ?? '',
            'approved_date' => $preview['timeline']['audit_program'],
            'created_by' => $userId,
            'created_at' => $preview['timeline']['audit_program'] . ' 10:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function createEventsAndFiles(int $tenantId, int $clientId, int $programId, int $userId, array $preview): array
    {
        $result = [];
        foreach ($preview['events'] as $type => $event) {
            $this->db->table('audit_events')->insert([
                'audit_program_id' => $programId,
                'event_type' => $type,
                'audit_number' => $this->number('AUD-' . strtoupper(str_replace('_', '-', $type)), $clientId),
                'planned_start_date' => $event['start'],
                'planned_end_date' => $event['end'],
                'actual_start_date' => $event['status'] === 'planned' ? null : $event['start'],
                'actual_end_date' => $event['status'] === 'planned' ? null : $event['end'],
                'audit_window_start' => (new DateTimeImmutable($event['start']))->sub(new DateInterval('P30D'))->format('Y-m-d'),
                'audit_window_end' => (new DateTimeImmutable($event['end']))->add(new DateInterval('P14D'))->format('Y-m-d'),
                'duration_days' => $event['days'],
                'status' => $event['status'],
                'created_at' => $event['start'] . ' 08:00:00',
            ]);
            $eventId = (int) $this->db->insertID();
            $this->createAppointments($eventId, $preview);
            $planId = $this->createAuditPlan($eventId, $type, $event, $preview);
            $reportId = $this->createReport($tenantId, $eventId, $type, $event, $preview);
            $ncrIds = $this->createNcrCapa($tenantId, $clientId, $eventId, $type, $event, $userId, $preview);
            $reviewId = $this->createTechnicalReview($tenantId, $eventId, $type, $event, $ncrIds, $preview);
            $decisionId = $this->createDecision($tenantId, $reviewId, $type, $event, $preview);
            $this->createReminders($eventId, $type, $event, $preview);
            $result[$type] = compact('eventId', 'planId', 'reportId', 'reviewId', 'decisionId') + [
                'event_id' => $eventId,
                'plan_id' => $planId,
                'report_id' => $reportId,
                'review_id' => $reviewId,
                'decision_id' => $decisionId,
                'ncr_ids' => $ncrIds,
            ];
        }

        return $result;
    }

    private function createAppointments(int $eventId, array $preview): void
    {
        foreach (['lead_auditor' => 'lead_auditor', 'auditor' => 'auditor'] as $key => $role) {
            $person = $preview['assignments'][$key] ?? null;
            if ($person === null) {
                continue;
            }
            $this->db->table('auditor_appointments')->insert([
                'audit_event_id' => $eventId,
                'personnel_id' => (int) $person['id'],
                'appointment_role' => $role,
                'appointed_by' => (int) ($preview['assignments']['certification_manager']['user_id'] ?? service('session')->get('user_id')),
                'appointed_at' => $preview['timeline']['appointment'] . ' 09:30:00',
                'status' => 'appointed',
                'conflict_check_json' => json_encode([
                    'competence_confirmed' => true,
                    'impartiality_confirmed' => true,
                    'conflict_of_interest' => false,
                    'notes' => 'Generated by cycle automation after conflict preview.',
                ], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    private function createAuditPlan(int $eventId, string $type, array $event, array $preview): int
    {
        $lead = $preview['assignments']['lead_auditor'] ?? null;
        $tm = $preview['assignments']['technical_reviewer'] ?? null;
        $this->db->table('audit_plans')->insert([
            'audit_event_id' => $eventId,
            'plan_number' => $this->number('PLAN-' . strtoupper(str_replace('_', '-', $type)), $eventId),
            'version_number' => 1,
            'status' => $event['status'] === 'planned' ? 'prepared' : 'approved',
            'prepared_by' => $lead['user_id'] ?? null,
            'approved_by' => $tm['user_id'] ?? null,
            'approved_at' => $event['status'] === 'planned' ? null : (new DateTimeImmutable($event['start']))->sub(new DateInterval('P5D'))->format('Y-m-d 14:00:00'),
            'created_at' => (new DateTimeImmutable($event['start']))->sub(new DateInterval('P5D'))->format('Y-m-d 10:00:00'),
        ]);
        $planId = (int) $this->db->insertID();
        $this->createPlanItems($planId, $type, $event, $preview);

        return $planId;
    }

    private function createPlanItems(int $planId, string $type, array $event, array $preview): void
    {
        $processes = explode(',', $this->defaultProcesses($preview['standards']));
        $slots = [
            ['09:00:00', '09:30:00', 'Opening meeting', 'Top Management', 'Audit objectives, scope and criteria'],
            ['09:30:00', '11:30:00', 'Process audit', 'Operations', trim($processes[0] ?? 'Core process')],
            ['11:30:00', '12:30:00', 'Support process audit', 'Support', trim($processes[1] ?? 'Support process')],
            ['13:30:00', '15:00:00', 'Performance review', 'Quality / Food Safety / HSE', 'Monitoring, internal audit and management review'],
            ['15:00:00', '16:00:00', 'Closing meeting', 'Top Management', 'Findings, conclusions and next steps'],
        ];
        $auditors = array_values(array_filter([$preview['assignments']['lead_auditor'] ?? null, $preview['assignments']['auditor'] ?? null]));
        foreach ($slots as $index => [$start, $end, $activity, $department, $process]) {
            $auditor = $auditors[$index % max(1, count($auditors))] ?? null;
            $this->db->table('audit_plan_items')->insert([
                'audit_plan_id' => $planId,
                'audit_date' => $event['start'],
                'start_time' => $start,
                'end_time' => $end,
                'activity_type' => $activity,
                'department' => $department,
                'process_name' => $process,
                'clauses' => $this->stageClauseFocus($type),
                'auditor_personnel_id' => $auditor['id'] ?? null,
                'notes' => 'Generated by cycle automation; edit timings and process coverage where needed.',
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function createReport(int $tenantId, int $eventId, string $type, array $event, array $preview): int
    {
        $lead = $preview['assignments']['lead_auditor'] ?? null;
        $reviewer = $preview['assignments']['technical_reviewer'] ?? null;
        $payload = [
            'audit_objectives' => 'Verify conformity, implementation and effectiveness for the selected certification scope.',
            'audit_criteria' => implode(', ', array_column($preview['standards'], 'code')) . ', client procedures, legal and customer requirements.',
            'audit_scope' => $preview['input']['scope'],
            'recommendation' => $type === 'initial_stage1' ? 'Proceed to Stage 2 subject to readiness actions.' : 'Maintain/grant certification subject to NCR/CAPA status.',
            'automation' => true,
        ];
        $this->db->table('report_drafts')->insert([
            'tenant_id' => $tenantId,
            'audit_event_id' => $eventId,
            'report_type' => 'audit_execution',
            'version_number' => 1,
            'status' => $event['status'] === 'planned' ? 'draft' : 'approved',
            'generated_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'editable_payload' => json_encode(['auditor_notes' => 'Generated report draft. Auditor shall verify and edit evidence before final submission.'], JSON_THROW_ON_ERROR),
            'prepared_by' => $lead['user_id'] ?? null,
            'approved_by' => $event['status'] === 'planned' ? null : ($reviewer['user_id'] ?? null),
            'approved_at' => $event['status'] === 'planned' ? null : $event['end'] . ' 16:00:00',
            'submitted_at' => $event['status'] === 'planned' ? null : $event['end'] . ' 15:00:00',
            'created_at' => $event['end'] . ' 10:00:00',
        ]);
        $reportId = (int) $this->db->insertID();
        foreach ($this->clauses($preview['standards'], 12) as $sort => $clause) {
            $this->db->table('report_sections')->insert([
                'report_draft_id' => $reportId,
                'clause_library_id' => empty($clause['id']) ? null : (int) $clause['id'],
                'section_key' => 'conformity',
                'section_title' => $clause['standard_code'] . ' ' . $clause['clause_number'] . ' - ' . $clause['clause_title'],
                'section_content' => $this->conformityText($preview['input']['client_name'], $clause, $type),
                'source_type' => 'automation_draft',
                'auditor_confirmed' => $event['status'] === 'planned' ? 0 : 1,
                'confirmed_by_user_id' => $event['status'] === 'planned' ? null : ($lead['user_id'] ?? null),
                'confirmed_at' => $event['status'] === 'planned' ? null : $event['end'] . ' 14:30:00',
                'confirmation_note' => $event['status'] === 'planned' ? null : 'Generated historic cycle section marked confirmed by automation.',
                'sort_order' => $sort + 1,
            ]);
        }

        return $reportId;
    }

    private function createNcrCapa(int $tenantId, int $clientId, int $eventId, string $type, array $event, int $userId, array $preview): array
    {
        $mode = $preview['input']['ncr_mode'];
        $count = match ($mode) {
            'none' => 0,
            'major' => 2,
            default => in_array($type, ['initial_stage2', 'surveillance1'], true) ? 1 : 0,
        };
        $ids = [];
        $clauses = $this->clauses($preview['standards'], max(1, $count));
        for ($i = 1; $i <= $count; $i++) {
            $clause = $clauses[($i - 1) % count($clauses)];
            $severity = $mode === 'major' && $i === 1 ? 'major' : 'minor';
            $closed = $event['status'] !== 'planned';
            $ncrNumber = $this->number('NCR-AUTO-' . strtoupper(str_replace('_', '-', $type)), $clientId) . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $finding = $this->findingText($clause, $preview['input'], $type, $severity);
            $target = (new DateTimeImmutable($event['end']))->add(new DateInterval('P21D'))->format('Y-m-d');
            $this->db->table('ncrs')->insert([
                'tenant_id' => $tenantId,
                'audit_event_id' => $eventId,
                'clause_library_id' => empty($clause['id']) ? null : (int) $clause['id'],
                'ncr_number' => $ncrNumber,
                'requirement' => (string) ($clause['requirement'] ?? $clause['clause_title']),
                'finding' => $finding,
                'objective_evidence' => $this->evidenceText($preview['input']['client_name'], $clause),
                'classification' => $severity,
                'correction' => 'Affected record corrected and responsible process owner briefed.',
                'root_cause' => 'Process verification checklist did not require complete evidence cross-reference before record approval.',
                'corrective_action' => 'Update checklist, train responsible staff, and verify next sample for complete implementation.',
                'responsible_person' => $preview['input']['contact_person'],
                'target_date' => $target,
                'verification' => $closed ? 'Corrective action evidence reviewed and accepted.' : 'Pending auditor verification.',
                'closure_notes' => $closed ? 'Closed by automation for historical cycle.' : 'Open for client action.',
                'status' => $closed ? 'closed' : 'open',
                'closed_at' => $closed ? (new DateTimeImmutable($target))->add(new DateInterval('P3D'))->format('Y-m-d 15:00:00') : null,
                'created_by' => $userId,
                'created_at' => $event['end'] . ' 11:00:00',
            ]);
            $ncrId = (int) $this->db->insertID();
            $ids[] = $ncrId;
            $this->db->table('capas')->insert([
                'tenant_id' => $tenantId,
                'ncr_id' => $ncrId,
                'capa_number' => str_replace('NCR', 'CAPA', $ncrNumber),
                'source' => 'audit_ncr',
                'issue' => $finding,
                'immediate_correction' => 'Corrected affected sample and checked similar records.',
                'root_cause' => 'Review responsibility and record completion criteria were not sufficiently clear.',
                'five_why' => json_encode(['Why was evidence incomplete?' => 'Checklist did not require attachment reference.', 'Why was checklist weak?' => 'Template was not updated after process change.'], JSON_THROW_ON_ERROR),
                'fishbone' => json_encode(['method' => 'Checklist gap', 'people' => 'Reviewer awareness', 'records' => 'Incomplete reference'], JSON_THROW_ON_ERROR),
                'corrective_action' => 'Revise template, brief owners, and add monthly verification sample.',
                'preventive_action' => 'Include evidence-reference check in internal audit sampling.',
                'responsible_person' => $preview['input']['contact_person'],
                'target_date' => $target,
                'evidence_reference' => $this->docPrefix($preview['input']['client_name']) . '-' . $clause['clause_number'] . '-CAPA-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'verification' => $closed ? 'Evidence accepted by audit team.' : 'Pending review.',
                'effectiveness' => $closed ? 'No repeat issue in follow-up sample.' : 'Pending effectiveness verification.',
                'closure_notes' => $closed ? 'Closed by automation for historical cycle.' : 'Awaiting evidence.',
                'status' => $closed ? 'closed' : 'open',
                'closed_at' => $closed ? (new DateTimeImmutable($target))->add(new DateInterval('P5D'))->format('Y-m-d 13:00:00') : null,
                'created_by' => $userId,
                'created_at' => $event['end'] . ' 12:00:00',
            ]);
        }

        return $ids;
    }

    private function createTechnicalReview(int $tenantId, int $eventId, string $type, array $event, array $ncrIds, array $preview): int
    {
        $person = $preview['assignments']['technical_reviewer'] ?? null;
        $closed = $event['status'] !== 'planned';
        $this->db->table('technical_reviews')->insert([
            'tenant_id' => $tenantId,
            'audit_event_id' => $eventId,
            'reviewer_personnel_id' => (int) ($person['id'] ?? 0),
            'checklist_payload' => json_encode(['automation' => true, 'ncr_count' => count($ncrIds), 'review_notes' => 'Generated technical review record.'], JSON_THROW_ON_ERROR),
            'competency_confirmed' => $closed ? 1 : 0,
            'duration_confirmed' => $closed ? 1 : 0,
            'application_confirmed' => $closed ? 1 : 0,
            'reports_confirmed' => $closed ? 1 : 0,
            'ncr_capa_confirmed' => $closed ? 1 : 0,
            'scope_dates_confirmed' => $closed ? 1 : 0,
            'impartiality_confirmed' => $closed ? 1 : 0,
            'recommendation' => $closed ? 'approve' : 'pending',
            'status' => $closed ? 'approved' : 'pending',
            'reviewed_at' => $closed ? (new DateTimeImmutable($event['end']))->add(new DateInterval('P2D'))->format('Y-m-d 14:00:00') : null,
            'created_at' => (new DateTimeImmutable($event['end']))->add(new DateInterval('P2D'))->format('Y-m-d 10:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function createDecision(int $tenantId, int $reviewId, string $type, array $event, array $preview): int
    {
        $person = $preview['assignments']['decision_maker'] ?? null;
        $closed = $event['status'] !== 'planned';
        $decision = match ($type) {
            'initial_stage1' => 'continue_to_stage2',
            'initial_stage2' => 'grant',
            'surveillance1', 'surveillance2' => 'maintain',
            'recertification' => 'renew',
            default => 'approve',
        };
        $this->db->table('certification_decisions')->insert([
            'tenant_id' => $tenantId,
            'technical_review_id' => $reviewId,
            'decision_maker_personnel_id' => (int) ($person['id'] ?? 0),
            'decision' => $decision,
            'reason' => $closed ? 'Decision generated based on approved technical review and completed audit file.' : 'Decision pending completion of planned audit.',
            'electronic_signature' => $closed ? (($person['full_name'] ?? 'Decision Maker') . ' / automation e-signature') : null,
            'decision_payload' => json_encode(['automation' => true, 'event_type' => $type], JSON_THROW_ON_ERROR),
            'decided_at' => $closed ? (new DateTimeImmutable($event['end']))->add(new DateInterval('P3D'))->format('Y-m-d 11:00:00') : null,
            'status' => $closed ? 'approved' : 'pending',
            'gm_approved_by_user_id' => $closed ? ($preview['assignments']['general_manager']['user_id'] ?? null) : null,
            'gm_approval_notes' => $closed ? 'Final approval generated by automation.' : null,
            'gm_approved_at' => $closed ? (new DateTimeImmutable($event['end']))->add(new DateInterval('P3D'))->format('Y-m-d 15:00:00') : null,
            'created_at' => (new DateTimeImmutable($event['end']))->add(new DateInterval('P3D'))->format('Y-m-d 10:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function createCertificates(int $tenantId, int $clientId, ?int $decisionId, array $preview): array
    {
        $ids = [];
        $certificateStatus = $preview['input']['certification_status'] === 'certified'
            ? 'active'
            : $preview['input']['certification_status'];
        $certificateStatus = in_array($certificateStatus, ['active', 'suspended', 'withdrawn', 'expired'], true)
            ? $certificateStatus
            : 'active';

        foreach ($preview['standards'] as $idx => $standard) {
            $certNo = $this->number('QSI-CERT-AUTO', $clientId) . '-' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $certNo));
            $this->db->table('certificates')->insert([
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
                'certification_decision_id' => $decisionId,
                'certificate_number' => $certNo,
                'standard_id' => (int) $standard['id'],
                'scope' => $preview['input']['scope'],
                'issue_date' => $preview['cycle']['issue'],
                'expiry_date' => $preview['cycle']['expiry'],
                'initial_certification_date' => $preview['cycle']['issue'],
                'status' => $certificateStatus,
                'qr_payload' => 'certificates/verify/' . $slug,
                'public_slug' => $slug,
                'created_at' => $preview['cycle']['issue'] . ' 10:00:00',
            ]);
            $ids[] = (int) $this->db->insertID();
        }

        return $ids;
    }

    private function createFeedback(int $tenantId, int $clientId, int $programId, ?int $certificateId, array $preview): int
    {
        $this->db->table('client_feedback')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'audit_program_id' => $programId,
            'certificate_id' => $certificateId,
            'contact_name' => $preview['input']['contact_person'],
            'contact_email' => $preview['input']['email'],
            'submitted_at' => (new DateTimeImmutable($preview['cycle']['issue']))->add(new DateInterval('P7D'))->format('Y-m-d 12:00:00'),
            'overall_rating' => 4,
            'communication_rating' => 4,
            'auditor_rating' => 4,
            'report_quality_rating' => 4,
            'comments' => 'Generated client feedback placeholder for cycle monitoring.',
            'improvement_suggestion' => 'Review and replace with actual client feedback when available.',
            'status' => 'submitted',
            'created_by' => (int) service('session')->get('user_id'),
            'created_at' => (new DateTimeImmutable($preview['cycle']['issue']))->add(new DateInterval('P7D'))->format('Y-m-d 12:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function createReminders(int $eventId, string $type, array $event, array $preview): void
    {
        foreach ([['audit_due', -30], ['report_due', 1], ['capa_followup', 21]] as [$kind, $offset]) {
            $date = (new DateTimeImmutable($event['start']))->add(new DateInterval('P' . abs($offset) . 'D'));
            if ($offset < 0) {
                $date = (new DateTimeImmutable($event['start']))->sub(new DateInterval('P' . abs($offset) . 'D'));
            }
            $this->db->table('audit_reminders')->insert([
                'audit_event_id' => $eventId,
                'reminder_type' => $kind . '_' . $type,
                'due_date' => $date->format('Y-m-d'),
                'status' => $date < new DateTimeImmutable(date('Y-m-d')) ? 'closed' : 'open',
                'sent_at' => $date < new DateTimeImmutable(date('Y-m-d')) ? $date->format('Y-m-d 09:00:00') : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function recordRun(int $tenantId, int $clientId, int $userId, array $preview, array $generated): int
    {
        $this->db->table('automation_runs')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'run_number' => $this->number('AUTO-CYCLE', $clientId),
            'module' => 'cycle_generator',
            'status' => 'generated',
            'input_payload' => json_encode($preview['input'], JSON_THROW_ON_ERROR),
            'preview_payload' => json_encode($preview, JSON_THROW_ON_ERROR),
            'generated_payload' => json_encode($generated, JSON_THROW_ON_ERROR),
            'warning_payload' => json_encode($preview['warnings'], JSON_THROW_ON_ERROR),
            'generated_by' => $userId,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function logAutomation(int $tenantId, int $userId, int $clientId, int $runId, array $preview): void
    {
        foreach ([
            'preview_date_logic',
            'preview_staff_conflicts',
            'generate_client_file',
            'generate_audit_program',
            'generate_audit_reports',
            'generate_reviews_decisions',
        ] as $action) {
            $this->logger->record($action, 'automation', 'automation_runs', $runId, null, [
                'client_id' => $clientId,
                'warnings' => $preview['warnings'],
            ], $tenantId, $userId);
        }
    }

    private function clauses(array $standards, int $limit): array
    {
        if ($standards === []) {
            return [];
        }

        $rows = $this->db->table('clause_library')
            ->select('clause_library.*, standards.code AS standard_code')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->whereIn('standard_id', array_column($standards, 'id'))
            ->where('clause_library.active', 1)
            ->orderBy('standards.code')
            ->orderBy('clause_library.clause_number')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $rows ?: [[
            'id' => null,
            'standard_code' => $standards[0]['code'] ?? 'STD',
            'clause_number' => '8.1',
            'clause_title' => 'Operational control',
            'requirement' => 'Operational controls shall be planned, implemented and maintained.',
        ]];
    }

    private function clientShape(array $input): array
    {
        return [
            'employee_count' => $input['employee_count'],
            'permanent_employees' => $input['employee_count'],
            'number_of_sites' => $input['number_of_sites'],
            'scope' => $input['scope'],
            'risk_category' => $input['risk_category'],
        ];
    }

    private function defaultProcesses(array $standards): string
    {
        $codes = strtoupper(implode(' ', array_column($standards, 'code')));
        if (str_contains($codes, 'HACCP') || str_contains($codes, '22000') || str_contains($codes, 'FSSC')) {
            return 'Receiving, Storage, Preparation / Processing, Packaging, Dispatch, PRP / Hygiene, Traceability and Recall';
        }
        if (str_contains($codes, '14001')) {
            return 'Environmental aspect control, Waste management, Emergency preparedness, Compliance evaluation, Monitoring and measurement';
        }
        if (str_contains($codes, '45001')) {
            return 'Hazard identification, Operational control, Emergency preparedness, Incident investigation, Worker consultation';
        }

        return 'Sales and contract review, Operations, Purchasing, Quality control, Internal audit, Management review';
    }

    private function stageClauseFocus(string $type): string
    {
        return $type === 'initial_stage1'
            ? 'Context, scope, policy, objectives, documented information and readiness'
            : 'Operational control, performance evaluation, internal audit, management review and improvement';
    }

    private function conformityText(string $clientName, array $clause, string $type): string
    {
        return sprintf(
            'Automation draft: %s showed implementation of %s %s through sampled records and interviews for %s. Evidence reference: %s.',
            $clientName,
            (string) ($clause['standard_code'] ?? ''),
            (string) ($clause['clause_number'] ?? ''),
            str_replace('_', ' ', $type),
            $this->docPrefix($clientName) . '-' . ($clause['clause_number'] ?? 'GEN') . '-001'
        );
    }

    private function findingText(array $clause, array $input, string $type, string $severity): string
    {
        return ucfirst($severity) . ' nonconformity raised during ' . str_replace('_', ' ', $type)
            . ': sampled evidence for ' . ($clause['clause_title'] ?? 'the requirement')
            . ' did not fully demonstrate controlled implementation for ' . $input['scope'] . '.';
    }

    private function evidenceText(string $clientName, array $clause): string
    {
        $ref = $this->docPrefix($clientName) . '-' . ($clause['clause_number'] ?? 'GEN') . '-NCR-001';

        return 'Objective evidence sampled: document/reference ' . $ref . ', responsible person interview, process record sample, and site observation linked to the applicable clause.';
    }

    private function docPrefix(string $clientName): string
    {
        $letters = strtoupper(preg_replace('/[^A-Z]/', '', $clientName));

        return substr($letters . 'QSI', 0, 3);
    }

    private function number(string $prefix, int $id): string
    {
        return $prefix . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . '-' . date('His');
    }

    private function intOrNull(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}

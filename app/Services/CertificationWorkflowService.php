<?php

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

class CertificationWorkflowService
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function steps(): array
    {
        return [
            ['key' => 'application', 'label' => 'Client application', 'owner' => 'Client', 'description' => 'Application submitted and applicable standards selected.'],
            ['key' => 'tm_application_review', 'label' => 'Technical Manager review', 'owner' => 'Technical Manager', 'description' => 'Scope, competence, resources and capability checked.'],
            ['key' => 'qm_application_approval', 'label' => 'Quality Manager approval', 'owner' => 'Quality Manager', 'description' => 'Independent application approval or rejection.'],
            ['key' => 'proposal', 'label' => 'Proposal', 'owner' => 'Admin / Client', 'description' => 'Proposal prepared, issued and accepted or rejected by the client.'],
            ['key' => 'contract', 'label' => 'Contract', 'owner' => 'Admin / Client', 'description' => 'Scope, standards, duration, fees, terms and cycle requirements agreed.'],
            ['key' => 'audit_program', 'label' => 'Three-year audit program', 'owner' => 'Admin', 'description' => 'Initial certification, Surveillance 1, Surveillance 2 and recertification planned.'],
            ['key' => 'auditor_appointment', 'label' => 'Auditor appointment', 'owner' => 'Admin / Technical', 'description' => 'Competent auditors assigned with impartiality and conflict checks.'],
            ['key' => 'stage1', 'label' => 'Stage 1 audit', 'owner' => 'Auditor', 'description' => 'Stage 1 plan prepared and Stage 1 audit completed.'],
            ['key' => 'stage2', 'label' => 'Stage 2 audit', 'owner' => 'Auditor', 'description' => 'Stage 2 plan prepared and Stage 2 audit completed.'],
            ['key' => 'ncr_closure', 'label' => 'Nonconformity closure', 'owner' => 'Client / Auditor', 'description' => 'All audit nonconformities addressed and closed.'],
            ['key' => 'tm_file_review', 'label' => 'Technical Manager file review', 'owner' => 'Technical Manager', 'description' => 'Complete audit file reviewed and approved or returned for correction.'],
            ['key' => 'certification_decision', 'label' => 'Certification decision', 'owner' => 'Decision Maker', 'description' => 'Independent certification decision recorded.'],
            ['key' => 'gm_final_approval', 'label' => 'General Manager final approval', 'owner' => 'General Manager', 'description' => 'Final management approval before certificate issue.'],
            ['key' => 'certificates', 'label' => 'Certificate issue', 'owner' => 'Admin', 'description' => 'Separate certificate issued for each approved standard.'],
            ['key' => 'feedback', 'label' => 'Client feedback', 'owner' => 'Quality', 'description' => 'Client satisfaction feedback collected for improvement.'],
        ];
    }

    public function clientSummaries(int $tenantId): array
    {
        $clients = $this->db->table('clients')
            ->select('id, company, certification_status, certificate_number, certificate_expiry_date')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->orderBy('company', 'ASC')
            ->get()
            ->getResultArray();

        $standardsByClient = $this->standardsForClients(array_map(static fn (array $client): int => (int) $client['id'], $clients));

        return array_map(fn (array $client): array => [
            'client' => $client,
            'standards' => $standardsByClient[(int) $client['id']] ?? [],
            'workflow' => $this->buildForClient($tenantId, (int) $client['id']),
        ], $clients);
    }

    public function buildForClient(int $tenantId, int $clientId): array
    {
        $records = $this->records($tenantId, $clientId);
        $steps = [];

        foreach ($this->steps() as $definition) {
            $method = 'step' . str_replace(' ', '', ucwords(str_replace('_', ' ', $definition['key'])));
            $status = method_exists($this, $method)
                ? $this->{$method}($records)
                : $this->status('pending', 'Waiting for earlier workflow activity.');

            $steps[] = array_merge($definition, $status);
        }

        $completed = count(array_filter($steps, static fn (array $step): bool => $step['state'] === 'complete'));

        return [
            'steps' => $steps,
            'records' => $records,
            'responsible' => $this->responsiblePeople($records),
            'completed' => $completed,
            'total' => count($steps),
            'progress' => (int) round(($completed / max(1, count($steps))) * 100),
            'current' => $this->currentStep($steps),
        ];
    }

    private function records(int $tenantId, int $clientId): array
    {
        $applicationReview = $this->latest('application_reviews', ['client_id' => $clientId]);
        $proposal = $this->latest('proposals', ['tenant_id' => $tenantId, 'client_id' => $clientId]);
        $contract = $this->latest('contracts', ['tenant_id' => $tenantId, 'client_id' => $clientId]);
        $auditProgram = $this->latest('audit_programs', ['tenant_id' => $tenantId, 'client_id' => $clientId]);
        $auditEvents = $auditProgram === null ? [] : $this->db->table('audit_events')
            ->where('audit_program_id', (int) $auditProgram['id'])
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $stage1 = $this->eventByType($auditEvents, ['stage1', 'initial_stage1']);
        $stage2 = $this->eventByType($auditEvents, ['stage2', 'initial_stage2']);
        $surveillance1 = $this->eventByType($auditEvents, ['surveillance1']);
        $surveillance2 = $this->eventByType($auditEvents, ['surveillance2']);
        $recertification = $this->eventByType($auditEvents, ['recertification']);
        $technicalReview = $this->certificationTechnicalReview($tenantId, $stage1, $stage2);
        $decision = $technicalReview === null ? null : $this->latest('certification_decisions', [
            'tenant_id' => $tenantId,
            'technical_review_id' => (int) $technicalReview['id'],
        ]);

        return [
            'client_standard_count' => $this->count('client_standards', ['client_id' => $clientId]),
            'application_review' => $applicationReview,
            'proposal' => $proposal,
            'contract' => $contract,
            'audit_program' => $auditProgram,
            'audit_events' => $auditEvents,
            'stage1' => $stage1,
            'stage2' => $stage2,
            'surveillance1' => $surveillance1,
            'surveillance2' => $surveillance2,
            'recertification' => $recertification,
            'appointment_count' => $this->appointmentCount($auditEvents),
            'open_ncr_count' => $this->ncrCount($tenantId, $auditEvents, false),
            'total_ncr_count' => $this->ncrCount($tenantId, $auditEvents, true),
            'technical_review' => $technicalReview,
            'certification_decision' => $decision,
            'certificate_count' => $this->count('certificates', ['tenant_id' => $tenantId, 'client_id' => $clientId]),
            'feedback_count' => $this->db->tableExists('client_feedback') ? $this->count('client_feedback', ['tenant_id' => $tenantId, 'client_id' => $clientId]) : 0,
            'feedback_supported' => $this->db->tableExists('client_feedback'),
        ];
    }

    private function responsiblePeople(array $records): array
    {
        $applicationReview = $records['application_review'];
        $proposal = $records['proposal'];
        $contract = $records['contract'];
        $auditProgram = $records['audit_program'];
        $technicalReview = $records['technical_review'];
        $decision = $records['certification_decision'];

        return [
            'technical_manager' => $applicationReview === null ? null : $this->userName($applicationReview['technical_manager_id'] ?? null),
            'quality_manager' => $applicationReview === null ? null : $this->userName($applicationReview['quality_manager_id'] ?? null),
            'proposal_created_by' => $proposal === null ? null : $this->userName($proposal['created_by'] ?? null),
            'proposal_approved_by' => $proposal === null ? null : $this->userName($proposal['approved_by'] ?? null),
            'contract_created_by' => $contract === null ? null : $this->userName($contract['created_by'] ?? null),
            'contract_signed_by' => $contract['signed_by_name'] ?? null,
            'audit_program_created_by' => $auditProgram === null ? null : $this->userName($auditProgram['created_by'] ?? null),
            'stage1_auditors' => $this->appointmentNames($records['stage1']['id'] ?? null),
            'stage2_auditors' => $this->appointmentNames($records['stage2']['id'] ?? null),
            'all_auditors' => $this->appointmentNamesForEvents($records['audit_events']),
            'technical_reviewer' => $technicalReview === null ? null : $this->personnelName($technicalReview['reviewer_personnel_id'] ?? null),
            'decision_maker' => $decision === null ? null : $this->personnelName($decision['decision_maker_personnel_id'] ?? null),
            'general_manager' => $decision !== null && ($decision['status'] ?? '') === 'gm_approved'
                ? ($this->userName($decision['gm_approved_by_user_id'] ?? null) ?? 'General Manager approval recorded')
                : null,
        ];
    }

    private function stepApplication(array $records): array
    {
        if ($records['application_review'] !== null || $records['client_standard_count'] > 0) {
            return $this->status('complete', 'Application record or selected standards found.');
        }

        return $this->status('pending', 'Waiting for client application and selected standards.');
    }

    private function stepTmApplicationReview(array $records): array
    {
        $review = $records['application_review'];

        if ($review === null) {
            return $this->status('pending', 'No application review started.');
        }

        return match ($review['status']) {
            'tm_rejected', 'rejected' => $this->status('rejected', 'Technical Manager rejected the application.'),
            'tm_approved', 'qm_approved', 'approved' => $this->status('complete', 'Technical Manager review approved.'),
            default => $this->status('in_progress', 'Technical Manager review is pending.'),
        };
    }

    private function stepQmApplicationApproval(array $records): array
    {
        $review = $records['application_review'];

        if ($review === null) {
            return $this->status('pending', 'Waiting for Technical Manager review first.');
        }

        return match ($review['status']) {
            'qm_rejected' => $this->status('rejected', 'Quality Manager rejected the application.'),
            'qm_approved', 'approved' => $this->status('complete', 'Quality Manager approval recorded.'),
            'tm_approved' => $this->status('in_progress', 'Ready for Quality Manager approval.'),
            default => $this->status('pending', 'Waiting for Technical Manager approval.'),
        };
    }

    private function stepProposal(array $records): array
    {
        return $this->documentStatus($records['proposal'], ['accepted', 'approved'], 'Proposal accepted.', 'Proposal prepared and awaiting final client response.', 'Waiting for approved application.');
    }

    private function stepContract(array $records): array
    {
        return $this->documentStatus($records['contract'], ['signed', 'approved', 'active'], 'Contract signed or approved.', 'Contract prepared and awaiting signature/approval.', 'Waiting for accepted proposal.');
    }

    private function stepAuditProgram(array $records): array
    {
        return $records['audit_program'] === null
            ? $this->status('pending', 'Waiting for approved contract.')
            : $this->status('complete', 'Three-year audit program exists.');
    }

    private function stepAuditorAppointment(array $records): array
    {
        if ($records['appointment_count'] > 0) {
            return $this->status('complete', $records['appointment_count'] . ' appointment record(s) found.');
        }

        return $records['audit_program'] === null
            ? $this->status('pending', 'Waiting for audit program.')
            : $this->status('pending', 'No auditor appointment recorded yet.');
    }

    private function stepStage1(array $records): array
    {
        return $this->auditEventStatus($records['stage1'], 'Stage 1 audit completed.', 'Stage 1 audit is planned or in progress.', 'Waiting for auditor appointment.');
    }

    private function stepStage2(array $records): array
    {
        return $this->auditEventStatus($records['stage2'], 'Stage 2 audit completed.', 'Stage 2 audit is planned or in progress.', 'Waiting for Stage 1 completion.');
    }

    private function stepNcrClosure(array $records): array
    {
        if ($records['stage2'] === null) {
            return $this->status('pending', 'Waiting for Stage 2 audit.');
        }

        if ($records['open_ncr_count'] > 0) {
            return $this->status('in_progress', $records['open_ncr_count'] . ' nonconformity record(s) still open.');
        }

        if ($records['total_ncr_count'] > 0) {
            return $this->status('complete', 'All nonconformities are closed.');
        }

        return in_array($records['stage2']['status'], ['completed', 'closed'], true)
            ? $this->status('complete', 'No open nonconformities found.')
            : $this->status('pending', 'Waiting for Stage 2 completion.');
    }

    private function stepTmFileReview(array $records): array
    {
        $review = $records['technical_review'];

        if ($review === null) {
            return $this->status('pending', 'Waiting for audit report/file submission.');
        }

        return match ($review['status']) {
            'approved' => $this->status('complete', 'Technical Manager file review approved.'),
            'rejected', 'returned' => $this->status('rejected', 'File was returned for correction.'),
            default => $this->status('in_progress', 'Technical Manager file review is pending.'),
        };
    }

    private function stepCertificationDecision(array $records): array
    {
        $decision = $records['certification_decision'];

        if ($decision === null) {
            return $this->status('pending', 'Waiting for Technical Manager file approval.');
        }

        if (in_array($decision['decision'], ['rejected', 'not_granted'], true)) {
            return $this->status('rejected', 'Certification was not granted.');
        }

        return in_array($decision['status'], ['approved', 'decided', 'gm_approved'], true) || in_array($decision['decision'], ['approved', 'granted'], true)
            ? $this->status('complete', 'Certification decision recorded.')
            : $this->status('in_progress', 'Certification decision is pending.');
    }

    private function stepGmFinalApproval(array $records): array
    {
        $decision = $records['certification_decision'];

        if ($records['certificate_count'] > 0 || ($decision !== null && $decision['status'] === 'gm_approved')) {
            return $this->status('complete', 'General Manager final approval is satisfied.');
        }

        return $decision === null
            ? $this->status('pending', 'Waiting for certification decision.')
            : $this->status('pending', 'Waiting for General Manager final approval.');
    }

    private function stepCertificates(array $records): array
    {
        return $records['certificate_count'] > 0
            ? $this->status('complete', $records['certificate_count'] . ' certificate record(s) issued.')
            : $this->status('pending', 'Waiting for final approval and certificate issue.');
    }

    private function stepFeedback(array $records): array
    {
        if (! $records['feedback_supported']) {
            return $this->status('pending', 'Feedback table will be added with the feedback module.');
        }

        return $records['feedback_count'] > 0
            ? $this->status('complete', 'Client feedback collected.')
            : $this->status('pending', 'Waiting for client feedback.');
    }

    private function documentStatus(?array $record, array $completeStatuses, string $complete, string $inProgress, string $pending): array
    {
        if ($record === null) {
            return $this->status('pending', $pending);
        }

        if (in_array($record['status'], ['rejected', 'cancelled'], true)) {
            return $this->status('rejected', ucfirst($record['status']) . '.');
        }

        return in_array($record['status'], $completeStatuses, true)
            ? $this->status('complete', $complete)
            : $this->status('in_progress', $inProgress);
    }

    private function auditEventStatus(?array $event, string $complete, string $inProgress, string $pending): array
    {
        if ($event === null) {
            return $this->status('pending', $pending);
        }

        return in_array($event['status'], ['completed', 'closed'], true)
            ? $this->status('complete', $complete)
            : $this->status('in_progress', $inProgress);
    }

    private function status(string $state, string $note): array
    {
        return ['state' => $state, 'note' => $note];
    }

    private function currentStep(array $steps): array
    {
        foreach ($steps as $step) {
            if ($step['state'] !== 'complete') {
                return $step;
            }
        }

        return end($steps);
    }

    private function latest(string $table, array $where): ?array
    {
        $builder = $this->db->table($table);

        foreach ($where as $field => $value) {
            $builder->where($field, $value);
        }

        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function count(string $table, array $where): int
    {
        $builder = $this->db->table($table);

        foreach ($where as $field => $value) {
            $builder->where($field, $value);
        }

        return $builder->countAllResults();
    }

    private function standardsForClients(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $rows = $this->db->table('client_standards')
            ->select('client_standards.client_id, standards.code, standards.name')
            ->join('standards', 'standards.id = client_standards.standard_id')
            ->whereIn('client_standards.client_id', $clientIds)
            ->orderBy('standards.code', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $clientId = (int) $row['client_id'];
            $code = trim((string) ($row['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $grouped[$clientId][$code] = [
                'code' => $code,
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        return array_map(static fn (array $standards): array => array_values($standards), $grouped);
    }

    private function eventByType(array $events, array $types): ?array
    {
        foreach ($events as $event) {
            if (in_array($event['event_type'], $types, true)) {
                return $event;
            }
        }

        return null;
    }

    private function appointmentCount(array $events): int
    {
        if ($events === []) {
            return 0;
        }

        return $this->db->table('auditor_appointments')
            ->whereIn('audit_event_id', array_column($events, 'id'))
            ->countAllResults();
    }

    private function ncrCount(int $tenantId, array $events, bool $includeClosed): int
    {
        if ($events === []) {
            return 0;
        }

        $builder = $this->db->table('ncrs')
            ->where('tenant_id', $tenantId)
            ->whereIn('audit_event_id', array_column($events, 'id'));

        if (! $includeClosed) {
            $builder->whereNotIn('status', ['closed', 'verified_closed']);
        }

        return $builder->countAllResults();
    }

    private function certificationTechnicalReview(int $tenantId, ?array $stage1, ?array $stage2): ?array
    {
        if ($stage2 !== null) {
            return $this->technicalReviewForEvent($tenantId, (int) $stage2['id']);
        }

        if ($stage1 !== null) {
            return $this->technicalReviewForEvent($tenantId, (int) $stage1['id']);
        }

        return null;
    }

    private function technicalReviewForEvent(int $tenantId, int $eventId): ?array
    {
        return $this->db->table('technical_reviews')
            ->where('tenant_id', $tenantId)
            ->where('audit_event_id', $eventId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function userName(mixed $userId): ?string
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $user = $this->db->table('users')
            ->select('full_name, email')
            ->where('id', (int) $userId)
            ->get(1)
            ->getRowArray();

        if ($user === null) {
            return null;
        }

        return trim((string) ($user['full_name'] ?: $user['email'])) ?: null;
    }

    private function personnelName(mixed $personnelId): ?string
    {
        if ($personnelId === null || $personnelId === '') {
            return null;
        }

        $person = $this->db->table('personnel')
            ->select('full_name, email')
            ->where('id', (int) $personnelId)
            ->get(1)
            ->getRowArray();

        if ($person === null) {
            return null;
        }

        return trim((string) ($person['full_name'] ?: $person['email'])) ?: null;
    }

    private function appointmentNames(?int $auditEventId): array
    {
        if ($auditEventId === null) {
            return [];
        }

        return $this->db->table('auditor_appointments')
            ->select('personnel.full_name, auditor_appointments.appointment_role')
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->where('auditor_appointments.audit_event_id', $auditEventId)
            ->orderBy('auditor_appointments.appointment_role', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function appointmentNamesForEvents(array $events): array
    {
        if ($events === []) {
            return [];
        }

        return $this->db->table('auditor_appointments')
            ->select('DISTINCT personnel.full_name, auditor_appointments.appointment_role', false)
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->whereIn('auditor_appointments.audit_event_id', array_column($events, 'id'))
            ->orderBy('personnel.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }
}

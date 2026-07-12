<?php

namespace App\Controllers\Workflow;

use App\Controllers\BaseController;
use App\Models\ApplicationReviewModel;
use App\Models\AuditEventModel;
use App\Models\AuditorAppointmentModel;
use App\Models\AuditPlanItemModel;
use App\Models\AuditPlanModel;
use App\Models\AuditProgramModel;
use App\Models\CapaModel;
use App\Models\CertificateModel;
use App\Models\CertificationDecisionModel;
use App\Models\ClientFeedbackModel;
use App\Models\ClientModel;
use App\Models\ContractModel;
use App\Models\ClauseLibraryModel;
use App\Models\NcrModel;
use App\Models\PersonnelModel;
use App\Models\ProposalModel;
use App\Models\ReportDraftModel;
use App\Models\ReportSectionModel;
use App\Models\TechnicalReviewModel;
use App\Services\AuditLogger;
use App\Services\AuditAiDraftService;
use App\Services\AuditDurationService;
use App\Services\AuditReportNarrativeService;
use App\Services\CertificationApplicationDefaults;
use App\Services\ClauseContentPoolService;
use App\Services\CommercialTermsService;
use App\Services\SmartAuditContentEngine;
use App\Services\NotificationService;
use App\Services\WorkflowRoleService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use DateInterval;
use DateTimeImmutable;

class WorkflowActionController extends BaseController
{
    private ClientModel $clients;
    private ApplicationReviewModel $reviews;
    private ProposalModel $proposals;
    private ContractModel $contracts;
    private AuditProgramModel $programs;
    private CertificateModel $certificates;
    private CertificationDecisionModel $decisions;
    private ClientFeedbackModel $feedbackModel;
    private AuditEventModel $events;
    private AuditorAppointmentModel $appointments;
    private AuditPlanModel $plans;
    private AuditPlanItemModel $planItems;
    private ClauseLibraryModel $clauses;
    private ReportDraftModel $reports;
    private ReportSectionModel $reportSections;
    private TechnicalReviewModel $technicalReviews;
    private NcrModel $ncrs;
    private CapaModel $capas;
    private PersonnelModel $personnel;
    private AuditLogger $auditLogger;
    private AuditAiDraftService $aiDrafts;
    private AuditDurationService $durationService;
    private AuditReportNarrativeService $narratives;
    private ClauseContentPoolService $contentPool;
    private SmartAuditContentEngine $contentEngine;
    private WorkflowRoleService $workflowRoles;
    private NotificationService $notifications;
    private CertificationApplicationDefaults $applicationDefaults;
    private CommercialTermsService $commercialTerms;
    private BaseConnection $db;

    public function __construct()
    {
        $this->clients = new ClientModel();
        $this->reviews = new ApplicationReviewModel();
        $this->proposals = new ProposalModel();
        $this->contracts = new ContractModel();
        $this->programs = new AuditProgramModel();
        $this->certificates = new CertificateModel();
        $this->decisions = new CertificationDecisionModel();
        $this->feedbackModel = new ClientFeedbackModel();
        $this->events = new AuditEventModel();
        $this->appointments = new AuditorAppointmentModel();
        $this->plans = new AuditPlanModel();
        $this->planItems = new AuditPlanItemModel();
        $this->clauses = new ClauseLibraryModel();
        $this->reports = new ReportDraftModel();
        $this->reportSections = new ReportSectionModel();
        $this->technicalReviews = new TechnicalReviewModel();
        $this->ncrs = new NcrModel();
        $this->capas = new CapaModel();
        $this->personnel = new PersonnelModel();
        $this->auditLogger = new AuditLogger();
        $this->aiDrafts = new AuditAiDraftService();
        $this->durationService = new AuditDurationService();
        $this->narratives = new AuditReportNarrativeService();
        $this->contentPool = new ClauseContentPoolService();
        $this->contentEngine = new SmartAuditContentEngine($this->contentPool, $this->narratives);
        $this->notifications = new NotificationService();
        $this->applicationDefaults = new CertificationApplicationDefaults();
        $this->commercialTerms = new CommercialTermsService();
        $this->db = Database::connect();
        $this->workflowRoles = new WorkflowRoleService($this->db);
    }

    public function review(int $clientId)
    {
        $client = $this->tenantClient($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        $review = $this->latestReview($clientId) ?? $this->blankReview($client);
        $application = $this->latestCertificationApplication($clientId);
        $standards = $this->clientStandardRows($clientId);

        return view('workflow/actions/review', [
            'title' => 'Application Review',
            'pageTitle' => 'Application Review',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'review' => $review,
            'application' => $application,
            'standards' => $standards,
            'payload' => $this->applicationReviewPayload($client, $review, $application, $standards),
        ]);
    }

    public function saveReview(int $clientId)
    {
        $client = $this->tenantClient($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if (! $this->validate([
            'completeness_status' => 'required|max_length[40]',
            'status' => 'required|max_length[40]',
            'stage1_days' => 'permit_empty|decimal',
            'stage2_days' => 'permit_empty|decimal',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $existing = $this->latestReview($clientId);
        $status = (string) $this->request->getPost('status');
        $reviewStage = str_starts_with($status, 'tm_')
            ? 'application_review_tm'
            : (str_starts_with($status, 'qm_') ? 'application_review_qm' : 'application_review_manage');
        if (($roleError = $this->workflowRoles->denialReason($reviewStage)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $application = $this->latestCertificationApplication($clientId);
        $standards = $this->clientStandardRows($clientId);
        $reviewPayload = $this->reviewPayloadFromRequest();
        if (! $this->isHaccpOnly($standards)
            && strtolower((string) ($reviewPayload['certification_route'] ?? '')) === 'accredited'
            && ! in_array(strtoupper((string) ($reviewPayload['accreditation_body'] ?? '')), ['IAS', 'SAAC'], true)
        ) {
            return redirect()->back()->withInput()->with('error', 'Select IAS or SAAC when the application review route is Accredited.');
        }
        $reviewPayload = $this->normaliseAccreditationReviewPayload($reviewPayload, $standards);
        $duration = $this->durationService->calculateApplicationReview($client, $standards, $reviewPayload);
        $reviewPayload = $this->applyDurationToReviewPayload($reviewPayload, $duration);
        $payload = [
            'client_id' => $clientId,
            'application_review_number' => $existing['application_review_number'] ?? $this->number('AR', $clientId),
            'certification_application_id' => $application['id'] ?? null,
            'document_number' => $this->nullableText('document_number') ?? 'F 28',
            'revision_number' => $this->nullableText('revision_number') ?? '4',
            'issue_number' => $this->nullableText('issue_number') ?? '2',
            'document_date' => $this->dateOrNull('document_date') ?? '2025-02-01',
            'completeness_status' => (string) $this->request->getPost('completeness_status'),
            'risk_rating' => $this->nullableText('risk_rating') ?? ($reviewPayload['risk_classification'] ?? null),
            'recommendation' => $this->nullableText('recommendation') ?? ($reviewPayload['application_status'] ?? null),
            'md5_duration_days' => $duration['total_days'],
            'iso22003_duration_days' => $this->decimalOrNull('iso22003_duration_days'),
            'integrated_reduction_percent' => $duration['reduction_percent'] ?? $this->decimalOrNull('integrated_reduction_percent'),
            'stage1_days' => $duration['stage1_days'],
            'stage2_days' => $duration['stage2_days'],
            'review_notes' => $reviewPayload['reviewer_comments'] !== '' ? $reviewPayload['reviewer_comments'] : $this->nullableText('review_notes'),
            'review_payload' => json_encode($reviewPayload, JSON_THROW_ON_ERROR),
            'status' => $status,
            'reviewed_at' => $status === 'draft' ? null : date('Y-m-d H:i:s'),
            'technical_reviewer_name' => $this->nullableText('technical_reviewer_name') ?? session()->get('full_name'),
            'technical_review_date' => $this->dateOrNull('technical_review_date'),
            'quality_manager_status' => $this->nullableText('quality_manager_status'),
            'quality_manager_comments' => $this->nullableText('quality_manager_comments'),
            'quality_manager_name' => $this->nullableText('quality_manager_name'),
            'quality_manager_date' => $this->dateOrNull('quality_manager_date'),
            'general_manager_status' => $this->nullableText('general_manager_status'),
            'general_manager_comments' => $this->nullableText('general_manager_comments'),
            'general_manager_name' => $this->nullableText('general_manager_name'),
            'general_manager_date' => $this->dateOrNull('general_manager_date'),
        ];

        if (str_starts_with($status, 'tm_')) {
            $payload['technical_manager_id'] = (int) session()->get('user_id');
        }

        if (str_starts_with($status, 'qm_')) {
            $payload['technical_manager_id'] = $existing['technical_manager_id'] ?? (int) session()->get('user_id');
            $payload['quality_manager_id'] = (int) session()->get('user_id');
        }

        if ($existing === null) {
            $id = (int) $this->reviews->insert($payload);
            $this->auditLogger->record('create', 'application_reviews', 'application_reviews', $id, null, $payload);
        } else {
            $this->reviews->update((int) $existing['id'], $payload);
            $this->auditLogger->record('update', 'application_reviews', 'application_reviews', (int) $existing['id'], $existing, $payload);
        }

        return redirect()->to('/workflow/certification/' . $clientId)->with('success', 'Application review saved.');
    }

    public function proposal(int $clientId)
    {
        $client = $this->tenantClient($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        $proposal = $this->latestProposal($clientId) ?? $this->blankProposal($clientId);
        $review = $this->latestReview($clientId);
        $standards = $this->clientStandardRows($clientId);

        return view('workflow/actions/proposal', [
            'title' => 'Proposal',
            'pageTitle' => 'Proposal',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'proposal' => $proposal,
            'review' => $review,
            'standards' => $standards,
            'payload' => $this->proposalPayload($client, $proposal, $review, $standards),
        ]);
    }

    public function saveProposal(int $clientId)
    {
        $client = $this->tenantClient($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if (! $this->validate([
            'status' => 'required|max_length[40]',
            'currency' => 'required|exact_length[3]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $existing = $this->latestProposal($clientId);
        $review = $this->latestReview($clientId);
        $subtotal = $this->money('certification_fee')
            + $this->money('surveillance1_fee')
            + $this->money('surveillance2_fee')
            + $this->money('training_fee')
            + $this->money('travel_fee')
            + $this->money('accommodation_fee')
            - $this->money('discount_amount');
        $subtotal = max(0, $subtotal);
        $vatPercent = $this->money('vat_percent', 15.00);
        $vatAmount = round($subtotal * ($vatPercent / 100), 2);
        $status = (string) $this->request->getPost('status');
        if (($roleError = $this->workflowRoles->denialReason('proposal_manage')) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'client_id' => $clientId,
            'application_review_id' => $review['id'] ?? null,
            'proposal_number' => $existing['proposal_number'] ?? $this->number('PROP', $clientId),
            'version_number' => 1,
            'status' => $status,
            'proposal_date' => $this->dateOrNull('proposal_date') ?? date('Y-m-d'),
            'client_reference' => $this->nullableText('client_reference'),
            'valid_until' => $this->dateOrNull('valid_until'),
            'certification_fee' => $this->money('certification_fee'),
            'surveillance1_fee' => $this->money('surveillance1_fee'),
            'surveillance2_fee' => $this->money('surveillance2_fee'),
            'training_fee' => $this->money('training_fee'),
            'travel_fee' => $this->money('travel_fee'),
            'accommodation_fee' => $this->money('accommodation_fee'),
            'discount_amount' => $this->money('discount_amount'),
            'vat_percent' => $vatPercent,
            'vat_amount' => $vatAmount,
            'grand_total' => round($subtotal + $vatAmount, 2),
            'currency' => strtoupper((string) $this->request->getPost('currency')),
            'proposal_payload' => json_encode($this->commercialTerms->applyControlledText($this->proposalPayloadFromRequest()), JSON_THROW_ON_ERROR),
            'created_by' => (int) session()->get('user_id'),
            'approved_by' => in_array($status, ['accepted', 'approved'], true) ? (int) session()->get('user_id') : null,
            'approved_at' => in_array($status, ['accepted', 'approved'], true) ? date('Y-m-d H:i:s') : null,
        ];

        if ($existing === null) {
            $id = (int) $this->proposals->insert($payload);
            $this->auditLogger->record('create', 'proposals', 'proposals', $id, null, $payload);
        } else {
            $this->proposals->update((int) $existing['id'], $payload);
            $this->auditLogger->record('update', 'proposals', 'proposals', (int) $existing['id'], $existing, $payload);
        }

        return redirect()->to('/workflow/certification/' . $clientId)->with('success', 'Proposal saved.');
    }

    public function contract(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $proposal = $this->latestProposal($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($proposal === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Create a proposal before creating the contract.');
        }

        $contract = $this->latestContract($clientId) ?? $this->blankContract($clientId);

        return view('workflow/actions/contract', [
            'title' => 'Contract',
            'pageTitle' => 'Contract',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'proposal' => $proposal,
            'contract' => $contract,
            'payload' => $this->contractPayload($client, $proposal, $contract),
        ]);
    }

    public function saveContract(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $proposal = $this->latestProposal($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($proposal === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Create a proposal before creating the contract.');
        }

        if (! $this->validate([
            'status' => 'required|max_length[40]',
            'signed_by_name' => 'permit_empty|max_length[180]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        if (($roleError = $this->workflowRoles->denialReason('contract_manage')) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $existing = $this->latestContract($clientId);
        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'client_id' => $clientId,
            'proposal_id' => (int) $proposal['id'],
            'contract_number' => $existing['contract_number'] ?? $this->number('CON', $clientId),
            'document_number' => $this->nullableText('document_number') ?? 'F 27',
            'revision_number' => $this->nullableText('revision_number') ?? '2',
            'issue_number' => $this->nullableText('issue_number') ?? '2',
            'document_date' => $this->dateOrNull('document_date') ?? '2022-05-15',
            'version_number' => 1,
            'status' => (string) $this->request->getPost('status'),
            'signed_at' => $this->dateTimeOrNull('signed_at'),
            'signed_by_name' => $this->nullableText('signed_by_name'),
            'contract_payload' => json_encode($this->commercialTerms->applyControlledText($this->contractPayloadFromRequest()), JSON_THROW_ON_ERROR),
            'qsi_signatory_name' => $this->nullableText('qsi_signatory_name'),
            'qsi_signatory_date' => $this->dateOrNull('qsi_signatory_date'),
            'client_signatory_name' => $this->nullableText('client_signatory_name'),
            'client_signatory_date' => $this->dateOrNull('client_signatory_date'),
            'created_by' => (int) session()->get('user_id'),
        ];

        if ($existing === null) {
            $id = (int) $this->contracts->insert($payload);
            $this->auditLogger->record('create', 'contracts', 'contracts', $id, null, $payload);
        } else {
            $this->contracts->update((int) $existing['id'], $payload);
            $this->auditLogger->record('update', 'contracts', 'contracts', (int) $existing['id'], $existing, $payload);
        }

        return redirect()->to('/workflow/certification/' . $clientId)->with('success', 'Contract saved.');
    }

    public function auditProgram(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $contract = $this->latestContract($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($contract === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Create a contract before generating the audit program.');
        }

        $review = $this->latestReview($clientId);
        $standards = $this->clientStandardRows($clientId);
        $program = $this->latestProgram($clientId) ?? $this->blankProgram($client, $standards, $review);
        $payload = $this->auditProgramPayload($client, $contract, $review, $program, $standards);
        $events = ! empty($program['id'])
            ? $this->programEvents((int) $program['id'])
            : $this->auditProgramDefaultEvents($clientId, $program, $payload);

        return view('workflow/actions/audit_program', [
            'title' => 'Audit Program',
            'pageTitle' => 'Three-Year Audit Program',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'contract' => $contract,
            'review' => $review,
            'standards' => $standards,
            'program' => $program,
            'payload' => $payload,
            'events' => $events,
        ]);
    }

    public function saveAuditProgram(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $contract = $this->latestContract($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($contract === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Create a contract before generating the audit program.');
        }

        if (! $this->validate([
            'certificate_issue_date' => 'required|valid_date[Y-m-d]',
            'document_number' => 'required|max_length[40]',
            'revision_number' => 'required|max_length[20]',
            'issue_number' => 'required|max_length[20]',
            'document_date' => 'required|valid_date[Y-m-d]',
            'stage1_days' => 'permit_empty|decimal',
            'stage2_days' => 'permit_empty|decimal',
            'surveillance1_days' => 'permit_empty|decimal',
            'surveillance2_days' => 'permit_empty|decimal',
            'recertification_days' => 'permit_empty|decimal',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_program_manage')) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $existing = $this->latestProgram($clientId);
        $issueDate = (string) $this->request->getPost('certificate_issue_date');
        $cycleDates = $this->certificationCycleDates($issueDate);
        $expiryDate = $cycleDates['expiry'];
        $programPayload = $this->postedAuditProgramPayload();
        $programPayload['standard_signature'] = $this->auditProgramStandardSignature($this->clientStandardRows($clientId));
        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'client_id' => $clientId,
            'contract_id' => (int) $contract['id'],
            'program_number' => (string) ($existing['program_number'] ?? $this->number('AP', $clientId)),
            'document_number' => (string) $this->request->getPost('document_number'),
            'revision_number' => (string) $this->request->getPost('revision_number'),
            'issue_number' => (string) $this->request->getPost('issue_number'),
            'document_date' => (string) $this->request->getPost('document_date'),
            'cycle_type' => 'initial',
            'certificate_issue_date' => $issueDate,
            'surveillance_1_due_date' => $cycleDates['surveillance1'],
            'surveillance_2_due_date' => $cycleDates['surveillance2'],
            'certificate_expiry_date' => $expiryDate,
            'surveillance_1_status' => $this->surveillanceCycleStatus($cycleDates['surveillance1'], $existing === null ? null : $this->eventStatus((int) $existing['id'], 'surveillance1')),
            'surveillance_2_status' => $this->surveillanceCycleStatus($cycleDates['surveillance2'], $existing === null ? null : $this->eventStatus((int) $existing['id'], 'surveillance2')),
            'status' => (string) ($this->request->getPost('status') ?: 'planned'),
            'program_payload' => json_encode($programPayload, JSON_THROW_ON_ERROR),
            'prepared_by_name' => $this->nullableText('prepared_by_name'),
            'prepared_date' => $this->dateOrNull('prepared_date'),
            'approved_by_name' => $this->nullableText('approved_by_name'),
            'approved_date' => $this->dateOrNull('approved_date'),
        ];

        if ($existing === null) {
            $payload['created_by'] = (int) session()->get('user_id');
            $programId = (int) $this->programs->insert($payload);
            $this->auditLogger->record('create', 'audit_programs', 'audit_programs', $programId, null, $payload);
        } else {
            $programId = (int) $existing['id'];
            $this->programs->update($programId, $payload);
            $this->auditLogger->record('update', 'audit_programs', 'audit_programs', $programId, $existing, $payload);
        }

        $this->syncProgramEvents($programId, $clientId);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-program')->with('success', $existing === null ? 'Three-year audit program generated.' : 'Audit program updated.');
    }

    public function appointments(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($program === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Generate an audit program before appointing auditors.');
        }

        return view('workflow/actions/appointments', [
            'title' => 'Auditor Appointments',
            'pageTitle' => 'Auditor Appointments',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'events' => $this->programEvents((int) $program['id']),
            'selectedEventId' => $this->intQueryOrNull('event_id'),
            'personnel' => $this->approvedPersonnel(),
            'appointments' => $this->appointmentRows((int) $program['id']),
        ]);
    }

    public function saveAppointment(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($program === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Generate an audit program before appointing auditors.');
        }

        if (! $this->validate([
            'audit_event_id' => 'required|integer',
            'personnel_id' => 'required|integer',
            'appointment_role' => 'required|max_length[60]',
            'status' => 'required|max_length[40]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $eventId = (int) $this->request->getPost('audit_event_id');
        if (! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->back()->with('error', 'Selected audit event does not belong to this client.');
        }

        if (($roleError = $this->workflowRoles->denialReason('appointment_manage', $eventId)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if ($event !== null && ($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->withInput()->with('error', $lockMessage);
        }

        $personnelId = (int) $this->request->getPost('personnel_id');
        $appointmentRole = (string) $this->request->getPost('appointment_role');
        $appointmentStatus = (string) $this->request->getPost('status');
        $competenceConfirmed = $this->request->getPost('competence_confirmed') === '1';
        $impartialityConfirmed = $this->request->getPost('impartiality_confirmed') === '1';
        $conflictOfInterest = $this->request->getPost('conflict_of_interest') === '1';

        if ($this->isActiveAppointmentStatus($appointmentStatus)) {
            $errors = $this->appointmentGateFailures($clientId, $personnelId, $appointmentRole, $competenceConfirmed, $impartialityConfirmed, $conflictOfInterest);
            if ($errors !== []) {
                return redirect()->back()->withInput()->with('error', implode(' ', $errors));
            }
        }

        $payload = [
            'audit_event_id' => $eventId,
            'personnel_id' => $personnelId,
            'appointment_role' => $appointmentRole,
            'appointed_by' => (int) session()->get('user_id'),
            'appointed_at' => date('Y-m-d H:i:s'),
            'status' => $appointmentStatus,
            'conflict_check_json' => json_encode([
                'competence_confirmed' => $competenceConfirmed,
                'impartiality_confirmed' => $impartialityConfirmed,
                'conflict_of_interest' => $conflictOfInterest,
                'notes' => $this->nullableText('conflict_notes'),
            ], JSON_THROW_ON_ERROR),
        ];

        $existing = $this->appointments
            ->where('audit_event_id', $payload['audit_event_id'])
            ->where('personnel_id', $payload['personnel_id'])
            ->where('appointment_role', $payload['appointment_role'])
            ->first();

        if ($existing === null) {
            $id = (int) $this->appointments->insert($payload);
            $this->auditLogger->record('create', 'auditor_appointments', 'auditor_appointments', $id, null, $payload);
        } else {
            $id = (int) $existing['id'];
            $this->appointments->update((int) $existing['id'], $payload);
            $this->auditLogger->record('update', 'auditor_appointments', 'auditor_appointments', (int) $existing['id'], $existing, $payload);
        }

        if ($this->isActiveAppointmentStatus($appointmentStatus)) {
            $this->notifications->notifyAuditorAppointment($id);
        }

        return redirect()->to('/workflow/certification/' . $clientId . '/appointments')->with('success', 'Auditor appointment saved.');
    }

    public function deleteAppointment(int $clientId, int $appointmentId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $appointment = $this->appointments->find($appointmentId);

        if ($client === null || $program === null || $appointment === null || ! $this->eventBelongsToProgram((int) $appointment['audit_event_id'], (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Appointment not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('appointment_manage', (int) $appointment['audit_event_id'])) !== null) {
            return redirect()->back()->with('error', $roleError);
        }

        $this->appointments->delete($appointmentId);
        $this->auditLogger->record('delete', 'auditor_appointments', 'auditor_appointments', $appointmentId, $appointment, null);

        return redirect()->to('/workflow/certification/' . $clientId . '/appointments')->with('success', 'Auditor appointment removed.');
    }

    public function auditPlan(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($program === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Generate an audit program before preparing audit plans.');
        }

        $selectedEventId = $this->intQueryOrNull('event_id');
        if ($selectedEventId !== null) {
            $selectedEvent = $this->events->find($selectedEventId);
            if ($selectedEvent !== null && $this->eventBelongsToProgram($selectedEventId, (int) $program['id']) && ($lockMessage = $this->surveillanceLockMessage($selectedEvent)) !== null) {
                return redirect()->to('/workflow/certification/' . $clientId)->with('error', $lockMessage);
            }
        }

        return view('workflow/actions/audit_plan', [
            'title' => 'Audit Plan',
            'pageTitle' => 'Audit Plan',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'events' => $this->programEvents((int) $program['id']),
            'selectedEventId' => $selectedEventId,
            'plans' => $this->planRows((int) $program['id']),
            'items' => $this->planItemRows((int) $program['id']),
            'auditors' => $this->appointmentPersonnel((int) $program['id']),
        ]);
    }

    public function saveAuditPlan(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($program === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Generate an audit program before preparing audit plans.');
        }

        if (! $this->validate([
            'audit_event_id' => 'required|integer',
            'status' => 'required|max_length[40]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $eventId = (int) $this->request->getPost('audit_event_id');
        if (! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->back()->with('error', 'Selected audit event does not belong to this client.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_plan_manage', $eventId)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if ($event !== null && ($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->withInput()->with('error', $lockMessage);
        }

        $status = (string) $this->request->getPost('status');
        $existing = $this->plans->where('audit_event_id', $eventId)->orderBy('id', 'DESC')->first();
        $payload = [
            'audit_event_id' => $eventId,
            'plan_number' => $existing['plan_number'] ?? $this->number('PLAN', $clientId),
            'version_number' => 1,
            'status' => $status,
            'prepared_by' => (int) session()->get('user_id'),
            'approved_by' => in_array($status, ['approved', 'issued'], true) ? (int) session()->get('user_id') : null,
            'approved_at' => in_array($status, ['approved', 'issued'], true) ? date('Y-m-d H:i:s') : null,
        ];

        if ($existing === null) {
            $id = (int) $this->plans->insert($payload);
            $this->auditLogger->record('create', 'audit_plans', 'audit_plans', $id, null, $payload);
        } else {
            $this->plans->update((int) $existing['id'], $payload);
            $this->auditLogger->record('update', 'audit_plans', 'audit_plans', (int) $existing['id'], $existing, $payload);
        }

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-plan')->with('success', 'Audit plan saved.');
    }

    public function addAuditPlanItem(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null || $program === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit program not found.');
        }

        if (! $this->validate([
            'audit_plan_id' => 'required|integer',
            'audit_date' => 'required|valid_date[Y-m-d]',
            'start_time' => 'required',
            'end_time' => 'required',
            'activity_type' => 'required|max_length[80]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $planId = (int) $this->request->getPost('audit_plan_id');
        if (! $this->planBelongsToProgram($planId, (int) $program['id'])) {
            return redirect()->back()->with('error', 'Selected audit plan does not belong to this client.');
        }

        $event = $this->eventForPlan($planId);
        if ($event !== null && ($roleError = $this->workflowRoles->denialReason('audit_plan_manage', (int) $event['id'])) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        if ($event !== null && ($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->withInput()->with('error', $lockMessage);
        }

        $payload = [
            'audit_plan_id' => $planId,
            'audit_date' => (string) $this->request->getPost('audit_date'),
            'start_time' => (string) $this->request->getPost('start_time'),
            'end_time' => (string) $this->request->getPost('end_time'),
            'activity_type' => (string) $this->request->getPost('activity_type'),
            'department' => $this->nullableText('department'),
            'process_name' => $this->nullableText('process_name'),
            'clauses' => $this->nullableText('clauses'),
            'auditor_personnel_id' => $this->intOrNull('auditor_personnel_id'),
            'notes' => $this->nullableText('notes'),
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
        ];

        $id = (int) $this->planItems->insert($payload);
        $this->auditLogger->record('create', 'audit_plans', 'audit_plan_items', $id, null, $payload);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-plan')->with('success', 'Audit plan item added.');
    }

    public function deleteAuditPlanItem(int $clientId, int $itemId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $item = $this->planItems->find($itemId);

        if ($client === null || $program === null || $item === null || ! $this->planBelongsToProgram((int) $item['audit_plan_id'], (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit plan item not found.');
        }

        $event = $this->eventForPlan((int) $item['audit_plan_id']);
        if ($event !== null && ($roleError = $this->workflowRoles->denialReason('audit_plan_manage', (int) $event['id'])) !== null) {
            return redirect()->back()->with('error', $roleError);
        }

        $this->planItems->delete($itemId);
        $this->auditLogger->record('delete', 'audit_plans', 'audit_plan_items', $itemId, $item, null);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-plan')->with('success', 'Audit plan item removed.');
    }

    public function executeAudit(int $clientId, int $eventId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null || $program === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit event not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', $lockMessage);
        }

        $clauses = $this->clausesForClient($clientId);
        $report = $this->ensureReport($eventId);
        $auditTeam = $this->eventTeamRows($eventId);
        $planItems = $this->eventPlanItemRows($eventId);
        $this->ensureConformitySections((int) $report['id'], $clauses, $client, $event, $planItems, $auditTeam);

        return view('workflow/actions/audit_execute', [
            'title' => 'Audit Execution',
            'pageTitle' => 'Audit Execution',
            'pageSubtitle' => $client['company'] . ' - ' . str_replace('_', ' ', $event['event_type']),
            'client' => $client,
            'event' => $event,
            'report' => $report,
            'sections' => $this->reportSectionRows((int) $report['id']),
            'ncrs' => $this->ncrRows($eventId),
            'clauses' => $clauses,
            'clientStandards' => $this->clientStandardRows($clientId),
            'auditTeam' => $auditTeam,
            'planItems' => $planItems,
            'smartConformityNotes' => $this->smartConformityNotes($client, $event, $clauses, $planItems, $auditTeam),
        ]);
    }

    public function auditEventFile(int $clientId, int $eventId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null || $program === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit event not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        $report = $this->ensureReport($eventId);
        $auditTeam = $this->eventTeamRows($eventId);
        $planItems = $this->eventPlanItemRows($eventId);
        $this->ensureConformitySections((int) $report['id'], $this->clausesForClient($clientId), $client, $event, $planItems, $auditTeam);
        $technicalReview = $this->technicalReviewForEvent($eventId);

        return view('workflow/audit_event_file', [
            'title' => 'Audit Stage File',
            'pageTitle' => ucwords(str_replace('_', ' ', (string) $event['event_type'])) . ' File',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'program' => $program,
            'event' => $event,
            'appointments' => $auditTeam,
            'planItems' => $planItems,
            'report' => $report,
            'sections' => $this->reportSectionRows((int) $report['id']),
            'ncrs' => $this->ncrRows($eventId),
            'capas' => $this->capaRowsForEvent($eventId),
            'technicalReview' => $technicalReview,
            'decision' => $technicalReview === null ? null : $this->decisionForReview((int) $technicalReview['id']),
        ]);
    }

    public function saveFinding(int $clientId, int $eventId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null || $program === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit event not found.');
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->withInput()->with('error', $lockMessage);
        }

        if (! $this->validate([
            'section_title' => 'required|max_length[255]',
            'section_content' => 'required',
            'section_key' => 'required|max_length[120]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $report = $this->ensureReport($eventId);
        $payload = [
            'report_draft_id' => (int) $report['id'],
            'clause_library_id' => $this->intOrNull('clause_library_id'),
            'section_key' => (string) $this->request->getPost('section_key'),
            'section_title' => (string) $this->request->getPost('section_title'),
            'section_content' => (string) $this->request->getPost('section_content'),
            'source_type' => 'manual_note',
            'auditor_confirmed' => 1,
            'confirmed_by_user_id' => (int) session()->get('user_id'),
            'confirmed_at' => date('Y-m-d H:i:s'),
            'confirmation_note' => 'Manual audit note entered by auditor.',
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
        ];

        $id = (int) $this->reportSections->insert($payload);
        $this->auditLogger->record('create', 'reports', 'report_sections', $id, null, $payload);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-events/' . $eventId . '/execute')->with('success', 'Finding saved.');
    }

    public function autosaveConformityNote(int $clientId, int $eventId, int $sectionId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $section = $this->reportSections->find($sectionId);

        if ($client === null || $program === null || $section === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok' => false,
                'message' => 'Conformity note not found.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return $this->jsonWorkflowDenied($roleError);
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return $this->response->setStatusCode(423)->setJSON([
                'ok' => false,
                'message' => $lockMessage,
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $report = $this->reports->find((int) $section['report_draft_id']);
        if ($report === null || (int) $report['audit_event_id'] !== $eventId || $section['section_key'] !== 'conformity') {
            return $this->response->setStatusCode(404)->setJSON([
                'ok' => false,
                'message' => 'Conformity note not found.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $content = trim((string) $this->request->getPost('section_content'));
        if ($content === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false,
                'message' => 'Conformity note cannot be empty.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $payload = [
            'section_content' => $content,
            'source_type' => 'manual_edit',
            'auditor_confirmed' => 0,
            'confirmed_by_user_id' => null,
            'confirmed_at' => null,
            'confirmation_note' => null,
        ];
        $this->reportSections->update($sectionId, $payload);
        $this->auditLogger->record('update', 'reports', 'report_sections', $sectionId, $section, $payload);

        return $this->response->setJSON([
            'ok' => true,
            'message' => 'Saved',
            'csrfToken' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function confirmReportSection(int $clientId, int $eventId, int $sectionId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $section = $this->reportSections->find($sectionId);

        if ($client === null || $program === null || $section === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Report section not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->back()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->with('error', $lockMessage);
        }

        $report = $this->reports->find((int) $section['report_draft_id']);
        if ($report === null || (int) $report['audit_event_id'] !== $eventId || $section['section_key'] !== 'conformity') {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Conformity section not found.');
        }

        if (trim((string) $section['section_content']) === '') {
            return redirect()->back()->with('error', 'Conformity note cannot be confirmed while empty.');
        }

        $payload = [
            'auditor_confirmed' => 1,
            'confirmed_by_user_id' => (int) session()->get('user_id'),
            'confirmed_at' => date('Y-m-d H:i:s'),
            'confirmation_note' => $this->nullableText('confirmation_note') ?: 'Auditor confirmed the sampled evidence and conformity note.',
        ];

        $this->reportSections->update($sectionId, $payload);
        $this->auditLogger->record('update', 'reports', 'report_sections', $sectionId, $section, $payload);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-events/' . $eventId . '/execute')->with('success', 'Conformity note confirmed by auditor.');
    }

    public function generateConformityDraft(int $clientId, int $eventId, int $clauseId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $event = $this->events->find($eventId);

        if ($client === null || $program === null || $event === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok' => false,
                'message' => 'Audit event not found.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return $this->jsonWorkflowDenied($roleError);
        }

        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return $this->response->setStatusCode(423)->setJSON([
                'ok' => false,
                'message' => $lockMessage,
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $clause = null;
        foreach ($this->clausesForClient($clientId) as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $clauseId) {
                $clause = $candidate;
                break;
            }
        }

        if ($clause === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok' => false,
                'message' => 'Clause not found for this client.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $package = $this->contentEngine->conformitySection(
            $client,
            $event,
            $clause,
            $this->eventPlanItemRows($eventId),
            $this->eventTeamRows($eventId)
        );
        $draft = [
            'source' => $package['source_type'],
            'text' => $package['content'],
        ];

        return $this->response->setJSON([
            'ok' => true,
            'source' => $draft['source'],
            'text' => $draft['text'],
            'csrfToken' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function deleteFinding(int $clientId, int $eventId, int $sectionId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $section = $this->reportSections->find($sectionId);

        if ($client === null || $program === null || $section === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Finding not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->back()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->with('error', $lockMessage);
        }

        $report = $this->reports->find((int) $section['report_draft_id']);
        if ($report === null || (int) $report['audit_event_id'] !== $eventId) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Finding not found.');
        }

        $this->reportSections->delete($sectionId);
        $this->auditLogger->record('delete', 'reports', 'report_sections', $sectionId, $section, null);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-events/' . $eventId . '/execute')->with('success', 'Finding removed.');
    }

    public function saveNcr(int $clientId, int $eventId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null || $program === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit event not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->withInput()->with('error', $lockMessage);
        }

        if (! $this->validate([
            'requirement' => 'required',
            'finding' => 'required',
            'objective_evidence' => 'required',
            'classification' => 'required|max_length[40]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'audit_event_id' => $eventId,
            'clause_library_id' => $this->intOrNull('ncr_clause_library_id'),
            'ncr_number' => $this->number('NCR', $clientId),
            'requirement' => (string) $this->request->getPost('requirement'),
            'finding' => (string) $this->request->getPost('finding'),
            'objective_evidence' => (string) $this->request->getPost('objective_evidence'),
            'classification' => (string) $this->request->getPost('classification'),
            'responsible_person' => $this->nullableText('responsible_person'),
            'target_date' => $this->dateOrNull('target_date'),
            'status' => 'open',
            'created_by' => (int) session()->get('user_id'),
        ];

        $id = (int) $this->ncrs->insert($payload);
        $this->auditLogger->record('create', 'ncrs', 'ncrs', $id, null, $payload);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-events/' . $eventId . '/execute')->with('success', 'NCR raised.');
    }

    public function closeNcr(int $clientId, int $eventId, int $ncrId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $ncr = $this->ncrs->find($ncrId);

        if ($client === null || $program === null || $ncr === null || (int) $ncr['audit_event_id'] !== $eventId || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'NCR not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->withInput()->with('error', $lockMessage);
        }

        $draft = $this->narratives->ncrCorrectionSet($ncr, $client);
        $payload = [
            'correction' => $this->nullableText('correction') ?: $draft['correction'],
            'root_cause' => $this->nullableText('root_cause') ?: $draft['root_cause'],
            'corrective_action' => $this->nullableText('corrective_action') ?: $draft['corrective_action'],
            'verification' => $this->nullableText('verification') ?: $draft['verification'],
            'closure_notes' => $this->nullableText('closure_notes') ?: $draft['closure_notes'],
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
        ];

        $this->ncrs->update($ncrId, $payload);
        $this->auditLogger->record('update', 'ncrs', 'ncrs', $ncrId, $ncr, $payload);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-events/' . $eventId . '/execute')->with('success', 'NCR closed.');
    }

    public function saveCapa(int $clientId, int $eventId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $ncrId = (int) $this->request->getPost('ncr_id');
        $ncr = $this->ncrs->find($ncrId);

        if ($client === null || $program === null || $ncr === null || (int) $ncr['audit_event_id'] !== $eventId || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'NCR not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->withInput()->with('error', $lockMessage);
        }

        if (! $this->validate([
            'ncr_id' => 'required|is_natural_no_zero',
            'issue' => 'required',
            'root_cause' => 'permit_empty',
            'corrective_action' => 'required',
            'target_date' => 'permit_empty|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $draft = $this->narratives->ncrCorrectionSet($ncr, $client);
        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'ncr_id' => $ncrId,
            'capa_number' => $this->number('CAPA', $clientId),
            'source' => 'audit_ncr',
            'issue' => (string) $this->request->getPost('issue'),
            'immediate_correction' => $this->nullableText('immediate_correction') ?: $draft['correction'],
            'root_cause' => $this->nullableText('root_cause') ?: $draft['root_cause'],
            'corrective_action' => (string) $this->request->getPost('corrective_action'),
            'preventive_action' => $this->nullableText('preventive_action') ?: $draft['preventive_action'],
            'responsible_person' => $this->nullableText('responsible_person'),
            'target_date' => $this->dateOrNull('target_date'),
            'evidence_reference' => $this->nullableText('evidence_reference') ?: $draft['evidence_reference'],
            'status' => 'open',
            'created_by' => (int) session()->get('user_id'),
        ];

        $id = (int) $this->capas->insert($payload);
        $this->auditLogger->record('create', 'capas', 'capas', $id, null, $payload);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-events/' . $eventId . '/file')->with('success', 'CAPA saved.');
    }

    public function closeCapa(int $clientId, int $eventId, int $capaId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $capa = $this->capas->find($capaId);
        $ncr = $capa === null || $capa['ncr_id'] === null ? null : $this->ncrs->find((int) $capa['ncr_id']);

        if ($client === null || $program === null || $capa === null || $ncr === null || (int) $ncr['audit_event_id'] !== $eventId || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'CAPA not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $event = $this->events->find($eventId);
        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->back()->withInput()->with('error', $lockMessage);
        }

        if (! $this->validate([
            'verification' => 'required',
            'effectiveness' => 'required',
            'closure_notes' => 'permit_empty',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $payload = [
            'verification' => (string) $this->request->getPost('verification'),
            'effectiveness' => (string) $this->request->getPost('effectiveness'),
            'evidence_reference' => $this->nullableText('evidence_reference') ?: ($capa['evidence_reference'] ?? null),
            'closure_notes' => $this->nullableText('closure_notes'),
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
        ];

        $this->capas->update($capaId, $payload);
        $this->auditLogger->record('update', 'capas', 'capas', $capaId, $capa, $payload);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-events/' . $eventId . '/file')->with('success', 'CAPA closed.');
    }

    public function completeAuditEvent(int $clientId, int $eventId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);
        $event = $this->events->find($eventId);

        if ($client === null || $program === null || $event === null || ! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit event not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('audit_execute', $eventId)) !== null) {
            return redirect()->back()->with('error', $roleError);
        }

        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', $lockMessage);
        }

        $completionFailures = $this->auditCompletionGateFailures($eventId);
        if ($completionFailures !== []) {
            return redirect()->back()->with('error', implode(' ', $completionFailures));
        }

        $payload = [
            'actual_start_date' => $event['actual_start_date'] ?: $event['planned_start_date'],
            'actual_end_date' => $event['actual_end_date'] ?: $event['planned_end_date'],
            'status' => 'completed',
        ];

        $this->events->update($eventId, $payload);
        $report = $this->ensureReport($eventId);
        $reportPayload = [
            'status' => 'submitted',
            'submitted_at' => $report['submitted_at'] ?? date('Y-m-d H:i:s'),
        ];
        $this->reports->update((int) $report['id'], $reportPayload);
        if (in_array($event['event_type'], ['surveillance1', 'surveillance2'], true)) {
            $this->programs->update((int) $program['id'], [
                $event['event_type'] === 'surveillance1' ? 'surveillance_1_status' : 'surveillance_2_status' => 'completed',
            ]);
        }
        $this->auditLogger->record('update', 'audit_programs', 'audit_events', $eventId, $event, $payload);
        $this->auditLogger->record('update', 'reports', 'report_drafts', (int) $report['id'], $report, $reportPayload);

        return redirect()->to('/workflow/certification/' . $clientId . '/audit-events/' . $eventId . '/execute')->with('success', 'Audit event marked completed.');
    }

    public function technicalReview(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($program === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Generate an audit program before technical review.');
        }

        $events = $this->programEvents((int) $program['id']);
        $requestedEventId = $this->intQueryOrNull('event_id');
        $event = $requestedEventId === null ? $this->finalAuditEvent($events) : $this->eventFromList($events, $requestedEventId);
        $review = $event === null ? null : $this->technicalReviewForEvent((int) $event['id']);

        $selectedEvents = $event === null ? [] : [$event];

        return view('workflow/actions/technical_review', [
            'title' => 'Technical File Review',
            'pageTitle' => 'Technical File Review',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'program' => $program,
            'events' => $events,
            'selectedEvent' => $event,
            'review' => $review,
            'reviewers' => $this->approvedPersonnel(),
            'openNcrCount' => $this->ncrCountForEvents($selectedEvents, false),
            'totalNcrCount' => $this->ncrCountForEvents($selectedEvents, true),
            'reportCount' => $this->reportCountForEvents($selectedEvents),
            'reportRows' => $this->reportRowsForEvents($selectedEvents),
            'ncrRows' => $this->ncrRowsForEvents($selectedEvents),
            'auditTeam' => $event === null ? [] : $this->eventTeamRows((int) $event['id']),
            'planItems' => $event === null ? [] : $this->eventPlanItemRows((int) $event['id']),
            'standards' => $this->clientStandardRows($clientId),
        ]);
    }

    public function saveTechnicalReview(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $program = $this->latestProgram($clientId);

        if ($client === null || $program === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Workflow file not found.');
        }

        if (! $this->validate([
            'audit_event_id' => 'required|is_natural_no_zero',
            'reviewer_personnel_id' => 'required|is_natural_no_zero',
            'status' => 'required|max_length[40]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $eventId = (int) $this->request->getPost('audit_event_id');
        if (! $this->eventBelongsToProgram($eventId, (int) $program['id'])) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit event not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('technical_review', $eventId, (int) $this->request->getPost('reviewer_personnel_id'))) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $status = (string) $this->request->getPost('status');
        if (in_array($status, ['approved', 'completed'], true)) {
            $gateFailures = $this->technicalReviewGateFailures(
                $clientId,
                $eventId,
                (int) $this->request->getPost('reviewer_personnel_id')
            );
            if ($gateFailures !== []) {
                return redirect()->back()->withInput()->with('error', implode(' ', $gateFailures));
            }
        }

        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'audit_event_id' => $eventId,
            'reviewer_personnel_id' => (int) $this->request->getPost('reviewer_personnel_id'),
            'checklist_payload' => json_encode([
                'review_notes' => $this->nullableText('review_notes'),
                'audit_result' => $this->nullableText('audit_result'),
                'audit_category_nace' => $this->nullableText('audit_category_nace'),
                'transfer_status' => $this->nullableText('transfer_status'),
                'accredited_scope_ias_saac' => $this->nullableText('accredited_scope_ias_saac'),
                'accredited_scope_fssc' => $this->nullableText('accredited_scope_fssc'),
                'ias_saac_registration_required' => $this->nullableText('ias_saac_registration_required'),
                'complaints_received' => $this->nullableText('complaints_received'),
                'client_management_system_review' => $this->nullableText('client_management_system_review'),
                'outstanding_items' => $this->nullableText('outstanding_items'),
                'certificate_authorization' => $this->nullableText('certificate_authorization'),
                'authorization_date' => $this->dateOrNull('authorization_date'),
                'checklist_rows' => $this->checklistRowsFromPost('technical_checklist'),
                'created_from' => 'technical_review_form',
            ], JSON_THROW_ON_ERROR),
            'competency_confirmed' => $this->checkbox('competency_confirmed'),
            'duration_confirmed' => $this->checkbox('duration_confirmed'),
            'application_confirmed' => $this->checkbox('application_confirmed'),
            'reports_confirmed' => $this->checkbox('reports_confirmed'),
            'ncr_capa_confirmed' => $this->checkbox('ncr_capa_confirmed'),
            'scope_dates_confirmed' => $this->checkbox('scope_dates_confirmed'),
            'impartiality_confirmed' => $this->checkbox('impartiality_confirmed'),
            'recommendation' => $this->nullableText('recommendation'),
            'status' => $status,
            'reviewed_at' => in_array($status, ['approved', 'returned', 'rejected'], true) ? date('Y-m-d H:i:s') : null,
        ];

        $existing = $this->technicalReviewForEvent($eventId);
        if ($existing === null) {
            $id = (int) $this->technicalReviews->insert($payload);
            $this->auditLogger->record('create', 'technical_reviews', 'technical_reviews', $id, null, $payload);
        } else {
            $this->technicalReviews->update((int) $existing['id'], $payload);
            $this->auditLogger->record('update', 'technical_reviews', 'technical_reviews', (int) $existing['id'], $existing, $payload);
        }

        return redirect()->to('/workflow/certification/' . $clientId)->with('success', 'Technical file review saved.');
    }

    public function decision(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $eventId = $this->intQueryOrNull('event_id');
        $review = $eventId === null ? $this->latestTechnicalReviewForClient($clientId) : $this->technicalReviewForEvent($eventId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if ($review === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Complete technical file review before certification decision.');
        }

        $reviewEvent = $this->events->find((int) $review['audit_event_id']);
        $reviewEvents = $reviewEvent === null ? [] : [$reviewEvent];

        return view('workflow/actions/decision', [
            'title' => 'Certification Decision',
            'pageTitle' => 'Certification Decision',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'technicalReview' => $review,
            'reviewEvent' => $reviewEvent,
            'decision' => $this->decisionForReview((int) $review['id']),
            'decisionMakers' => $this->approvedPersonnel(),
            'eventId' => $eventId,
            'standards' => $this->clientStandardRows($clientId),
            'openNcrCount' => $this->ncrCountForEvents($reviewEvents, false),
            'totalNcrCount' => $this->ncrCountForEvents($reviewEvents, true),
            'certificates' => $this->certificateRows($clientId),
        ]);
    }

    public function saveDecision(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $eventId = $this->intOrNull('event_id');
        $review = $eventId === null ? $this->latestTechnicalReviewForClient($clientId) : $this->technicalReviewForEvent($eventId);

        if ($client === null || $review === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Technical review is required first.');
        }

        if (! $this->validate([
            'decision_maker_personnel_id' => 'required|is_natural_no_zero',
            'decision' => 'required|max_length[40]',
            'status' => 'required|max_length[40]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $status = (string) $this->request->getPost('status');
        if ($this->request->getPost('gm_approved') === '1') {
            $status = 'gm_approved';
        }

        $decisionValue = (string) $this->request->getPost('decision');
        $decisionStage = $status === 'gm_approved' ? 'gm_approval' : 'decision';
        $decisionPersonnelId = $status === 'gm_approved' ? null : (int) $this->request->getPost('decision_maker_personnel_id');
        if (($roleError = $this->workflowRoles->denialReason($decisionStage, (int) ($review['audit_event_id'] ?? 0), $decisionPersonnelId)) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        if (in_array($status, ['approved', 'decided', 'gm_approved'], true) || in_array($decisionValue, ['approved', 'granted'], true)) {
            $gateFailures = $this->decisionGateFailures(
                $review,
                (int) $this->request->getPost('decision_maker_personnel_id'),
                $decisionValue,
                $status
            );
            if ($gateFailures !== []) {
                return redirect()->back()->withInput()->with('error', implode(' ', $gateFailures));
            }
        }

        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'technical_review_id' => (int) $review['id'],
            'decision_maker_personnel_id' => (int) $this->request->getPost('decision_maker_personnel_id'),
            'decision' => $decisionValue,
            'reason' => $this->nullableText('reason'),
            'electronic_signature' => $this->nullableText('electronic_signature'),
            'decision_payload' => json_encode([
                'application_id' => $this->nullableText('application_id'),
                'standard_category_nace' => $this->nullableText('standard_category_nace'),
                'certificate_number' => $this->nullableText('certificate_number'),
                'certificate_decision_date' => $this->dateOrNull('certificate_decision_date'),
                'declaration_confirmed' => $this->checkbox('declaration_confirmed'),
                'declaration_text' => $this->nullableText('declaration_text'),
                'technical_reviewer_name' => $this->nullableText('technical_reviewer_name'),
                'technical_reviewer_date' => $this->dateOrNull('technical_reviewer_date'),
                'certification_decision_maker_name' => $this->nullableText('certification_decision_maker_name'),
                'certification_decision_maker_date' => $this->dateOrNull('certification_decision_maker_date'),
                'checklist_rows' => $this->checklistRowsFromPost('decision_checklist'),
            ], JSON_THROW_ON_ERROR),
            'decided_at' => date('Y-m-d H:i:s'),
            'status' => $status,
            'gm_approved_by_user_id' => $status === 'gm_approved' ? (int) session()->get('user_id') : null,
            'gm_approval_notes' => $status === 'gm_approved' ? $this->nullableText('gm_approval_notes') : null,
            'gm_approved_at' => $status === 'gm_approved' ? date('Y-m-d H:i:s') : null,
        ];

        $existing = $this->decisionForReview((int) $review['id']);
        if ($existing === null) {
            $id = (int) $this->decisions->insert($payload);
            $this->auditLogger->record('create', 'certification_decisions', 'certification_decisions', $id, null, $payload);
        } else {
            $this->decisions->update((int) $existing['id'], $payload);
            $this->auditLogger->record('update', 'certification_decisions', 'certification_decisions', (int) $existing['id'], $existing, $payload);
        }

        return redirect()->to('/workflow/certification/' . $clientId)->with('success', 'Certification decision saved.');
    }

    public function certificates(int $clientId)
    {
        $client = $this->tenantClient($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        $issueDate = date('Y-m-d');

        return view('workflow/actions/certificates', [
            'title' => 'Certificates',
            'pageTitle' => 'Certificates',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'decision' => $this->latestDecisionForClient($clientId),
            'program' => $this->latestProgram($clientId),
            'standards' => $this->clientStandardRows($clientId),
            'certificates' => $this->certificateRows($clientId),
            'defaultIssueDate' => $issueDate,
            'cycleDates' => $this->certificationCycleDates($issueDate),
        ]);
    }

    public function generateCertificates(int $clientId)
    {
        $client = $this->tenantClient($clientId);
        $decision = $this->latestDecisionForClient($clientId);

        if ($client === null || $decision === null || ($decision['status'] ?? '') !== 'gm_approved' || ! in_array($decision['decision'], ['granted', 'approved'], true)) {
            return redirect()->to('/workflow/certification/' . $clientId . '/certificates')->with('error', 'GM-approved granted decision is required before certificate issue.');
        }

        if (($roleError = $this->workflowRoles->denialReason('certificate_issue')) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $issueDate = (string) $this->request->getPost('issue_date') ?: date('Y-m-d');
        $cycleDates = $this->certificationCycleDates($issueDate);
        $expiryDate = $cycleDates['expiry'];
        $this->updateAuditProgramCycleDates($clientId, $cycleDates);
        $this->clients->update($clientId, [
            'certificate_issue_date' => $issueDate,
            'certificate_expiry_date' => $expiryDate,
            'initial_certification_date' => $this->dateOrNull('initial_certification_date') ?: $issueDate,
        ]);

        $created = 0;
        foreach ($this->clientStandardRows($clientId) as $standard) {
            $standardId = (int) $standard['standard_id'];
            if ($this->certificateForClientStandard($clientId, $standardId) !== null) {
                continue;
            }

            $number = $this->nextCertificateNumber((string) $standard['standard_code']);
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $number)) . '-' . random_int(1000, 9999);
            $payload = [
                'tenant_id' => (int) session()->get('tenant_id'),
                'client_id' => $clientId,
                'certification_decision_id' => (int) $decision['id'],
                'certificate_number' => $number,
                'standard_id' => $standardId,
                'scope' => (string) ($standard['scope'] ?: $client['scope'] ?: 'Certification scope to be confirmed.'),
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'initial_certification_date' => $this->dateOrNull('initial_certification_date') ?: $issueDate,
                'status' => 'active',
                'public_slug' => $slug,
                'qr_payload' => site_url('certificates/verify/' . $slug),
            ];

            $id = (int) $this->certificates->insert($payload);
            $this->auditLogger->record('create', 'certificates', 'certificates', $id, null, $payload);
            $created++;
        }

        if ($created > 0) {
            $first = $this->certificateRows($clientId)[0] ?? null;
            $this->clients->update($clientId, [
                'certification_status' => 'certified',
                'certificate_number' => $first['certificate_number'] ?? $client['certificate_number'],
                'certificate_expiry_date' => $expiryDate,
                'certificate_issue_date' => $issueDate,
                'initial_certification_date' => $this->dateOrNull('initial_certification_date') ?: $issueDate,
            ]);
            $this->notifyTenantUsers(
                'Certificates issued',
                $created . ' certificate record(s) were issued for ' . $client['company'] . '.',
                'certificates',
                $clientId
            );
        }

        return redirect()->to('/workflow/certification/' . $clientId . '/certificates')->with('success', $created . ' certificate record(s) issued.');
    }

    public function feedback(int $clientId)
    {
        $client = $this->tenantClient($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        return view('workflow/actions/feedback', [
            'title' => 'Client Feedback',
            'pageTitle' => 'Client Feedback',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'program' => $this->latestProgram($clientId),
            'certificates' => $this->certificateRows($clientId),
            'feedbackRows' => $this->feedbackRows($clientId),
        ]);
    }

    public function saveFeedback(int $clientId)
    {
        $client = $this->tenantClient($clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if (($roleError = $this->workflowRoles->denialReason('feedback_manage')) !== null) {
            return redirect()->back()->withInput()->with('error', $roleError);
        }

        $program = $this->latestProgram($clientId);
        $certificateId = $this->intOrNull('certificate_id');
        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'client_id' => $clientId,
            'audit_program_id' => $program['id'] ?? null,
            'certificate_id' => $certificateId,
            'contact_name' => $this->nullableText('contact_name'),
            'contact_email' => $this->nullableText('contact_email'),
            'submitted_at' => $this->dateTimeOrNow('submitted_at'),
            'overall_rating' => $this->intOrNull('overall_rating'),
            'communication_rating' => $this->intOrNull('communication_rating'),
            'auditor_rating' => $this->intOrNull('auditor_rating'),
            'report_quality_rating' => $this->intOrNull('report_quality_rating'),
            'comments' => $this->nullableText('comments'),
            'improvement_suggestion' => $this->nullableText('improvement_suggestion'),
            'status' => (string) ($this->request->getPost('status') ?: 'submitted'),
            'created_by' => (int) session()->get('user_id'),
        ];

        $id = (int) $this->feedbackModel->insert($payload);
        $this->auditLogger->record('create', 'client_feedback', 'client_feedback', $id, null, $payload);
        $this->notifyTenantUsers(
            'Client feedback received',
            'Feedback was recorded for ' . $client['company'] . '.',
            'client_feedback',
            $id
        );

        return redirect()->to('/workflow/certification/' . $clientId)->with('success', 'Client feedback saved.');
    }

    private function auditProgramPayload(array $client, array $contract, ?array $review, array $program, array $standards): array
    {
        $stored = [];
        if (! empty($program['program_payload'])) {
            $stored = json_decode((string) $program['program_payload'], true) ?: [];
        }

        $reviewPayload = [];
        if (! empty($review['review_payload'])) {
            $reviewPayload = json_decode((string) $review['review_payload'], true) ?: [];
        }

        $duration = $this->durationService->calculateApplicationReview($client, $standards, $reviewPayload);
        $standardText = implode(', ', array_filter(array_map(
            static fn (array $row): string => (string) ($row['standard_code'] ?? $row['code'] ?? ''),
            $standards
        )));
        $profile = $this->auditProgramStandardProfile($standards);
        $issueDate = (string) ($program['certificate_issue_date'] ?? date('Y-m-d'));
        $cycleDates = $this->certificationCycleDates($issueDate);

        $defaults = [
            'profile_version' => 2,
            'standard_signature' => $this->auditProgramStandardSignature($standards),
            'client_reference' => $contract['contract_number'] ?? $this->number('QSI-REF', (int) $client['id']),
            'standards_text' => $standardText,
            'category_label' => $profile['category_label'],
            'process_label' => $profile['process_label'],
            'category_subcategory' => $reviewPayload['audit_category'] ?? $profile['category_default'],
            'audit_language' => $reviewPayload['communication_language'] ?? 'English',
            'audit_type' => 'Initial Certification',
            'organization_name' => $client['legal_name'] ?: $client['company'],
            'head_office_address' => trim((string) ($client['address'] ?? '')),
            'site_addresses' => 'Same as head office unless otherwise stated.',
            'scope' => (string) ($client['scope'] ?? ''),
            'exclusions' => $reviewPayload['standard_exclusions'] ?? 'None',
            'employee_count' => (string) ($duration['employee_count'] ?? $client['employee_count'] ?? ''),
            'shifts' => (string) ($client['shift_pattern'] ?? $reviewPayload['shifts_auditing'] ?? 'Single shift'),
            'haccp_studies' => (string) ($reviewPayload['haccp_plans_processes'] ?? $profile['process_default']),
            'audit_duration_days' => number_format((float) $duration['total_days'], 2, '.', ''),
            'stage1_days' => number_format((float) ($review['stage1_days'] ?? $duration['stage1_days']), 2, '.', ''),
            'stage2_days' => number_format((float) ($review['stage2_days'] ?? $duration['stage2_days']), 2, '.', ''),
            'surveillance1_days' => number_format((float) ($duration['surveillance1_days'] ?? 1.00), 2, '.', ''),
            'surveillance2_days' => number_format((float) ($duration['surveillance2_days'] ?? 1.00), 2, '.', ''),
            'recertification_days' => number_format((float) ($duration['recertification_days'] ?? 1.00), 2, '.', ''),
            'surveillance_1_due_date' => $cycleDates['surveillance1'],
            'surveillance_2_due_date' => $cycleDates['surveillance2'],
            'certificate_expiry_date' => $cycleDates['expiry'],
            'coverage' => $this->auditProgramCoverageRows($client, $standards),
            'committee' => $this->auditProgramCommitteeRows((int) ($program['id'] ?? 0)),
            'nc_summary' => $this->auditProgramNcSummaryRows($standards),
            'legend_notes' => 'Checked cells are planned for inspection. NE = not covered/applicable. PD = unplanned. X = inspected or planned clause. Numbers in NC summary indicate total nonconformities by stage.',
        ];

        $merged = array_merge($defaults, $stored);
        if ((int) ($stored['profile_version'] ?? 0) < 2 || ($stored['standard_signature'] ?? '') !== $defaults['standard_signature']) {
            $merged['profile_version'] = $defaults['profile_version'];
            $merged['standard_signature'] = $defaults['standard_signature'];
            $merged['category_label'] = $defaults['category_label'];
            $merged['process_label'] = $defaults['process_label'];
            $merged['category_subcategory'] = $defaults['category_subcategory'];
            $merged['haccp_studies'] = $defaults['haccp_studies'];
            $merged['coverage'] = $defaults['coverage'];
            $merged['nc_summary'] = $defaults['nc_summary'];
            $merged['legend_notes'] = $defaults['legend_notes'];
        }

        $merged['category_label'] = $profile['category_label'];
        $merged['process_label'] = $profile['process_label'];
        if (! $profile['has_food']) {
            $merged['haccp_studies'] = '';
            if (preg_match('/^[A-Z]+\/[A-Z0-9]+$/', (string) ($merged['category_subcategory'] ?? ''))) {
                $merged['category_subcategory'] = $profile['category_default'];
            }
        }

        return $merged;
    }

    private function postedAuditProgramPayload(): array
    {
        $coverage = [];
        $coverageStandards = (array) $this->request->getPost('coverage_standard');
        $coverageNumbers = (array) $this->request->getPost('coverage_clause_number');
        $coverageTitles = (array) $this->request->getPost('coverage_clause_title');
        $coverageStage1 = (array) $this->request->getPost('coverage_initial_stage1');
        $coverageStage2 = (array) $this->request->getPost('coverage_initial_stage2');
        $coverageSurv1 = (array) $this->request->getPost('coverage_surveillance1');
        $coverageSurv2 = (array) $this->request->getPost('coverage_surveillance2');
        $coverageRecert = (array) $this->request->getPost('coverage_recertification');

        foreach ($coverageNumbers as $index => $number) {
            $coverage[] = [
                'standard' => trim((string) ($coverageStandards[$index] ?? '')),
                'clause_number' => trim((string) $number),
                'clause_title' => trim((string) ($coverageTitles[$index] ?? '')),
                'initial_stage1' => isset($coverageStage1[$index]) ? 'X' : '',
                'initial_stage2' => isset($coverageStage2[$index]) ? 'X' : '',
                'surveillance1' => isset($coverageSurv1[$index]) ? 'X' : '',
                'surveillance2' => isset($coverageSurv2[$index]) ? 'X' : '',
                'recertification' => isset($coverageRecert[$index]) ? 'X' : '',
            ];
        }

        $committee = [];
        $committeeRoles = (array) $this->request->getPost('committee_role');
        foreach ($committeeRoles as $index => $role) {
            $committee[] = [
                'role' => trim((string) $role),
                'initial_stage1' => trim((string) ((array) $this->request->getPost('committee_initial_stage1'))[$index] ?? ''),
                'initial_stage2' => trim((string) ((array) $this->request->getPost('committee_initial_stage2'))[$index] ?? ''),
                'surveillance1' => trim((string) ((array) $this->request->getPost('committee_surveillance1'))[$index] ?? ''),
                'surveillance2' => trim((string) ((array) $this->request->getPost('committee_surveillance2'))[$index] ?? ''),
                'recertification' => trim((string) ((array) $this->request->getPost('committee_recertification'))[$index] ?? ''),
            ];
        }

        $ncSummary = [];
        foreach ((array) $this->request->getPost('nc_standard') as $index => $standard) {
            $ncSummary[] = [
                'standard' => trim((string) $standard),
                'initial_stage1' => (int) (((array) $this->request->getPost('nc_initial_stage1'))[$index] ?? 0),
                'initial_stage2' => (int) (((array) $this->request->getPost('nc_initial_stage2'))[$index] ?? 0),
                'surveillance1' => (int) (((array) $this->request->getPost('nc_surveillance1'))[$index] ?? 0),
                'surveillance2' => (int) (((array) $this->request->getPost('nc_surveillance2'))[$index] ?? 0),
                'recertification' => (int) (((array) $this->request->getPost('nc_recertification'))[$index] ?? 0),
            ];
        }

        return [
            'profile_version' => 2,
            'standard_signature' => '',
            'client_reference' => trim((string) $this->request->getPost('client_reference')),
            'standards_text' => trim((string) $this->request->getPost('standards_text')),
            'category_label' => trim((string) $this->request->getPost('category_label')),
            'process_label' => trim((string) $this->request->getPost('process_label')),
            'category_subcategory' => trim((string) $this->request->getPost('category_subcategory')),
            'audit_language' => trim((string) $this->request->getPost('audit_language')),
            'audit_type' => trim((string) $this->request->getPost('audit_type')),
            'organization_name' => trim((string) $this->request->getPost('organization_name')),
            'head_office_address' => trim((string) $this->request->getPost('head_office_address')),
            'site_addresses' => trim((string) $this->request->getPost('site_addresses')),
            'scope' => trim((string) $this->request->getPost('scope')),
            'exclusions' => trim((string) $this->request->getPost('exclusions')),
            'employee_count' => trim((string) $this->request->getPost('employee_count')),
            'shifts' => trim((string) $this->request->getPost('shifts')),
            'haccp_studies' => trim((string) $this->request->getPost('haccp_studies')),
            'audit_duration_days' => number_format((float) $this->request->getPost('audit_duration_days'), 2, '.', ''),
            'stage1_days' => number_format((float) $this->request->getPost('stage1_days'), 2, '.', ''),
            'stage2_days' => number_format((float) $this->request->getPost('stage2_days'), 2, '.', ''),
            'surveillance1_days' => number_format((float) $this->request->getPost('surveillance1_days'), 2, '.', ''),
            'surveillance2_days' => number_format((float) $this->request->getPost('surveillance2_days'), 2, '.', ''),
            'recertification_days' => number_format((float) $this->request->getPost('recertification_days'), 2, '.', ''),
            'surveillance_1_due_date' => trim((string) $this->request->getPost('surveillance_1_due_date')),
            'surveillance_2_due_date' => trim((string) $this->request->getPost('surveillance_2_due_date')),
            'certificate_expiry_date' => trim((string) $this->request->getPost('certificate_expiry_date')),
            'coverage' => $coverage,
            'committee' => $committee,
            'nc_summary' => $ncSummary,
            'legend_notes' => trim((string) $this->request->getPost('legend_notes')),
        ];
    }

    private function auditProgramDefaultEvents(int $clientId, array $program, array $payload): array
    {
        $issue = new DateTimeImmutable((string) ($program['certificate_issue_date'] ?? date('Y-m-d')));
        $cycleDates = $this->certificationCycleDates($issue->format('Y-m-d'));
        $stage1Days = (float) ($payload['stage1_days'] ?? 1.00);
        $stage2Days = (float) ($payload['stage2_days'] ?? 2.00);
        $surv1Days = (float) ($payload['surveillance1_days'] ?? 1.00);
        $surv2Days = (float) ($payload['surveillance2_days'] ?? 1.00);
        $recertDays = (float) ($payload['recertification_days'] ?? 2.00);
        $stage1Start = $this->nextWorkingDay($issue);
        $stage2Start = $this->durationService->addWorkingDays($stage1Start, 10);
        $surv1Due = new DateTimeImmutable($cycleDates['surveillance1']);
        $surv2Due = new DateTimeImmutable($cycleDates['surveillance2']);
        $expiryDue = new DateTimeImmutable($cycleDates['expiry']);
        $recertStart = $this->nextWorkingDay($expiryDue->sub(new DateInterval('P90D')));

        return [
            $this->auditProgramEventRow($clientId, 'initial_stage1', $stage1Start, $stage1Days, $issue, $issue->add(new DateInterval('P30D'))),
            $this->auditProgramEventRow($clientId, 'initial_stage2', $stage2Start, $stage2Days, $issue, $issue->add(new DateInterval('P90D'))),
            $this->auditProgramEventRow($clientId, 'surveillance1', $this->nextWorkingDay($surv1Due), $surv1Days, $surv1Due->sub(new DateInterval('P90D')), $surv1Due),
            $this->auditProgramEventRow($clientId, 'surveillance2', $this->nextWorkingDay($surv2Due), $surv2Days, $surv2Due->sub(new DateInterval('P90D')), $surv2Due),
            $this->auditProgramEventRow($clientId, 'recertification', $recertStart, $recertDays, $expiryDue->sub(new DateInterval('P180D')), $expiryDue),
        ];
    }

    private function auditProgramEventRow(int $clientId, string $type, DateTimeImmutable $start, float $duration, DateTimeImmutable $windowStart, DateTimeImmutable $windowEnd): array
    {
        return [
            'id' => '',
            'event_type' => $type,
            'audit_number' => $this->number('AUD-' . strtoupper(str_replace('_', '-', $type)), $clientId),
            'planned_start_date' => $start->format('Y-m-d'),
            'planned_end_date' => $this->durationService->endDateForDuration($start, $duration)->format('Y-m-d'),
            'audit_window_start' => $windowStart->format('Y-m-d'),
            'audit_window_end' => $windowEnd->format('Y-m-d'),
            'duration_days' => number_format($duration, 2, '.', ''),
            'status' => 'planned',
        ];
    }

    private function auditProgramCoverageRows(array $client, array $standards): array
    {
        $standardIds = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['standard_id'] ?? 0),
            $standards
        )));

        if ($standardIds === []) {
            return [];
        }

        $rows = $this->db->table('clause_library')
            ->select('standards.code AS standard, clause_library.clause_number, clause_library.clause_title')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->where('clause_library.tenant_id', (int) session()->get('tenant_id'))
            ->whereIn('clause_library.standard_id', $standardIds)
            ->where('clause_library.active', 1)
            ->get()
            ->getResultArray();

        usort($rows, static function (array $left, array $right): int {
            $standardCompare = strcmp((string) $left['standard'], (string) $right['standard']);
            if ($standardCompare !== 0) {
                return $standardCompare;
            }

            return strnatcmp((string) $left['clause_number'], (string) $right['clause_number']);
        });

        $coverage = [];
        foreach ($rows as $row) {
            $stageCoverage = $this->auditProgramStageCoverage((string) $row['standard'], (string) $row['clause_number'], (string) $row['clause_title']);
            $coverage[] = [
                'standard' => (string) $row['standard'],
                'clause_number' => (string) $row['clause_number'],
                'clause_title' => (string) $row['clause_title'],
                'initial_stage1' => $stageCoverage['initial_stage1'],
                'initial_stage2' => $stageCoverage['initial_stage2'],
                'surveillance1' => $stageCoverage['surveillance1'],
                'surveillance2' => $stageCoverage['surveillance2'],
                'recertification' => $stageCoverage['recertification'],
            ];
        }

        foreach ($this->auditProgramAdditionalRequirements($standards) as $title) {
            $coverage[] = [
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

        return $coverage;
    }

    private function auditProgramCommitteeRows(int $programId): array
    {
        $roles = [
            'Lead Auditor',
            'Auditor 1',
            'Auditor 2',
            'Technical Specialist',
            'Additional / Trainee Auditor',
            'Observer',
        ];
        $rows = [];
        foreach ($roles as $role) {
            $rows[] = [
                'role' => $role,
                'initial_stage1' => '',
                'initial_stage2' => '',
                'surveillance1' => '',
                'surveillance2' => '',
                'recertification' => '',
            ];
        }

        if ($programId <= 0) {
            return $rows;
        }

        foreach ($this->appointmentRows($programId) as $appointment) {
            if (stripos((string) $appointment['appointment_role'], 'lead') === false) {
                continue;
            }

            foreach ($rows as &$row) {
                if ($row['role'] === 'Lead Auditor') {
                    $row[(string) $appointment['event_type']] = (string) $appointment['full_name'];
                }
            }
            unset($row);
        }

        return $rows;
    }

    private function auditProgramNcSummaryRows(array $standards): array
    {
        $rows = [];
        foreach ($standards as $standard) {
            $rows[] = [
                'standard' => (string) ($standard['standard_code'] ?? $standard['code'] ?? ''),
                'initial_stage1' => 0,
                'initial_stage2' => 0,
                'surveillance1' => 0,
                'surveillance2' => 0,
                'recertification' => 0,
            ];
        }

        return $rows;
    }

    private function auditProgramStandardProfile(array $standards): array
    {
        $types = [];
        foreach ($standards as $standard) {
            $code = strtoupper((string) ($standard['standard_code'] ?? $standard['code'] ?? ''));
            $scheme = strtolower((string) ($standard['scheme_type'] ?? ''));
            if (str_contains($code, 'HACCP') || str_contains($code, 'ISO 22000') || str_contains($code, 'FSSC') || str_contains($scheme, 'food')) {
                $types['food'] = true;
            } elseif (str_contains($code, 'ISO 14001')) {
                $types['environment'] = true;
            } elseif (str_contains($code, 'ISO 45001')) {
                $types['ohs'] = true;
            } elseif (str_contains($code, 'ISO 13485')) {
                $types['medical'] = true;
            } elseif (str_contains($code, 'ISO 17021')) {
                $types['cb_ms'] = true;
            } elseif (str_contains($code, 'ISO 17065')) {
                $types['cb_product'] = true;
            } elseif (str_contains($code, 'ISO 9001')) {
                $types['quality'] = true;
            }
        }

        if (count($types) > 1) {
            return [
                'has_food' => isset($types['food']),
                'category_label' => 'Scheme / IAF / category codes',
                'category_default' => '',
                'process_label' => 'Key audited processes / standard-specific controls',
                'process_default' => '',
            ];
        }

        if (isset($types['food'])) {
            return [
                'has_food' => true,
                'category_label' => 'Food chain category / sub-category',
                'category_default' => '',
                'process_label' => 'HACCP studies / food safety plans',
                'process_default' => '',
            ];
        }

        if (isset($types['environment'])) {
            return [
                'has_food' => false,
                'category_label' => 'IAF code / environmental aspect category',
                'category_default' => '',
                'process_label' => 'Significant environmental aspects / key controls',
                'process_default' => '',
            ];
        }

        if (isset($types['ohs'])) {
            return [
                'has_food' => false,
                'category_label' => 'IAF code / OHS risk category',
                'category_default' => '',
                'process_label' => 'Significant hazards / key OHS controls',
                'process_default' => '',
            ];
        }

        if (isset($types['medical'])) {
            return [
                'has_food' => false,
                'category_label' => 'Medical device category / risk class',
                'category_default' => '',
                'process_label' => 'Device families / regulated processes',
                'process_default' => '',
            ];
        }

        if (isset($types['cb_ms'])) {
            return [
                'has_food' => false,
                'category_label' => 'Certification scheme / IAF scope',
                'category_default' => '',
                'process_label' => 'Certification schemes / witnessed activities',
                'process_default' => '',
            ];
        }

        if (isset($types['cb_product'])) {
            return [
                'has_food' => false,
                'category_label' => 'Product certification scheme / product group',
                'category_default' => '',
                'process_label' => 'Product groups / evaluation activities',
                'process_default' => '',
            ];
        }

        return [
            'has_food' => false,
            'category_label' => 'IAF scope code(s)',
            'category_default' => '',
            'process_label' => 'Key audited processes',
            'process_default' => '',
        ];
    }

    private function auditProgramStageCoverage(string $standard, string $clauseNumber, string $clauseTitle): array
    {
        $text = strtolower($standard . ' ' . $clauseNumber . ' ' . $clauseTitle);
        $stage1 = str_starts_with($clauseNumber, '4')
            || str_starts_with($clauseNumber, '5')
            || str_starts_with($clauseNumber, '6')
            || str_starts_with($clauseNumber, '7')
            || str_contains($text, 'scope')
            || str_contains($text, 'context')
            || str_contains($text, 'policy')
            || str_contains($text, 'objective')
            || str_contains($text, 'document')
            || str_contains($text, 'hazard')
            || str_contains($text, 'environmental aspect')
            || str_contains($text, 'haccp')
            || str_contains($text, 'prp');

        return [
            'initial_stage1' => $stage1 ? 'X' : '',
            'initial_stage2' => 'X',
            'surveillance1' => 'X',
            'surveillance2' => 'X',
            'recertification' => 'X',
        ];
    }

    private function auditProgramAdditionalRequirements(array $standards): array
    {
        $requirements = [];
        foreach ($standards as $standard) {
            $code = strtoupper((string) ($standard['standard_code'] ?? $standard['code'] ?? ''));
            $scheme = strtolower((string) ($standard['scheme_type'] ?? ''));

            if (str_contains($code, 'HACCP') || str_contains($code, 'ISO 22000') || str_contains($code, 'FSSC') || str_contains($scheme, 'food')) {
                $requirements = array_merge($requirements, [
                    'Product and process food safety review',
                    'PRP / OPRP / CCP control effectiveness',
                    'Traceability, withdrawal and recall arrangements',
                    'Food safety hazard analysis and validation status',
                    'Food safety legal and customer requirements',
                ]);
                continue;
            }

            if (str_contains($code, 'ISO 14001')) {
                $requirements = array_merge($requirements, [
                    'Significant environmental aspects and impacts',
                    'Compliance obligations and evaluation status',
                    'Operational controls for environmental risks',
                    'Emergency preparedness and response',
                    'Environmental performance monitoring',
                ]);
                continue;
            }

            if (str_contains($code, 'ISO 45001')) {
                $requirements = array_merge($requirements, [
                    'Hazard identification and OHS risk controls',
                    'Legal and other OHS requirements',
                    'Worker consultation and participation',
                    'Emergency preparedness and response',
                    'Incident, injury and ill-health investigation',
                ]);
                continue;
            }

            if (str_contains($code, 'ISO 13485')) {
                $requirements = array_merge($requirements, [
                    'Medical device regulatory requirements',
                    'Device family, risk class and intended use',
                    'Sterilization, cleanroom or special process controls',
                    'Traceability, UDI and post-market surveillance',
                    'Complaint, vigilance and advisory notice controls',
                ]);
                continue;
            }

            if (str_contains($code, 'ISO 17021')) {
                $requirements = array_merge($requirements, [
                    'Impartiality and certification decision controls',
                    'Auditor competence and evaluation process',
                    'Certification process, audit program and file review',
                    'Complaints, appeals and suspension process',
                    'Witnessed activity / office assessment coverage',
                ]);
                continue;
            }

            if (str_contains($code, 'ISO 17065')) {
                $requirements = array_merge($requirements, [
                    'Product certification scheme requirements',
                    'Evaluation, review and certification decision process',
                    'Impartiality and conflict of interest controls',
                    'Use of marks, licences and surveillance arrangements',
                    'Complaints, appeals and product nonconformity controls',
                ]);
                continue;
            }

            $requirements = array_merge($requirements, [
                'Customer and statutory/regulatory requirements',
                'Scope and complexity of the management system',
                'Process performance and operational control',
                'Internal audit and management review results',
                'Customer satisfaction, complaints and improvement trends',
            ]);
        }

        return array_values(array_unique($requirements));
    }

    private function auditProgramStandardSignature(array $standards): string
    {
        $codes = array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['standard_code'] ?? $row['code'] ?? ''))),
            $standards
        );
        sort($codes);

        return implode('|', array_filter($codes));
    }

    private function syncProgramEvents(int $programId, int $clientId): void
    {
        $eventTypes = (array) $this->request->getPost('event_type');
        if ($eventTypes === []) {
            return;
        }

        $eventIds = (array) $this->request->getPost('event_id');
        $auditNumbers = (array) $this->request->getPost('audit_number');
        $starts = (array) $this->request->getPost('planned_start_date');
        $ends = (array) $this->request->getPost('planned_end_date');
        $windowStarts = (array) $this->request->getPost('audit_window_start');
        $windowEnds = (array) $this->request->getPost('audit_window_end');
        $durations = (array) $this->request->getPost('duration_days');
        $statuses = (array) $this->request->getPost('event_status');

        foreach ($eventTypes as $index => $type) {
            $type = (string) $type;
            if ($type === '') {
                continue;
            }

            $payload = [
                'audit_program_id' => $programId,
                'event_type' => $type,
                'audit_number' => (string) ($auditNumbers[$index] ?? $this->number('AUD-' . strtoupper(str_replace('_', '-', $type)), $clientId)),
                'planned_start_date' => (string) ($starts[$index] ?? ''),
                'planned_end_date' => (string) ($ends[$index] ?? ''),
                'audit_window_start' => (string) ($windowStarts[$index] ?? ''),
                'audit_window_end' => (string) ($windowEnds[$index] ?? ''),
                'duration_days' => (float) ($durations[$index] ?? 0),
                'status' => (string) ($statuses[$index] ?? 'planned'),
            ];

            $eventId = (int) ($eventIds[$index] ?? 0);
            if ($eventId > 0 && $this->eventBelongsToProgram($eventId, $programId)) {
                $this->events->update($eventId, $payload);
                continue;
            }

            $this->events->insert($payload);
        }
    }

    private function createProgramEvents(int $programId, int $clientId, string $issueDate, string $expiryDate, array $client): void
    {
        $issue = new DateTimeImmutable($issueDate);
        $expiry = new DateTimeImmutable($expiryDate);
        $duration = $this->durationService->normalizeStageDays(
            $this->decimalOrNull('stage1_days'),
            $this->decimalOrNull('stage2_days'),
            $client,
            $this->clientStandardRows($clientId),
        );
        $stage1Days = $duration['stage1_days'];
        $stage2Days = $duration['stage2_days'];
        $stage1Start = $this->nextWorkingDay($issue);
        $stage2Start = $this->durationService->addWorkingDays($stage1Start, 10);
        $cycleDates = $this->certificationCycleDates($issueDate);
        $surveillance1Due = new DateTimeImmutable($cycleDates['surveillance1']);
        $surveillance2Due = new DateTimeImmutable($cycleDates['surveillance2']);
        $expiryDue = new DateTimeImmutable($cycleDates['expiry']);
        $definitions = [
            ['initial_stage1', $stage1Start, $this->durationService->endDateForDuration($stage1Start, $stage1Days), $issue, $issue->add(new DateInterval('P30D')), $stage1Days],
            ['initial_stage2', $stage2Start, $this->durationService->endDateForDuration($stage2Start, $stage2Days), $issue, $issue->add(new DateInterval('P90D')), $stage2Days],
            ['surveillance1', $this->nextWorkingDay($surveillance1Due), $this->nextWorkingDay($surveillance1Due), $surveillance1Due->sub(new DateInterval('P90D')), $surveillance1Due, 1.00],
            ['surveillance2', $this->nextWorkingDay($surveillance2Due), $this->nextWorkingDay($surveillance2Due), $surveillance2Due->sub(new DateInterval('P90D')), $surveillance2Due, 1.00],
            ['recertification', $this->nextWorkingDay($expiryDue->sub(new DateInterval('P90D'))), $this->durationService->endDateForDuration($this->nextWorkingDay($expiryDue->sub(new DateInterval('P90D'))), $stage2Days), $expiryDue->sub(new DateInterval('P180D')), $expiryDue, $stage2Days],
        ];

        foreach ($definitions as [$type, $plannedStart, $plannedEnd, $windowStart, $windowEnd, $duration]) {
            $this->events->insert([
                'audit_program_id' => $programId,
                'event_type' => $type,
                'audit_number' => $this->number('AUD-' . strtoupper(str_replace('_', '-', $type)), $clientId),
                'planned_start_date' => $plannedStart->format('Y-m-d'),
                'planned_end_date' => $plannedEnd->format('Y-m-d'),
                'audit_window_start' => $windowStart->format('Y-m-d'),
                'audit_window_end' => $windowEnd->format('Y-m-d'),
                'duration_days' => $duration,
                'status' => 'planned',
            ]);
        }
    }

    private function certificationCycleDates(string $issueDate): array
    {
        $issue = new DateTimeImmutable($issueDate);

        return [
            'issue' => $issue->format('Y-m-d'),
            'surveillance1' => $issue->add(new DateInterval('P1Y'))->sub(new DateInterval('P1D'))->format('Y-m-d'),
            'surveillance2' => $issue->add(new DateInterval('P2Y'))->sub(new DateInterval('P1D'))->format('Y-m-d'),
            'expiry' => $issue->add(new DateInterval('P3Y'))->sub(new DateInterval('P1D'))->format('Y-m-d'),
        ];
    }

    private function updateAuditProgramCycleDates(int $clientId, array $cycleDates): void
    {
        $program = $this->latestProgram($clientId);
        if ($program === null) {
            return;
        }

        $this->programs->update((int) $program['id'], [
            'certificate_issue_date' => $cycleDates['issue'],
            'surveillance_1_due_date' => $cycleDates['surveillance1'],
            'surveillance_2_due_date' => $cycleDates['surveillance2'],
            'certificate_expiry_date' => $cycleDates['expiry'],
            'surveillance_1_status' => $this->surveillanceCycleStatus($cycleDates['surveillance1'], $this->eventStatus((int) $program['id'], 'surveillance1')),
            'surveillance_2_status' => $this->surveillanceCycleStatus($cycleDates['surveillance2'], $this->eventStatus((int) $program['id'], 'surveillance2')),
        ]);

        foreach ([
            'surveillance1' => $cycleDates['surveillance1'],
            'surveillance2' => $cycleDates['surveillance2'],
        ] as $eventType => $dueDate) {
            $due = new DateTimeImmutable($dueDate);
            $event = $this->events
                ->where('audit_program_id', (int) $program['id'])
                ->where('event_type', $eventType)
                ->first();

            if ($event === null) {
                continue;
            }

            $payload = [
                'planned_start_date' => $dueDate,
                'planned_end_date' => $dueDate,
                'audit_window_start' => $due->sub(new DateInterval('P90D'))->format('Y-m-d'),
                'audit_window_end' => $dueDate,
            ];
            $this->events->update((int) $event['id'], $payload);
        }
    }

    private function eventStatus(int $programId, string $eventType): ?string
    {
        $event = $this->events
            ->where('audit_program_id', $programId)
            ->where('event_type', $eventType)
            ->first();

        return $event['status'] ?? null;
    }

    private function surveillanceLockMessage(?array $event): ?string
    {
        if ($event === null || ! in_array((string) ($event['event_type'] ?? ''), ['surveillance1', 'surveillance2'], true)) {
            return null;
        }

        if (in_array((string) ($event['status'] ?? ''), ['completed', 'closed'], true)) {
            return null;
        }

        $program = $this->programs->find((int) $event['audit_program_id']);
        if ($program === null) {
            return null;
        }

        $dueDate = (string) ($event['event_type'] === 'surveillance1'
            ? ($program['surveillance_1_due_date'] ?? '')
            : ($program['surveillance_2_due_date'] ?? ''));

        if ($dueDate === '') {
            return null;
        }

        $today = new DateTimeImmutable(date('Y-m-d'));
        $due = new DateTimeImmutable($dueDate);
        if ($today >= $due) {
            return null;
        }

        $label = $event['event_type'] === 'surveillance1' ? 'Surveillance Audit #01' : 'Surveillance Audit #02';

        return $label . ' is locked until its due date (' . $due->format('Y-m-d') . ').';
    }

    private function isActiveAppointmentStatus(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['appointed', 'accepted', 'confirmed', 'approved', 'active'], true);
    }

    private function appointmentGateFailures(
        int $clientId,
        int $personnelId,
        string $appointmentRole,
        bool $competenceConfirmed,
        bool $impartialityConfirmed,
        bool $conflictOfInterest
    ): array {
        $failures = [];
        $person = $this->personnelForTenant($personnelId);

        if ($person === null) {
            $failures[] = 'Selected person is not an approved tenant personnel record.';
            return $failures;
        }

        if (($person['approval_status'] ?? '') !== 'approved') {
            $failures[] = 'Selected person is not approved in Personnel Master.';
        }

        if (($person['personnel_type'] ?? '') === 'client_representative') {
            $failures[] = 'Client personnel cannot be appointed as the certification-body audit team.';
        }

        if (! $competenceConfirmed) {
            $failures[] = 'Auditor competence confirmation is required before appointment.';
        }

        if (! $impartialityConfirmed) {
            $failures[] = 'Impartiality confirmation is required before appointment.';
        }

        if ($conflictOfInterest) {
            $failures[] = 'Appointment cannot be active while a conflict of interest is recorded.';
        }

        if ($this->appointmentRequiresCompetence($appointmentRole) && ! $this->personnelHasApprovedCompetenceForClient($personnelId, $clientId)) {
            $failures[] = 'No approved matching competence record was found for this person against the client standard/scope categories.';
        }

        return $failures;
    }

    private function jsonWorkflowDenied(string $message)
    {
        return $this->response->setStatusCode(403)->setJSON([
            'ok' => false,
            'message' => $message,
            'csrfToken' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    private function appointmentRequiresCompetence(string $role): bool
    {
        $role = strtolower($role);

        return ! str_contains($role, 'observer')
            && ! str_contains($role, 'trainee')
            && ! str_contains($role, 'witness');
    }

    private function technicalReviewGateFailures(int $clientId, int $eventId, int $reviewerPersonnelId): array
    {
        $failures = [];
        $event = $this->events->find($eventId);
        $report = $this->reportForEvent($eventId);

        if ($event === null) {
            return ['Audit event not found.'];
        }

        if (! in_array((string) ($event['status'] ?? ''), ['completed', 'closed'], true)) {
            $failures[] = 'Audit event must be completed before Technical Review approval.';
        }

        if ($report === null || ! in_array((string) ($report['status'] ?? ''), ['submitted', 'approved'], true)) {
            $failures[] = 'Audit report must be submitted before Technical Review approval.';
        }

        if ($this->openNcrCountForEvent($eventId) > 0 || $this->openCapaCountForEvent($eventId) > 0) {
            $failures[] = 'All NCR/CAPA records for this audit event must be closed before Technical Review approval.';
        }

        $teamFailures = $this->auditTeamGateFailures($clientId, $eventId);
        $failures = array_merge($failures, $teamFailures);
        $failures = array_merge($failures, $this->auditTeamCoverageFailures($clientId, $eventId));

        $reviewer = $this->personnelForTenant($reviewerPersonnelId);
        if ($reviewer === null || ($reviewer['approval_status'] ?? '') !== 'approved') {
            $failures[] = 'Technical reviewer must be an approved Personnel Master record.';
        }

        if ($this->personnelIsOnAuditTeam($reviewerPersonnelId, $eventId)) {
            $failures[] = 'Technical reviewer cannot be part of the audit team for the same audit event.';
        }

        $reviewerMissing = $this->uncoveredClientRequirementsForPersonnel($reviewerPersonnelId, $clientId);
        if ($reviewerMissing !== []) {
            $failures[] = 'Technical reviewer does not cover all selected client standards/scopes: ' . implode(', ', $reviewerMissing) . '.';
        }

        $fileFailures = $this->certificationFileGateFailures($clientId, $eventId);
        $failures = array_merge($failures, $fileFailures);

        foreach ([
            'competency_confirmed' => 'competence',
            'duration_confirmed' => 'audit duration',
            'application_confirmed' => 'application/scope',
            'reports_confirmed' => 'audit report completeness',
            'ncr_capa_confirmed' => 'NCR/CAPA closure',
            'scope_dates_confirmed' => 'scope and dates',
            'impartiality_confirmed' => 'impartiality',
        ] as $field => $label) {
            if (! $this->checkbox($field)) {
                $failures[] = 'Technical Review approval requires confirmation of ' . $label . '.';
            }
        }

        return array_values(array_unique($failures));
    }

    private function decisionGateFailures(array $review, int $decisionMakerPersonnelId, string $decision, string $status): array
    {
        $failures = [];
        $eventId = (int) ($review['audit_event_id'] ?? 0);
        $event = $this->events->find($eventId);
        $clientId = $event === null ? 0 : $this->clientIdForEvent($eventId);

        if (! in_array((string) ($review['status'] ?? ''), ['approved', 'completed'], true)) {
            $failures[] = 'Certification decision requires an approved Technical Review.';
        }

        if ($event === null || $clientId <= 0) {
            $failures[] = 'Decision audit event/client link is missing.';
            return $failures;
        }

        if ($this->openNcrCountForEvent($eventId) > 0 || $this->openCapaCountForEvent($eventId) > 0) {
            $failures[] = 'Certification decision cannot be approved while NCR/CAPA records are open.';
        }

        $decisionMaker = $this->personnelForTenant($decisionMakerPersonnelId);
        if ($decisionMaker === null || ($decisionMaker['approval_status'] ?? '') !== 'approved') {
            $failures[] = 'Decision maker must be an approved Personnel Master record.';
        }

        if ($this->personnelIsOnAuditTeam($decisionMakerPersonnelId, $eventId)) {
            $failures[] = 'Decision maker cannot be part of the audit team for the same audit event.';
        }

        if ($decisionMakerPersonnelId === (int) ($review['reviewer_personnel_id'] ?? 0)) {
            $failures[] = 'Decision maker must be independent from the Technical Reviewer.';
        }

        $decisionMissing = $this->uncoveredClientRequirementsForPersonnel($decisionMakerPersonnelId, $clientId);
        if ($decisionMissing !== []) {
            $failures[] = 'Decision maker does not cover all selected client standards/scopes: ' . implode(', ', $decisionMissing) . '.';
        }

        $fileFailures = $this->certificationFileGateFailures($clientId, $eventId);
        $failures = array_merge($failures, $fileFailures);

        if ($status === 'gm_approved' && ! in_array($decision, ['approved', 'granted'], true)) {
            $failures[] = 'General Manager final approval can only be recorded for an approved/granted decision.';
        }

        return array_values(array_unique($failures));
    }

    private function auditCompletionGateFailures(int $eventId): array
    {
        $failures = [];
        $event = $this->events->find($eventId);
        $clientId = $event === null ? 0 : $this->clientIdForEvent($eventId);

        if ($event === null || $clientId <= 0) {
            return ['Audit event/client link is missing.'];
        }

        $teamFailures = $this->auditTeamGateFailures($clientId, $eventId);
        $failures = array_merge($failures, $teamFailures);
        $failures = array_merge($failures, $this->auditTeamCoverageFailures($clientId, $eventId));

        if ($this->eventPlanItemRows($eventId) === []) {
            $failures[] = 'Audit plan timetable must be recorded before marking the audit completed.';
        }

        $report = $this->reportForEvent($eventId);
        if ($report === null) {
            $failures[] = 'Audit report draft must exist before completion.';
        } elseif ($this->reportSectionRows((int) $report['id']) === []) {
            $failures[] = 'Audit report checklist must contain saved clause records before completion.';
        } elseif ($this->unconfirmedConformitySectionCount((int) $report['id']) > 0) {
            $failures[] = 'All conformity notes must be explicitly confirmed by the auditor before audit completion.';
        }

        return array_values(array_unique($failures));
    }

    private function auditTeamGateFailures(int $clientId, int $eventId): array
    {
        $team = $this->eventTeamRows($eventId);
        if ($team === []) {
            return ['At least one approved auditor appointment is required.'];
        }

        $failures = [];
        foreach ($team as $member) {
            if (! $this->isActiveAppointmentStatus((string) ($member['status'] ?? ''))) {
                continue;
            }

            $check = json_decode((string) ($member['conflict_check_json'] ?? '{}'), true) ?: [];
            $name = (string) ($member['full_name'] ?? 'Selected auditor');
            if (empty($check['competence_confirmed'])) {
                $failures[] = $name . ' appointment is missing competence confirmation.';
            }
            if (empty($check['impartiality_confirmed'])) {
                $failures[] = $name . ' appointment is missing impartiality confirmation.';
            }
            if (! empty($check['conflict_of_interest'])) {
                $failures[] = $name . ' has a recorded conflict of interest.';
            }
            if ($this->appointmentRequiresCompetence((string) ($member['appointment_role'] ?? '')) && ! $this->personnelHasApprovedCompetenceForClient((int) $member['personnel_id'], $clientId)) {
                $failures[] = $name . ' has no approved matching competence record for the client standard/scope.';
            }
        }

        return $failures;
    }

    private function personnelForTenant(int $personnelId): ?array
    {
        if ($personnelId <= 0) {
            return null;
        }

        return $this->personnel
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('id', $personnelId)
            ->first();
    }

    private function personnelHasApprovedCompetenceForClient(int $personnelId, int $clientId): bool
    {
        if ($personnelId <= 0 || $clientId <= 0) {
            return false;
        }

        $standards = $this->clientStandardRows($clientId);
        if ($standards === []) {
            return false;
        }

        $competencies = $this->approvedCompetencyRowsForPersonnel($personnelId);
        foreach ($standards as $standard) {
            foreach ($competencies as $competency) {
                if ($this->competencyCoversClientStandard($competency, $standard)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function auditTeamCoverageFailures(int $clientId, int $eventId): array
    {
        $requirements = $this->clientStandardRows($clientId);
        if ($requirements === []) {
            return ['Client file has no selected standards/scope for competence coverage.'];
        }

        $activeAuditors = [];
        foreach ($this->eventTeamRows($eventId) as $member) {
            if ($this->isActiveAppointmentStatus((string) ($member['status'] ?? ''))
                && $this->appointmentRequiresCompetence((string) ($member['appointment_role'] ?? ''))
            ) {
                $activeAuditors[] = (int) $member['personnel_id'];
            }
        }

        if ($activeAuditors === []) {
            return ['No active competent audit team members are appointed for coverage verification.'];
        }

        $failures = [];
        foreach ($requirements as $requirement) {
            $covered = false;
            foreach (array_unique($activeAuditors) as $personnelId) {
                foreach ($this->approvedCompetencyRowsForPersonnel($personnelId) as $competency) {
                    if ($this->competencyCoversClientStandard($competency, $requirement)) {
                        $covered = true;
                        break 2;
                    }
                }
            }

            if (! $covered) {
                $failures[] = 'Audit team does not cover ' . $this->clientRequirementLabel($requirement) . '.';
            }
        }

        return $failures;
    }

    private function uncoveredClientRequirementsForPersonnel(int $personnelId, int $clientId): array
    {
        if ($personnelId <= 0) {
            return ['all selected standards'];
        }

        $competencies = $this->approvedCompetencyRowsForPersonnel($personnelId);
        $missing = [];

        foreach ($this->clientStandardRows($clientId) as $requirement) {
            $covered = false;
            foreach ($competencies as $competency) {
                if ($this->competencyCoversClientStandard($competency, $requirement)) {
                    $covered = true;
                    break;
                }
            }

            if (! $covered) {
                $missing[] = $this->clientRequirementLabel($requirement);
            }
        }

        return $missing;
    }

    private function approvedCompetencyRowsForPersonnel(int $personnelId): array
    {
        if ($personnelId <= 0) {
            return [];
        }

        $today = date('Y-m-d');

        return $this->db->table('personnel_competencies')
            ->where('personnel_id', $personnelId)
            ->where('approval_status', 'approved')
            ->groupStart()
                ->where('valid_from', null)
                ->orWhere('valid_from <=', $today)
            ->groupEnd()
            ->groupStart()
                ->where('valid_until', null)
                ->orWhere('valid_until >=', $today)
            ->groupEnd()
            ->get()
            ->getResultArray();
    }

    private function competencyCoversClientStandard(array $competency, array $clientStandard): bool
    {
        $competencyStandardId = (int) ($competency['standard_id'] ?? 0);
        $clientStandardId = (int) ($clientStandard['standard_id'] ?? 0);

        if ($competencyStandardId > 0 && $clientStandardId > 0 && $competencyStandardId !== $clientStandardId) {
            return false;
        }

        if ($competencyStandardId <= 0
            && ! $this->competencyCoversAllScopeCategories($competency)
            && ! $this->competencyMatchesAnyScopeCategory($competency, $clientStandard)
        ) {
            return false;
        }

        foreach (['iaf_code_id', 'food_chain_category_id', 'medical_device_category_id'] as $field) {
            $required = (int) ($clientStandard[$field] ?? 0);
            $approved = (int) ($competency[$field] ?? 0);
            if ($required > 0 && $approved > 0 && $approved !== $required) {
                return false;
            }
        }

        return true;
    }

    private function competencyCoversAllScopeCategories(array $competency): bool
    {
        foreach (['iaf_code_id', 'food_chain_category_id', 'medical_device_category_id'] as $field) {
            if ((int) ($competency[$field] ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }

    private function competencyMatchesAnyScopeCategory(array $competency, array $clientStandard): bool
    {
        foreach (['iaf_code_id', 'food_chain_category_id', 'medical_device_category_id'] as $field) {
            if ((int) ($clientStandard[$field] ?? 0) > 0 && (int) ($competency[$field] ?? 0) === (int) $clientStandard[$field]) {
                return true;
            }
        }

        return false;
    }

    private function clientRequirementLabel(array $requirement): string
    {
        $label = (string) ($requirement['standard_code'] ?? 'selected standard');
        $scopeBits = [];
        foreach (['iaf_code_id' => 'IAF', 'food_chain_category_id' => 'Food', 'medical_device_category_id' => 'Medical'] as $field => $prefix) {
            if (! empty($requirement[$field])) {
                $scopeBits[] = $prefix . ' #' . $requirement[$field];
            }
        }

        return $scopeBits === [] ? $label : $label . ' (' . implode(', ', $scopeBits) . ')';
    }

    private function certificationFileGateFailures(int $clientId, int $eventId): array
    {
        $failures = [];

        if ($this->clientStandardRows($clientId) === []) {
            $failures[] = 'Certification file has no selected standard/scope.';
        }

        $review = $this->latestReview($clientId);
        if ($review === null || ! in_array((string) ($review['status'] ?? ''), ['accepted', 'approved', 'qm_approved'], true)) {
            $failures[] = 'Application Review must be accepted/approved before file approval.';
        }

        $proposal = $this->latestProposal($clientId);
        if ($proposal === null || ! in_array((string) ($proposal['status'] ?? ''), ['accepted', 'approved'], true)) {
            $failures[] = 'Accepted proposal must exist before file approval.';
        }

        $contract = $this->latestContract($clientId);
        if ($contract === null || ! in_array((string) ($contract['status'] ?? ''), ['signed', 'approved', 'active'], true)) {
            $failures[] = 'Signed/approved contract must exist before file approval.';
        }

        $program = $this->latestProgram($clientId);
        if ($program === null) {
            $failures[] = 'Audit programme must exist before file approval.';
            return $failures;
        }

        foreach ($this->eventsRequiredForFileGate((int) $program['id'], $eventId) as $requiredEvent) {
            $eventLabel = ucwords(str_replace('_', ' ', (string) $requiredEvent['event_type']));
            if (! in_array((string) ($requiredEvent['status'] ?? ''), ['completed', 'closed'], true)) {
                $failures[] = $eventLabel . ' must be completed before file approval.';
            }

            $report = $this->reportForEvent((int) $requiredEvent['id']);
            if ($report === null || ! in_array((string) ($report['status'] ?? ''), ['submitted', 'approved', 'completed', 'closed'], true)) {
                $failures[] = $eventLabel . ' audit report must be submitted before file approval.';
            } elseif ($this->unconfirmedConformitySectionCount((int) $report['id']) > 0) {
                $failures[] = $eventLabel . ' still has unconfirmed conformity notes.';
            }
        }

        return $failures;
    }

    private function eventsRequiredForFileGate(int $programId, int $eventId): array
    {
        $events = $this->programEvents($programId);
        $selected = $this->eventFromList($events, $eventId);
        $selectedType = (string) ($selected['event_type'] ?? '');

        if (str_contains($selectedType, 'surveillance') || str_contains($selectedType, 'recertification')) {
            return $selected === null ? [] : [$selected];
        }

        $required = [];
        foreach ($events as $event) {
            if (in_array((string) ($event['event_type'] ?? ''), ['initial_stage1', 'stage1', 'initial_stage2', 'stage2'], true)) {
                $required[] = $event;
            }
        }

        return $required === [] && $selected !== null ? [$selected] : $required;
    }

    private function personnelIsOnAuditTeam(int $personnelId, int $eventId): bool
    {
        return $personnelId > 0
            && $this->appointments
                ->where('audit_event_id', $eventId)
                ->where('personnel_id', $personnelId)
                ->countAllResults() > 0;
    }

    private function openNcrCountForEvent(int $eventId): int
    {
        return (int) $this->ncrs
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('audit_event_id', $eventId)
            ->whereNotIn('status', ['closed', 'verified_closed', 'cancelled'])
            ->countAllResults();
    }

    private function openCapaCountForEvent(int $eventId): int
    {
        return (int) $this->db->table('capas')
            ->join('ncrs', 'ncrs.id = capas.ncr_id')
            ->where('capas.tenant_id', (int) session()->get('tenant_id'))
            ->where('ncrs.audit_event_id', $eventId)
            ->whereNotIn('capas.status', ['closed', 'verified_closed', 'cancelled'])
            ->countAllResults();
    }

    private function clientIdForEvent(int $eventId): int
    {
        $row = $this->db->table('audit_events')
            ->select('audit_programs.client_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_events.id', $eventId)
            ->where('audit_programs.tenant_id', (int) session()->get('tenant_id'))
            ->get(1)
            ->getRowArray();

        return (int) ($row['client_id'] ?? 0);
    }

    private function surveillanceCycleStatus(string $dueDate, ?string $eventStatus): string
    {
        if (in_array($eventStatus, ['completed', 'closed'], true)) {
            return 'completed';
        }

        $today = new DateTimeImmutable(date('Y-m-d'));
        $due = new DateTimeImmutable($dueDate);

        if ($today < $due) {
            return 'locked';
        }

        return $today > $due ? 'overdue' : 'active';
    }

    private function finalAuditEvent(array $events): ?array
    {
        foreach (['initial_stage2', 'stage2', 'recertification', 'surveillance2', 'surveillance1', 'initial_stage1', 'stage1'] as $type) {
            foreach ($events as $event) {
                if (($event['event_type'] ?? '') === $type) {
                    return $event;
                }
            }
        }

        return $events === [] ? null : end($events);
    }

    private function eventFromList(array $events, int $eventId): ?array
    {
        foreach ($events as $event) {
            if ((int) $event['id'] === $eventId) {
                return $event;
            }
        }

        return null;
    }

    private function technicalReviewForEvent(int $eventId): ?array
    {
        return $this->technicalReviews
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('audit_event_id', $eventId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function latestTechnicalReviewForClient(int $clientId): ?array
    {
        $program = $this->latestProgram($clientId);
        if ($program === null) {
            return null;
        }

        $events = $this->programEvents((int) $program['id']);
        if ($events === []) {
            return null;
        }

        return $this->technicalReviews
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->whereIn('audit_event_id', array_column($events, 'id'))
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function decisionForReview(int $reviewId): ?array
    {
        return $this->decisions
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('technical_review_id', $reviewId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function latestDecisionForClient(int $clientId): ?array
    {
        $review = $this->latestTechnicalReviewForClient($clientId);

        return $review === null ? null : $this->decisionForReview((int) $review['id']);
    }

    private function certificateRows(int $clientId): array
    {
        return $this->db->table('certificates')
            ->select('certificates.*, standards.code AS standard_code, standards.name AS standard_name')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.tenant_id', (int) session()->get('tenant_id'))
            ->where('certificates.client_id', $clientId)
            ->orderBy('certificates.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function certificateForClientStandard(int $clientId, int $standardId): ?array
    {
        return $this->certificates
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('client_id', $clientId)
            ->where('standard_id', $standardId)
            ->whereIn('status', ['active', 'suspended'])
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function feedbackRows(int $clientId): array
    {
        return $this->db->table('client_feedback')
            ->select('client_feedback.*, certificates.certificate_number')
            ->join('certificates', 'certificates.id = client_feedback.certificate_id', 'left')
            ->where('client_feedback.tenant_id', (int) session()->get('tenant_id'))
            ->where('client_feedback.client_id', $clientId)
            ->orderBy('client_feedback.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function capaRowsForEvent(int $eventId): array
    {
        return $this->db->table('capas')
            ->select('capas.*, ncrs.ncr_number, ncrs.requirement AS ncr_requirement, ncrs.finding AS ncr_finding, ncrs.objective_evidence AS ncr_objective_evidence, ncrs.classification AS ncr_classification, clause_library.clause_number, clause_library.clause_title')
            ->join('ncrs', 'ncrs.id = capas.ncr_id')
            ->join('clause_library', 'clause_library.id = ncrs.clause_library_id', 'left')
            ->where('capas.tenant_id', (int) session()->get('tenant_id'))
            ->where('ncrs.audit_event_id', $eventId)
            ->orderBy('capas.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function notifyTenantUsers(string $title, string $body, string $module, int $relatedId): void
    {
        $users = $this->db->table('users')
            ->select('id')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('status', 'active')
            ->get()
            ->getResultArray();

        foreach ($users as $user) {
            $this->db->table('notifications')->insert([
                'tenant_id' => (int) session()->get('tenant_id'),
                'user_id' => (int) $user['id'],
                'title' => $title,
                'body' => $body,
                'channel' => 'dashboard',
                'related_module' => $module,
                'related_id' => $relatedId,
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function ncrCountForEvents(array $events, bool $includeClosed): int
    {
        if ($events === []) {
            return 0;
        }

        $builder = $this->db->table('ncrs')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->whereIn('audit_event_id', array_column($events, 'id'));

        if (! $includeClosed) {
            $builder->whereNotIn('status', ['closed', 'verified_closed', 'cancelled']);
        }

        return $builder->countAllResults();
    }

    private function reportCountForEvents(array $events): int
    {
        if ($events === []) {
            return 0;
        }

        return $this->db->table('report_drafts')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->whereIn('audit_event_id', array_column($events, 'id'))
            ->countAllResults();
    }

    private function reportRowsForEvents(array $events): array
    {
        if ($events === []) {
            return [];
        }

        return $this->db->table('report_drafts')
            ->select('report_drafts.*, audit_events.audit_number, audit_events.event_type')
            ->join('audit_events', 'audit_events.id = report_drafts.audit_event_id')
            ->where('report_drafts.tenant_id', (int) session()->get('tenant_id'))
            ->whereIn('report_drafts.audit_event_id', array_column($events, 'id'))
            ->orderBy('report_drafts.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function ncrRowsForEvents(array $events): array
    {
        if ($events === []) {
            return [];
        }

        return $this->db->table('ncrs')
            ->select('ncrs.*, audit_events.audit_number, audit_events.event_type, standards.code AS standard_code, clause_library.clause_number')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('clause_library', 'clause_library.id = ncrs.clause_library_id', 'left')
            ->join('standards', 'standards.id = clause_library.standard_id', 'left')
            ->where('ncrs.tenant_id', (int) session()->get('tenant_id'))
            ->whereIn('ncrs.audit_event_id', array_column($events, 'id'))
            ->orderBy('ncrs.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function tenantClient(int $clientId): ?array
    {
        $client = $this->clients->find($clientId);

        if ($client === null || (int) $client['tenant_id'] !== (int) session()->get('tenant_id')) {
            return null;
        }

        return $client;
    }

    private function latestReview(int $clientId): ?array
    {
        return $this->reviews->where('client_id', $clientId)->orderBy('id', 'DESC')->first();
    }

    private function latestProposal(int $clientId): ?array
    {
        return $this->proposals
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function latestContract(int $clientId): ?array
    {
        return $this->contracts
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function latestProgram(int $clientId): ?array
    {
        return $this->programs
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function clientStandardRows(int $clientId): array
    {
        return $this->db->table('client_standards')
            ->select('client_standards.*, standards.code AS standard_code, standards.scheme_type, iaf_codes.code AS iaf_code, iaf_codes.title AS iaf_title, food_chain_categories.code AS food_category_code, food_chain_categories.title AS food_category_title, food_chain_categories.description AS food_category_description')
            ->join('standards', 'standards.id = client_standards.standard_id')
            ->join('iaf_codes', 'iaf_codes.id = client_standards.iaf_code_id', 'left')
            ->join('food_chain_categories', 'food_chain_categories.id = client_standards.food_chain_category_id', 'left')
            ->where('client_standards.client_id', $clientId)
            ->get()
            ->getResultArray();
    }

    private function latestCertificationApplication(int $clientId): ?array
    {
        if (! $this->db->tableExists('certification_applications')) {
            return null;
        }

        return $this->db->table('certification_applications')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function applicationReviewPayload(array $client, array $review, ?array $application, array $standards): array
    {
        $stored = [];
        if (! empty($review['review_payload'])) {
            $stored = json_decode((string) $review['review_payload'], true) ?: [];
        }

        $standardText = implode(', ', array_filter(array_map(
            static fn (array $row): string => (string) ($row['standard_code'] ?? ''),
            $standards
        )));

        $defaults = array_merge([
            'application_id' => $application['application_number'] ?? '',
            'communication_language' => 'English',
            'client_type' => 'New Client',
            'management_system_complexity' => 'The client management system is of medium complexity.',
            'effective_employees' => $client['effective_employee_count'] ?? $client['employee_count'] ?? '',
            'haccp_plans_processes' => '',
            'shifts_auditing' => $client['shift_count'] ?? '',
            'seasonal_activity' => ! empty($client['seasonal_operations']) ? 'Yes' : 'No',
            'legal_requirements' => '',
            'product_process_risks' => '',
            'risk_classification' => $review['risk_rating'] ?? '',
            'technical_issues' => '',
            'safety_requirements' => '',
            'technological_regulatory_context' => '',
            'design_development' => 'No',
            'installation_commissioning' => 'No',
            'standard_exclusions' => 'No',
            'outsourced_activity_details' => $client['outsourced_processes'] ?? 'No',
            'incident' => 'None',
            'scope_change' => 'No',
            'employee_change' => 'No',
            'common_management_system' => 'No',
            'employee_justification' => '',
            'invoice_established' => 'No',
            'standards_text' => $standardText,
            'certification_route' => $this->defaultCertificationRoute($standards),
            'accreditation_body' => $this->defaultAccreditationBody($standards),
            'initial_audit_type' => 'Initial Certification',
            'audit_category' => $this->scopeCategorySummary($standards),
            'competence_requirements' => 'Competence requirement meet.',
            'days_allotted' => $review['md5_duration_days'] ?? '3.00',
            'stage1_days' => $review['stage1_days'] ?? '1.00',
            'stage2_days' => $review['stage2_days'] ?? '2.00',
            'surveillance1_days' => '2.00',
            'surveillance2_days' => '2.00',
            'recertification_days' => '2.00',
            'reduction_days_allotted' => '0.00',
            'reduction_stage1_days' => '0.00',
            'reduction_stage2_days' => '0.00',
            'reduction_surveillance1_days' => '0.00',
            'reduction_surveillance2_days' => '0.00',
            'reduction_recertification_days' => '0.00',
            'reduction_percentage' => $review['integrated_reduction_percent'] ?? '0.00',
            'no_design' => 'None',
            'prior_knowledge' => 'None',
            'maturity_of_system' => 'None',
            'registered_scheme' => 'None',
            'low_risk_product' => 'None',
            'single_activity_process' => 'None',
            'shift_work' => 'None',
            'very_small_site' => 'None',
            'repetitive_work' => 'None',
            'others_reduction' => 'None',
            'no_offsite_work' => 'None',
            'application_status' => $review['recommendation'] ?? 'Accepted',
            'reviewer_comments' => $review['review_notes'] ?? 'I reviewed the application with the best of my knowledge and now it is submitting to Quality Manager for approval.',
        ], $this->applicationDefaults->reviewDefaults($client, $standards));

        $payload = array_merge($defaults, $stored);
        foreach ($defaults as $key => $value) {
            if (trim((string) ($payload[$key] ?? '')) === '' && trim((string) $value) !== '') {
                $payload[$key] = $value;
            }
        }
        if ($standardText !== '') {
            $payload['standards_text'] = $standardText;
        }
        $payload = $this->normaliseAccreditationReviewPayload($payload, $standards);
        $applicationHaccpPlans = $this->applicationAnswerByKey((int) ($application['id'] ?? 0), 'haccp_plans_processes');
        if ($applicationHaccpPlans !== null) {
            $payload['haccp_plans_processes'] = $applicationHaccpPlans;
        }
        $payload = $this->applyDurationToReviewPayload(
            $payload,
            $this->durationService->calculateApplicationReview($client, $standards, $payload)
        );

        return $payload;
    }

    private function applicationAnswerByKey(int $applicationId, string $questionKey): ?string
    {
        if ($applicationId <= 0 || ! $this->db->tableExists('application_answers')) {
            return null;
        }

        $row = $this->db->table('application_answers')
            ->select('application_answers.answer_text')
            ->join('application_questions', 'application_questions.id = application_answers.application_question_id')
            ->where('application_answers.application_id', $applicationId)
            ->where('application_questions.question_key', $questionKey)
            ->get(1)
            ->getRowArray();

        $answer = trim((string) ($row['answer_text'] ?? ''));

        return $answer === '' ? null : $answer;
    }

    private function applyDurationToReviewPayload(array $payload, array $duration): array
    {
        $payload['days_allotted'] = number_format((float) $duration['total_days'], 2, '.', '');
        $payload['stage1_days'] = number_format((float) $duration['stage1_days'], 2, '.', '');
        $payload['stage2_days'] = number_format((float) $duration['stage2_days'], 2, '.', '');
        $payload['surveillance1_days'] = number_format((float) ($duration['surveillance1_days'] ?? 1.00), 2, '.', '');
        $payload['surveillance2_days'] = number_format((float) ($duration['surveillance2_days'] ?? 1.00), 2, '.', '');
        $payload['recertification_days'] = number_format((float) ($duration['recertification_days'] ?? $duration['stage2_days']), 2, '.', '');
        $payload['reduction_percentage'] = number_format((float) ($duration['reduction_percent'] ?? 0.00), 2, '.', '');
        $payload['calculation_basis'] = (string) ($duration['basis'] ?? '');

        return $payload;
    }

    private function normaliseAccreditationReviewPayload(array $payload, array $standards): array
    {
        if ($this->isHaccpOnly($standards)) {
            $payload['certification_route'] = 'unaccredited';
            $payload['accreditation_body'] = '';
        } else {
            $route = strtolower(trim((string) ($payload['certification_route'] ?? '')));
            $payload['certification_route'] = $route === 'accredited' ? 'accredited' : 'unaccredited';
            $body = strtoupper(trim((string) ($payload['accreditation_body'] ?? '')));
            $payload['accreditation_body'] = $payload['certification_route'] === 'accredited' && in_array($body, ['IAS', 'SAAC'], true)
                ? $body
                : '';
        }

        $payload['audit_category'] = trim((string) ($payload['audit_category'] ?? '')) !== ''
            ? (string) $payload['audit_category']
            : $this->scopeCategorySummary($standards);

        return $payload;
    }

    private function defaultCertificationRoute(array $standards): string
    {
        return $this->isHaccpOnly($standards) ? 'unaccredited' : 'unaccredited';
    }

    private function defaultAccreditationBody(array $standards): string
    {
        return $this->isHaccpOnly($standards) ? '' : '';
    }

    private function isHaccpOnly(array $standards): bool
    {
        $codes = array_values(array_filter(array_map(
            static fn (array $row): string => strtoupper((string) ($row['standard_code'] ?? '')),
            $standards
        )));

        return $codes !== [] && count($codes) === 1 && str_contains($codes[0], 'HACCP');
    }

    private function scopeCategorySummary(array $standards): string
    {
        $parts = [];
        foreach ($standards as $standard) {
            $code = strtoupper((string) ($standard['standard_code'] ?? ''));
            if (str_contains($code, 'HACCP')) {
                continue;
            }

            $foodCode = trim((string) ($standard['food_category_code'] ?? ''));
            $foodTitle = trim((string) ($standard['food_category_title'] ?? ''));
            if ((str_contains($code, '22000') || str_contains($code, 'FSSC')) && $foodCode !== '') {
                $parts[] = $code . ': Food-chain category ' . $foodCode . ($foodTitle !== '' ? ' - ' . $foodTitle : '');
                continue;
            }

            $iafCode = trim((string) ($standard['iaf_code'] ?? ''));
            $iafTitle = trim((string) ($standard['iaf_title'] ?? ''));
            if ($iafCode !== '') {
                $parts[] = $code . ': IAF ' . $iafCode . ($iafTitle !== '' ? ' - ' . $iafTitle : '');
            }
        }

        return implode("\n", array_unique($parts));
    }

    private function reviewPayloadFromRequest(): array
    {
        $fields = [
            'application_id',
            'communication_language',
            'client_type',
            'management_system_complexity',
            'effective_employees',
            'haccp_plans_processes',
            'shifts_auditing',
            'seasonal_activity',
            'legal_requirements',
            'product_process_risks',
            'risk_classification',
            'technical_issues',
            'safety_requirements',
            'technological_regulatory_context',
            'design_development',
            'installation_commissioning',
            'standard_exclusions',
            'outsourced_activity_details',
            'incident',
            'scope_change',
            'employee_change',
            'common_management_system',
            'employee_justification',
            'invoice_established',
            'standards_text',
            'certification_route',
            'accreditation_body',
            'initial_audit_type',
            'audit_category',
            'competence_requirements',
            'days_allotted',
            'stage1_days',
            'stage2_days',
            'surveillance1_days',
            'surveillance2_days',
            'recertification_days',
            'reduction_days_allotted',
            'reduction_stage1_days',
            'reduction_stage2_days',
            'reduction_surveillance1_days',
            'reduction_surveillance2_days',
            'reduction_recertification_days',
            'reduction_percentage',
            'no_design',
            'prior_knowledge',
            'maturity_of_system',
            'registered_scheme',
            'low_risk_product',
            'single_activity_process',
            'shift_work',
            'very_small_site',
            'repetitive_work',
            'others_reduction',
            'no_offsite_work',
            'application_status',
            'reviewer_comments',
            'calculation_basis',
        ];

        $payload = [];
        foreach ($fields as $field) {
            $value = $this->request->getPost($field);
            $payload[$field] = is_array($value) ? '' : trim((string) $value);
        }

        return $payload;
    }

    private function proposalPayload(array $client, array $proposal, ?array $review, array $standards): array
    {
        $stored = json_decode((string) ($proposal['proposal_payload'] ?? ''), true) ?: [];
        $stored = $this->discardPartialDurationPayload($stored);
        $reviewPayload = json_decode((string) ($review['review_payload'] ?? ''), true) ?: [];
        $duration = $this->durationService->calculateApplicationReview($client, $standards, $reviewPayload);
        $standardsText = implode(', ', array_keys($duration['standard_days'] ?? []));

        return $this->commercialTerms->applyControlledText($this->mergeNonEmptyPayload([
            'legal_documentation' => '-',
            'management_representative' => $client['contact_person'] ?? '',
            'phone_fax' => trim((string) ($client['phone'] ?? '')),
            'number_of_locations' => (string) ($client['number_of_sites'] ?? 1),
            'intro_message' => 'Thank you for expressing your interest in obtaining certification for your company. We are pleased to inform you that we have prepared a tailored certification offer based on the requirements of the applicable standard.',
            'standards_text' => $standardsText,
            'certification_route' => $reviewPayload['certification_route'] ?? 'unaccredited',
            'accreditation_body' => $reviewPayload['accreditation_body'] ?? '',
            'initial_audit_type' => $reviewPayload['initial_audit_type'] ?? 'Initial Certification',
            'total_audit_days' => number_format((float) ($reviewPayload['days_allotted'] ?? $review['md5_duration_days'] ?? $duration['total_days']), 2, '.', ''),
            'stage1_days' => number_format((float) ($reviewPayload['stage1_days'] ?? $review['stage1_days'] ?? $duration['stage1_days']), 2, '.', ''),
            'stage2_days' => number_format((float) ($reviewPayload['stage2_days'] ?? $review['stage2_days'] ?? $duration['stage2_days']), 2, '.', ''),
            'surveillance1_days' => number_format((float) ($reviewPayload['surveillance1_days'] ?? $duration['surveillance1_days'] ?? 1.00), 2, '.', ''),
            'surveillance2_days' => number_format((float) ($reviewPayload['surveillance2_days'] ?? $duration['surveillance2_days'] ?? 1.00), 2, '.', ''),
            'recertification_days' => number_format((float) ($reviewPayload['recertification_days'] ?? $duration['recertification_days'] ?? $duration['stage2_days']), 2, '.', ''),
            'certification_process_obligations' => $this->commercialTerms->text('certification_process_obligations'),
            'payment_terms' => "Certification Audit Fee:\n50% payable upon signing the contract.\n50% payable after receiving the draft copy of the certificate.\n\nSurveillance Audit Fee:\n100% payable one month in advance of the scheduled audit.\n\nAdditional Fees:\nAll additional fees must be paid in advance.",
            'certification_audit_includes' => "Audit planning and preparation.\nReview of management system documentation.\nExecution of the audit, including audit reporting and related documentation.\nIssuance of the certificate, including one copy in English.",
            'surveillance_audit_includes' => "Audit planning and preparation.\nReview of management system documentation.\nConducting the audit, drafting the audit report, and related documentation.\nReissuing the certificate if required due to certification changes.",
            'additional_a4_copy_fee' => '50 USD',
            'certificate_reissue_fee' => '150 USD',
            'extraordinary_audit_1_fee' => '850 USD',
            'extraordinary_audit_2_fee' => '925 USD',
            'vat_invoice_terms' => $this->commercialTerms->text('vat_invoice_terms'),
            'stage1_activity' => $this->commercialTerms->text('stage1_activity'),
            'stage2_activity' => $this->commercialTerms->text('stage2_activity'),
            'certificate_issuance' => $this->commercialTerms->text('certificate_issuance'),
            'surveillance_activity' => $this->commercialTerms->text('surveillance_activity'),
            'audit_time_reference' => 'Audit time is calculated using MD-style rules aligned with ISO/IEC 17021-1 and applicable IAF mandatory documents, considering effective personnel, standards, risk, shifts, sites and reductions.',
            'additional_services' => '',
        ], $stored));
    }

    private function mergeNonEmptyPayload(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if (is_array($value) || trim((string) $value) !== '') {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    private function discardPartialDurationPayload(array $payload): array
    {
        $fields = ['total_audit_days', 'stage1_days', 'stage2_days', 'surveillance1_days', 'surveillance2_days', 'recertification_days'];
        if (($payload['total_audit_days'] ?? '') === '' && ($payload['days_allotted'] ?? '') !== '') {
            $payload['total_audit_days'] = $payload['days_allotted'];
        }

        $filled = 0;
        foreach ($fields as $field) {
            if (trim((string) ($payload[$field] ?? '')) !== '') {
                $filled++;
            }
        }

        if ($filled > 0 && $filled < count($fields)) {
            foreach ($fields as $field) {
                unset($payload[$field]);
            }
        }

        return $payload;
    }

    private function proposalPayloadFromRequest(): array
    {
        return $this->payloadFields([
            'legal_documentation', 'management_representative', 'phone_fax', 'number_of_locations',
            'intro_message', 'standards_text', 'certification_route', 'accreditation_body', 'initial_audit_type',
            'total_audit_days', 'stage1_days', 'stage2_days', 'surveillance1_days', 'surveillance2_days', 'recertification_days',
            'certification_process_obligations', 'payment_terms', 'certification_audit_includes', 'surveillance_audit_includes',
            'additional_a4_copy_fee', 'certificate_reissue_fee', 'extraordinary_audit_1_fee', 'extraordinary_audit_2_fee',
            'vat_invoice_terms', 'stage1_activity', 'stage2_activity', 'certificate_issuance', 'surveillance_activity',
            'audit_time_reference', 'additional_services',
        ]);
    }

    private function contractPayload(array $client, array $proposal, array $contract): array
    {
        $proposalPayload = json_decode((string) ($proposal['proposal_payload'] ?? ''), true) ?: [];
        $stored = json_decode((string) ($contract['contract_payload'] ?? ''), true) ?: [];

        return $this->commercialTerms->applyControlledText(array_merge([
            'legal_documentation' => $proposalPayload['legal_documentation'] ?? '-',
            'management_representative' => $proposalPayload['management_representative'] ?? ($client['contact_person'] ?? ''),
            'phone_fax' => $proposalPayload['phone_fax'] ?? ($client['phone'] ?? ''),
            'number_of_locations' => $proposalPayload['number_of_locations'] ?? (string) ($client['number_of_sites'] ?? 1),
            'standards_text' => $proposalPayload['standards_text'] ?? '',
            'certification_route' => $proposalPayload['certification_route'] ?? 'unaccredited',
            'accreditation_body' => $proposalPayload['accreditation_body'] ?? '',
            'initial_audit_type' => $proposalPayload['initial_audit_type'] ?? 'Initial Certification',
            'total_audit_days' => $proposalPayload['total_audit_days'] ?? '',
            'stage1_days' => $proposalPayload['stage1_days'] ?? '',
            'stage2_days' => $proposalPayload['stage2_days'] ?? '',
            'surveillance1_days' => $proposalPayload['surveillance1_days'] ?? '',
            'surveillance2_days' => $proposalPayload['surveillance2_days'] ?? '',
            'recertification_days' => $proposalPayload['recertification_days'] ?? '',
            'certification_process_obligations' => $proposalPayload['certification_process_obligations'] ?? $this->commercialTerms->text('certification_process_obligations'),
            'payment_terms' => $proposalPayload['payment_terms'] ?? '',
            'certification_audit_includes' => $proposalPayload['certification_audit_includes'] ?? '',
            'surveillance_audit_includes' => $proposalPayload['surveillance_audit_includes'] ?? '',
            'additional_a4_copy_fee' => $proposalPayload['additional_a4_copy_fee'] ?? '50 USD',
            'certificate_reissue_fee' => $proposalPayload['certificate_reissue_fee'] ?? '150 USD',
            'extraordinary_audit_1_fee' => $proposalPayload['extraordinary_audit_1_fee'] ?? '850 USD',
            'extraordinary_audit_2_fee' => $proposalPayload['extraordinary_audit_2_fee'] ?? '925 USD',
            'vat_invoice_terms' => $proposalPayload['vat_invoice_terms'] ?? '',
            'stage1_activity' => $proposalPayload['stage1_activity'] ?? '',
            'stage2_activity' => $proposalPayload['stage2_activity'] ?? '',
            'certificate_issuance' => $proposalPayload['certificate_issuance'] ?? '',
            'surveillance_activity' => $proposalPayload['surveillance_activity'] ?? '',
            'audit_time_reference' => $proposalPayload['audit_time_reference'] ?? '',
            'important_note' => $this->commercialTerms->text('important_note'),
            'contact_line' => 'QSI_CERT TEAM +966569009021 info@qsi-cert.com',
        ], $stored));
    }

    private function contractPayloadFromRequest(): array
    {
        return $this->payloadFields([
            'legal_documentation', 'management_representative', 'phone_fax', 'number_of_locations',
            'standards_text', 'certification_route', 'accreditation_body', 'initial_audit_type',
            'total_audit_days', 'stage1_days', 'stage2_days', 'surveillance1_days', 'surveillance2_days', 'recertification_days',
            'certification_process_obligations', 'payment_terms', 'certification_audit_includes', 'surveillance_audit_includes',
            'additional_a4_copy_fee', 'certificate_reissue_fee', 'extraordinary_audit_1_fee', 'extraordinary_audit_2_fee',
            'vat_invoice_terms', 'stage1_activity', 'stage2_activity', 'certificate_issuance', 'surveillance_activity',
            'audit_time_reference', 'important_note', 'contact_line',
        ]);
    }

    private function payloadFields(array $fields): array
    {
        $payload = [];
        foreach ($fields as $field) {
            $value = $this->request->getPost($field);
            $payload[$field] = is_array($value) ? '' : trim((string) $value);
        }

        return $payload;
    }

    private function defaultCertificationProcessText(): string
    {
        return 'QSI-Cert delivers certification services in accordance with accreditation requirements and applicable standards. Compliance is verified through planned audits and surveillance activities. QSI-Cert maintains auditor competence, confidentiality, impartiality and professional integrity throughout the certification process.';
    }

    private function nextWorkingDay(DateTimeImmutable $date): DateTimeImmutable
    {
        $current = $date;

        while (in_array((int) $current->format('N'), [5, 6], true)) {
            $current = $current->add(new DateInterval('P1D'));
        }

        return $current;
    }

    private function programEvents(int $programId): array
    {
        return $this->events
            ->where('audit_program_id', $programId)
            ->orderBy('planned_start_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    private function approvedPersonnel(): array
    {
        return $this->personnel
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('approval_status', 'approved')
            ->where('personnel_type !=', 'client_representative')
            ->orderBy('full_name', 'ASC')
            ->findAll();
    }

    private function appointmentRows(int $programId): array
    {
        return $this->db->table('auditor_appointments')
            ->select('auditor_appointments.*, personnel.full_name, audit_events.event_type, audit_events.audit_number, audit_events.planned_start_date')
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->join('audit_events', 'audit_events.id = auditor_appointments.audit_event_id')
            ->where('audit_events.audit_program_id', $programId)
            ->orderBy('audit_events.planned_start_date', 'ASC')
            ->orderBy('auditor_appointments.appointment_role', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function eventBelongsToProgram(int $eventId, int $programId): bool
    {
        return $this->events
            ->where('id', $eventId)
            ->where('audit_program_id', $programId)
            ->countAllResults() > 0;
    }

    private function planRows(int $programId): array
    {
        return $this->db->table('audit_plans')
            ->select('audit_plans.*, audit_events.event_type, audit_events.audit_number, audit_events.planned_start_date')
            ->join('audit_events', 'audit_events.id = audit_plans.audit_event_id')
            ->where('audit_events.audit_program_id', $programId)
            ->orderBy('audit_events.planned_start_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function planItemRows(int $programId): array
    {
        return $this->db->table('audit_plan_items')
            ->select('audit_plan_items.*, audit_plans.plan_number, audit_events.audit_number, audit_events.event_type, personnel.full_name AS auditor_name')
            ->join('audit_plans', 'audit_plans.id = audit_plan_items.audit_plan_id')
            ->join('audit_events', 'audit_events.id = audit_plans.audit_event_id')
            ->join('personnel', 'personnel.id = audit_plan_items.auditor_personnel_id', 'left')
            ->where('audit_events.audit_program_id', $programId)
            ->orderBy('audit_plan_items.audit_date', 'ASC')
            ->orderBy('audit_plan_items.start_time', 'ASC')
            ->orderBy('audit_plan_items.sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function eventPlanItemRows(int $eventId): array
    {
        return $this->db->table('audit_plan_items')
            ->select('audit_plan_items.*, audit_plans.plan_number, personnel.full_name AS auditor_name')
            ->join('audit_plans', 'audit_plans.id = audit_plan_items.audit_plan_id')
            ->join('personnel', 'personnel.id = audit_plan_items.auditor_personnel_id', 'left')
            ->where('audit_plans.audit_event_id', $eventId)
            ->orderBy('audit_plan_items.audit_date', 'ASC')
            ->orderBy('audit_plan_items.start_time', 'ASC')
            ->orderBy('audit_plan_items.sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function eventTeamRows(int $eventId): array
    {
        return $this->db->table('auditor_appointments')
            ->select('auditor_appointments.*, personnel.full_name, personnel.email, personnel.user_id')
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->where('auditor_appointments.audit_event_id', $eventId)
            ->orderBy('auditor_appointments.appointment_role', 'ASC')
            ->orderBy('personnel.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function appointmentPersonnel(int $programId): array
    {
        return $this->db->table('auditor_appointments')
            ->select('DISTINCT personnel.id, personnel.full_name', false)
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->join('audit_events', 'audit_events.id = auditor_appointments.audit_event_id')
            ->where('audit_events.audit_program_id', $programId)
            ->orderBy('personnel.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function planBelongsToProgram(int $planId, int $programId): bool
    {
        return $this->db->table('audit_plans')
            ->join('audit_events', 'audit_events.id = audit_plans.audit_event_id')
            ->where('audit_plans.id', $planId)
            ->where('audit_events.audit_program_id', $programId)
            ->countAllResults() > 0;
    }

    private function eventForPlan(int $planId): ?array
    {
        $row = $this->db->table('audit_plans')
            ->select('audit_events.*')
            ->join('audit_events', 'audit_events.id = audit_plans.audit_event_id')
            ->where('audit_plans.id', $planId)
            ->get(1)
            ->getRowArray();

        return $row === null ? null : $row;
    }

    private function reportForEvent(int $eventId): ?array
    {
        return $this->reports
            ->where('audit_event_id', $eventId)
            ->orderBy('version_number', 'DESC')
            ->first();
    }

    private function ensureReport(int $eventId): array
    {
        $report = $this->reportForEvent($eventId);

        if ($report !== null) {
            return $report;
        }

        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'audit_event_id' => $eventId,
            'report_type' => 'audit_execution',
            'version_number' => 1,
            'status' => 'draft',
            'generated_payload' => json_encode(['source' => 'manual_execution'], JSON_THROW_ON_ERROR),
            'editable_payload' => json_encode([], JSON_THROW_ON_ERROR),
            'prepared_by' => (int) session()->get('user_id'),
        ];

        $id = (int) $this->reports->insert($payload);
        $payload['id'] = $id;

        $this->auditLogger->record('create', 'reports', 'report_drafts', $id, null, $payload);

        return $payload;
    }

    private function ensureConformitySections(int $reportId, array $clauses, array $client = [], ?array $event = null, array $planItems = [], array $auditTeam = []): void
    {
        foreach ($clauses as $index => $clause) {
            $clauseId = (int) $clause['id'];
            $existing = $this->reportSections
                ->where('report_draft_id', $reportId)
                ->where('clause_library_id', $clauseId)
                ->where('section_key', 'conformity')
                ->first();

            if ($existing !== null) {
                continue;
            }

            $package = $this->contentEngine->conformitySection($client, $event, $clause, $planItems, $auditTeam);
            $payload = [
                'report_draft_id' => $reportId,
                'clause_library_id' => $clauseId,
                'section_key' => 'conformity',
                'section_title' => trim((string) $clause['standard_code'] . ' ' . (string) $clause['clause_number'] . ' - ' . (string) $clause['clause_title']),
                'section_content' => $package['content'],
                'source_type' => $package['source_type'],
                'auditor_confirmed' => 1,
                'confirmed_by_user_id' => $this->leadAuditorUserId($auditTeam) ?? (int) session()->get('user_id'),
                'confirmed_at' => date('Y-m-d H:i:s'),
                'confirmation_note' => $package['confirmation_note'],
                'sort_order' => $index + 1,
            ];

            $id = (int) $this->reportSections->insert($payload);
            $this->auditLogger->record('create', 'reports', 'report_sections', $id, null, $payload);
        }
    }

    private function smartConformityNotes(array $client, ?array $event, array $clauses, array $planItems, array $auditTeam): array
    {
        $notes = [];
        foreach ($clauses as $clause) {
            $notes[(int) $clause['id']] = $this->contentEngine->conformitySection($client, $event, $clause, $planItems, $auditTeam)['content'];
        }

        return $notes;
    }

    private function clausePoolConformityNote(array $client, ?array $event, array $clause, array $planItems = [], array $auditTeam = []): string
    {
        if ($client !== []) {
            $content = $this->contentPool->conformityNote($client, $event, $clause);
            if ($content !== null) {
                return $content;
            }
        }

        return $client === []
            ? (string) ($clause['predefined_conformity_note'] ?: 'Conformity verified for this clause.')
            : $this->narratives->conformityNote($client, $event, $clause, $planItems, $auditTeam);
    }

    private function leadAuditorUserId(array $auditTeam): ?int
    {
        foreach ($auditTeam as $member) {
            if (($member['appointment_role'] ?? '') === 'lead_auditor' && ! empty($member['user_id'])) {
                return (int) $member['user_id'];
            }
        }

        foreach ($auditTeam as $member) {
            if (! empty($member['user_id'])) {
                return (int) $member['user_id'];
            }
        }

        return null;
    }

    private function reportSectionRows(int $reportId): array
    {
        return $this->db->table('report_sections')
            ->select('report_sections.*, clause_library.clause_number, clause_library.clause_title')
            ->join('clause_library', 'clause_library.id = report_sections.clause_library_id', 'left')
            ->where('report_sections.report_draft_id', $reportId)
            ->orderBy('report_sections.sort_order', 'ASC')
            ->orderBy('report_sections.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function unconfirmedConformitySectionCount(int $reportId): int
    {
        return (int) $this->db->table('report_sections')
            ->where('report_draft_id', $reportId)
            ->where('section_key', 'conformity')
            ->where('auditor_confirmed', 0)
            ->countAllResults();
    }

    private function ncrRows(int $eventId): array
    {
        return $this->db->table('ncrs')
            ->select('ncrs.*, clause_library.clause_number, clause_library.clause_title')
            ->join('clause_library', 'clause_library.id = ncrs.clause_library_id', 'left')
            ->where('ncrs.audit_event_id', $eventId)
            ->orderBy('ncrs.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function clausesForClient(int $clientId): array
    {
        $standardIds = array_column($this->clientStandardRows($clientId), 'standard_id');

        if ($standardIds === []) {
            return [];
        }

        return $this->clauses
            ->select('clause_library.*, standards.code AS standard_code')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->where('clause_library.tenant_id', (int) session()->get('tenant_id'))
            ->whereIn('clause_library.standard_id', $standardIds)
            ->where('clause_library.active', 1)
            ->orderBy('standards.code', 'ASC')
            ->orderBy('clause_library.clause_number', 'ASC')
            ->findAll();
    }

    private function number(string $prefix, int $clientId): string
    {
        return $prefix . '-' . date('YmdHis') . '-' . $clientId . '-' . random_int(100, 999);
    }

    private function nextCertificateNumber(string $standardCode): string
    {
        $prefix = 'QSI-' . $this->certificateStandardPrefix($standardCode);
        $tenantId = (int) session()->get('tenant_id');
        $rows = $this->db->table('certificates')
            ->select('certificate_number')
            ->where('tenant_id', $tenantId)
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

    private function nullableText(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }

    private function dateOrNull(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }

    private function dateTimeOrNull(string $field): ?string
    {
        $value = str_replace('T', ' ', trim((string) $this->request->getPost($field)));

        return $value === '' ? null : Time::parse($value)->toDateTimeString();
    }

    private function dateTimeOrNow(string $field): string
    {
        return $this->dateTimeOrNull($field) ?? date('Y-m-d H:i:s');
    }

    private function decimalOrNull(string $field): ?float
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : (float) $value;
    }

    private function intOrNull(string $field): ?int
    {
        $value = $this->request->getPost($field);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function intQueryOrNull(string $field): ?int
    {
        $value = $this->request->getGet($field);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function checkbox(string $field): int
    {
        return $this->request->getPost($field) === '1' ? 1 : 0;
    }

    private function checklistRowsFromPost(string $field): array
    {
        $rows = $this->request->getPost($field);
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized[] = [
                'key' => (string) $key,
                'group' => trim((string) ($row['group'] ?? '')),
                'ref' => trim((string) ($row['ref'] ?? '')),
                'requirement' => trim((string) ($row['requirement'] ?? '')),
                'action_by' => trim((string) ($row['action_by'] ?? '')),
                'result' => trim((string) ($row['result'] ?? '')),
                'evidence' => trim((string) ($row['evidence'] ?? '')),
            ];
        }

        return $normalized;
    }

    private function money(string $field, float $default = 0.00): float
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? $default : (float) $value;
    }

    private function blankReview(array $client = []): array
    {
        return [
            'application_review_number' => '',
            'document_number' => 'F 28',
            'revision_number' => '4',
            'issue_number' => '2',
            'document_date' => '2025-02-01',
            'completeness_status' => 'pending',
            'risk_rating' => '',
            'recommendation' => '',
            'md5_duration_days' => '3.00',
            'iso22003_duration_days' => '',
            'integrated_reduction_percent' => '',
            'stage1_days' => '1.00',
            'stage2_days' => '2.00',
            'review_notes' => '',
            'review_payload' => '',
            'status' => 'draft',
            'technical_reviewer_name' => '',
            'technical_review_date' => date('Y-m-d'),
            'quality_manager_status' => '',
            'quality_manager_comments' => '',
            'quality_manager_name' => '',
            'quality_manager_date' => '',
            'general_manager_status' => '',
            'general_manager_comments' => '',
            'general_manager_name' => '',
            'general_manager_date' => '',
        ];
    }

    private function blankProposal(int $clientId): array
    {
        return [
            'proposal_number' => $this->number('PROP', $clientId),
            'status' => 'draft',
            'proposal_date' => date('Y-m-d'),
            'client_reference' => '',
            'valid_until' => '',
            'certification_fee' => '0.00',
            'surveillance1_fee' => '0.00',
            'surveillance2_fee' => '0.00',
            'training_fee' => '0.00',
            'travel_fee' => '0.00',
            'accommodation_fee' => '0.00',
            'discount_amount' => '0.00',
            'vat_percent' => '15.00',
            'currency' => 'SAR',
            'proposal_payload' => '',
        ];
    }

    private function blankContract(int $clientId): array
    {
        return [
            'contract_number' => $this->number('CON', $clientId),
            'document_number' => 'F 27',
            'revision_number' => '2',
            'issue_number' => '2',
            'document_date' => '2022-05-15',
            'status' => 'draft',
            'signed_at' => '',
            'signed_by_name' => '',
            'contract_payload' => '',
            'qsi_signatory_name' => '',
            'qsi_signatory_date' => '',
            'client_signatory_name' => '',
            'client_signatory_date' => '',
        ];
    }

    private function blankProgram(array $client, array $standards = [], ?array $review = null): array
    {
        $reviewPayload = [];
        if (! empty($review['review_payload'])) {
            $reviewPayload = json_decode((string) $review['review_payload'], true) ?: [];
        }

        $duration = $this->durationService->calculateApplicationReview($client, $standards, $reviewPayload);
        $cycleDates = $this->certificationCycleDates(date('Y-m-d'));

        return [
            'program_number' => $this->number('AP', (int) ($client['id'] ?? 0)),
            'document_number' => 'F 42',
            'revision_number' => '2',
            'issue_number' => '2',
            'document_date' => '2022-05-15',
            'certificate_issue_date' => date('Y-m-d'),
            'surveillance_1_due_date' => $cycleDates['surveillance1'],
            'surveillance_2_due_date' => $cycleDates['surveillance2'],
            'certificate_expiry_date' => $cycleDates['expiry'],
            'stage1_days' => number_format($duration['stage1_days'], 2, '.', ''),
            'stage2_days' => number_format($duration['stage2_days'], 2, '.', ''),
            'status' => 'planned',
            'prepared_by_name' => '',
            'prepared_date' => date('Y-m-d'),
            'approved_by_name' => '',
            'approved_date' => '',
        ];
    }
}

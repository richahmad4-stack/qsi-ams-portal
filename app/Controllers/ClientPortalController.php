<?php

namespace App\Controllers;

use App\Services\AuditLogger;
use App\Services\DocumentGeneratorService;
use Config\Database;

class ClientPortalController extends BaseController
{
    private DocumentGeneratorService $generator;
    private AuditLogger $auditLogger;
    private const CLIENT_DOCUMENTS = [
        'certification_application' => 'Application',
        'proposal' => 'Proposal',
        'contract' => 'Contract',
    ];

    private const EVENT_DOCUMENTS = [
        'auditor_appointment' => 'Auditor appointment',
        'audit_plan' => 'Audit plan PDF',
        'audit_report' => 'Audit report PDF',
        'ncr_capa' => 'NC/CAPA PDF',
    ];

    public function __construct()
    {
        $this->generator = new DocumentGeneratorService();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        $client = $this->linkedClient();
        if ($client === null) {
            return redirect()->to('/dashboard')->with('error', 'Your login is not linked to a client portal record.');
        }

        return view('client_portal/index', [
            'title' => 'Client Portal',
            'pageTitle' => 'Client Portal',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'documents' => $this->clientDocuments((int) $client['id']),
            'events' => $this->auditEvents((int) $client['id']),
            'ncrs' => $this->ncrRows((int) $client['id']),
            'capas' => $this->capaRows((int) $client['id']),
            'certificates' => $this->certificateRows((int) $client['id']),
            'feedbackRows' => $this->feedbackRows((int) $client['id']),
        ]);
    }

    public function clientDocument(int $clientId, string $documentKey)
    {
        if (! $this->canAccessClient($clientId)) {
            return redirect()->to('/client-portal')->with('error', 'Client document not available.');
        }

        if (! array_key_exists($documentKey, self::CLIENT_DOCUMENTS) || ! $this->clientDocumentAvailable($clientId, $documentKey)) {
            return redirect()->to('/client-portal')->with('error', 'Client document not available.');
        }

        $document = $this->generator->generateClientDocument((int) session()->get('tenant_id'), $clientId, $documentKey, (int) session()->get('user_id'));
        $this->auditLogger->record('download', 'documents', 'generated_documents', (int) $document['id'], null, $document);

        return $this->response->download($document['storage_path'], null)
            ->setFileName($this->downloadName($document['document_title']));
    }

    public function eventDocument(int $eventId, string $documentKey)
    {
        $event = $this->eventForClientUser($eventId);
        if ($event === null) {
            return redirect()->to('/client-portal')->with('error', 'Audit event document not available.');
        }

        if (! array_key_exists($documentKey, self::EVENT_DOCUMENTS) || ! $this->eventDocumentAvailable($eventId, $documentKey)) {
            return redirect()->to('/client-portal')->with('error', 'Audit event document not available.');
        }

        $document = $this->generator->generateEventDocument((int) session()->get('tenant_id'), (int) $event['client_id'], $eventId, $documentKey, (int) session()->get('user_id'));
        $this->auditLogger->record('download', 'documents', 'generated_documents', (int) $document['id'], null, $document);

        return $this->response->download($document['storage_path'], null)
            ->setFileName($this->downloadName($document['document_title']));
    }

    public function certificate(int $certificateId)
    {
        $certificate = $this->certificateForClientUser($certificateId);
        if ($certificate === null) {
            return redirect()->to('/client-portal')->with('error', 'Certificate not available.');
        }

        $document = $this->generator->generateCertificate((int) session()->get('tenant_id'), $certificateId, (int) session()->get('user_id'));
        $this->auditLogger->record('download', 'documents', 'generated_documents', (int) $document['id'], null, $document);

        return $this->response->download($document['storage_path'], null)
            ->setFileName($this->downloadName($document['document_title']));
    }

    public function editCapa(int $capaId)
    {
        $capa = $this->capaForClientUser($capaId);
        if ($capa === null) {
            return redirect()->to('/client-portal')->with('error', 'CAPA not available.');
        }

        return view('client_portal/capa_form', [
            'title' => 'Client CAPA Response',
            'pageTitle' => 'Client CAPA Response',
            'pageSubtitle' => $capa['capa_number'],
            'capa' => $capa,
            'action' => site_url('client-portal/capas/' . $capaId),
        ]);
    }

    public function updateCapa(int $capaId)
    {
        $capa = $this->capaForClientUser($capaId);
        if ($capa === null) {
            return redirect()->to('/client-portal')->with('error', 'CAPA not available.');
        }

        if (in_array((string) ($capa['status'] ?? ''), ['closed', 'verified_closed', 'cancelled'], true)) {
            return redirect()->to('/client-portal')->with('error', 'This CAPA is already closed and cannot be changed from the client portal.');
        }

        if (! $this->validate([
            'immediate_correction' => 'permit_empty',
            'root_cause' => 'required',
            'corrective_action' => 'required',
            'preventive_action' => 'permit_empty',
            'responsible_person' => 'permit_empty|max_length[180]',
            'target_date' => 'permit_empty|valid_date[Y-m-d]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $payload = [
            'immediate_correction' => $this->nullablePost('immediate_correction'),
            'root_cause' => $this->nullablePost('root_cause'),
            'corrective_action' => $this->nullablePost('corrective_action'),
            'preventive_action' => $this->nullablePost('preventive_action'),
            'responsible_person' => $this->nullablePost('responsible_person'),
            'target_date' => $this->nullablePost('target_date'),
            'status' => 'submitted',
        ];

        Database::connect()->table('capas')->where('id', $capaId)->update($payload);
        $this->auditLogger->record('update', 'capas', 'capas', $capaId, $capa, $payload);

        return redirect()->to('/client-portal')->with('success', 'CAPA response submitted.');
    }

    public function saveFeedback()
    {
        $client = $this->linkedClient();
        if ($client === null) {
            return redirect()->to('/dashboard')->with('error', 'Your login is not linked to a client portal record.');
        }

        if (! $this->validate([
            'overall_rating' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
            'communication_rating' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
            'auditor_rating' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
            'report_quality_rating' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $certificateId = $this->intOrNull('certificate_id');
        if ($certificateId !== null && $this->certificateForClientUser($certificateId) === null) {
            return redirect()->to('/client-portal')->with('error', 'Selected certificate is not available for this client.');
        }

        $program = $this->latestProgram((int) $client['id']);
        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'client_id' => (int) $client['id'],
            'audit_program_id' => $program['id'] ?? null,
            'certificate_id' => $certificateId,
            'contact_name' => $this->nullablePost('contact_name') ?: $client['full_name'],
            'contact_email' => $this->nullablePost('contact_email') ?: $client['email'],
            'submitted_at' => date('Y-m-d H:i:s'),
            'overall_rating' => $this->intOrNull('overall_rating'),
            'communication_rating' => $this->intOrNull('communication_rating'),
            'auditor_rating' => $this->intOrNull('auditor_rating'),
            'report_quality_rating' => $this->intOrNull('report_quality_rating'),
            'comments' => $this->nullablePost('comments'),
            'improvement_suggestion' => $this->nullablePost('improvement_suggestion'),
            'status' => 'submitted',
            'created_by' => (int) session()->get('user_id'),
        ];

        $db = Database::connect();
        $db->table('client_feedback')->insert($payload);
        $id = (int) $db->insertID();
        $this->auditLogger->record('create', 'client_feedback', 'client_feedback', $id, null, $payload);

        return redirect()->to('/client-portal')->with('success', 'Feedback submitted.');
    }

    private function linkedClient(): ?array
    {
        return Database::connect()->table('personnel')
            ->select('personnel.*, clients.company, clients.certification_status')
            ->join('clients', 'clients.id = personnel.client_id')
            ->where('personnel.tenant_id', (int) session()->get('tenant_id'))
            ->where('personnel.user_id', (int) session()->get('user_id'))
            ->where('personnel.personnel_type', 'client_representative')
            ->where('personnel.deleted_at', null)
            ->where('clients.deleted_at', null)
            ->get(1)
            ->getRowArray();
    }

    private function canAccessClient(int $clientId): bool
    {
        $client = $this->linkedClient();

        return $client !== null && (int) $client['client_id'] === $clientId;
    }

    private function clientDocuments(int $clientId): array
    {
        $documents = [];
        foreach (self::CLIENT_DOCUMENTS as $key => $label) {
            $available = $this->clientDocumentAvailable($clientId, $key);
            $documents[] = [
                'key' => $key,
                'label' => $label,
                'available' => $available,
                'status' => $available ? 'Available PDF' : 'Not ready yet',
                'url' => site_url('client-portal/documents/' . $clientId . '/' . $key),
            ];
        }

        return $documents;
    }

    private function auditEvents(int $clientId): array
    {
        $events = Database::connect()->table('audit_events')
            ->select('audit_events.*, audit_programs.client_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', (int) session()->get('tenant_id'))
            ->where('audit_programs.client_id', $clientId)
            ->orderBy('audit_events.planned_start_date', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($events as &$event) {
            $event['client_documents'] = $this->eventDocuments((int) $event['id']);
        }

        return $events;
    }

    private function ncrRows(int $clientId): array
    {
        return Database::connect()->table('ncrs')
            ->select('ncrs.*, audit_events.event_type, audit_events.audit_number')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('ncrs.tenant_id', (int) session()->get('tenant_id'))
            ->where('audit_programs.client_id', $clientId)
            ->orderBy('ncrs.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function capaRows(int $clientId): array
    {
        return Database::connect()->table('capas')
            ->select('capas.*, ncrs.ncr_number, audit_events.event_type, audit_events.audit_number')
            ->join('ncrs', 'ncrs.id = capas.ncr_id')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('capas.tenant_id', (int) session()->get('tenant_id'))
            ->where('audit_programs.client_id', $clientId)
            ->orderBy('capas.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function certificateRows(int $clientId): array
    {
        return Database::connect()->table('certificates')
            ->select('certificates.*, standards.code AS standard_code')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.tenant_id', (int) session()->get('tenant_id'))
            ->where('certificates.client_id', $clientId)
            ->orderBy('certificates.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function clientDocumentAvailable(int $clientId, string $documentKey): bool
    {
        $db = Database::connect();
        $tenantId = (int) session()->get('tenant_id');

        return match ($documentKey) {
            'certification_application' => $db->table('certification_applications')
                ->where('tenant_id', $tenantId)
                ->where('client_id', $clientId)
                ->whereIn('status', ['submitted', 'under_review', 'approved', 'completed'])
                ->countAllResults() > 0,
            'proposal' => $db->table('proposals')
                ->where('tenant_id', $tenantId)
                ->where('client_id', $clientId)
                ->whereIn('status', ['sent', 'accepted', 'approved', 'completed'])
                ->countAllResults() > 0,
            'contract' => $db->table('contracts')
                ->where('tenant_id', $tenantId)
                ->where('client_id', $clientId)
                ->whereIn('status', ['sent', 'signed', 'active', 'approved', 'completed'])
                ->countAllResults() > 0,
            default => false,
        };
    }

    private function eventDocuments(int $eventId): array
    {
        $documents = [];
        foreach (self::EVENT_DOCUMENTS as $key => $label) {
            $available = $this->eventDocumentAvailable($eventId, $key);
            $documents[] = [
                'key' => $key,
                'label' => $label,
                'available' => $available,
                'status' => $available ? 'Available PDF' : 'Not ready yet',
                'url' => site_url('client-portal/audit-events/' . $eventId . '/documents/' . $key),
            ];
        }

        return $documents;
    }

    private function eventDocumentAvailable(int $eventId, string $documentKey): bool
    {
        $db = Database::connect();
        $tenantId = (int) session()->get('tenant_id');

        return match ($documentKey) {
            'auditor_appointment' => $db->table('auditor_appointments')
                ->where('tenant_id', $tenantId)
                ->where('audit_event_id', $eventId)
                ->countAllResults() > 0,
            'audit_plan' => $db->table('audit_plans')
                ->where('tenant_id', $tenantId)
                ->where('audit_event_id', $eventId)
                ->countAllResults() > 0,
            'audit_report' => $db->table('report_drafts')
                ->where('tenant_id', $tenantId)
                ->where('audit_event_id', $eventId)
                ->whereIn('status', ['submitted', 'approved', 'completed'])
                ->countAllResults() > 0,
            'ncr_capa' => $db->table('report_drafts')
                ->where('tenant_id', $tenantId)
                ->where('audit_event_id', $eventId)
                ->whereIn('status', ['submitted', 'approved', 'completed'])
                ->countAllResults() > 0,
            default => false,
        };
    }

    private function feedbackRows(int $clientId): array
    {
        return Database::connect()->table('client_feedback')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function latestProgram(int $clientId): ?array
    {
        return Database::connect()->table('audit_programs')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();
    }

    private function eventForClientUser(int $eventId): ?array
    {
        $client = $this->linkedClient();
        if ($client === null) {
            return null;
        }

        return Database::connect()->table('audit_events')
            ->select('audit_events.*, audit_programs.client_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', (int) session()->get('tenant_id'))
            ->where('audit_programs.client_id', (int) $client['client_id'])
            ->where('audit_events.id', $eventId)
            ->get(1)
            ->getRowArray();
    }

    private function certificateForClientUser(int $certificateId): ?array
    {
        $client = $this->linkedClient();
        if ($client === null) {
            return null;
        }

        return Database::connect()->table('certificates')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('client_id', (int) $client['client_id'])
            ->where('id', $certificateId)
            ->get(1)
            ->getRowArray();
    }

    private function capaForClientUser(int $capaId): ?array
    {
        $client = $this->linkedClient();
        if ($client === null) {
            return null;
        }

        return Database::connect()->table('capas')
            ->select('capas.*, ncrs.ncr_number, ncrs.requirement, ncrs.finding, ncrs.objective_evidence, audit_events.event_type, audit_events.audit_number')
            ->join('ncrs', 'ncrs.id = capas.ncr_id')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('capas.tenant_id', (int) session()->get('tenant_id'))
            ->where('audit_programs.client_id', (int) $client['client_id'])
            ->where('capas.id', $capaId)
            ->get(1)
            ->getRowArray();
    }

    private function downloadName(string $title): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($title)) . '.pdf';
    }

    private function nullablePost(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }

    private function intOrNull(string $field): ?int
    {
        $value = $this->request->getPost($field);

        return $value === null || $value === '' ? null : (int) $value;
    }
}

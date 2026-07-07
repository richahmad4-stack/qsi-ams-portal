<?php

namespace App\Controllers\Workflow;

use App\Controllers\BaseController;
use App\Models\CertificateModel;
use App\Models\ClientModel;
use App\Services\AuditLogger;
use App\Services\DocumentGeneratorService;
use Config\Database;
use DateTimeImmutable;

class WorkflowDocumentController extends BaseController
{
    private ClientModel $clients;
    private CertificateModel $certificates;
    private DocumentGeneratorService $generator;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->clients = new ClientModel();
        $this->certificates = new CertificateModel();
        $this->generator = new DocumentGeneratorService();
        $this->auditLogger = new AuditLogger();
    }

    public function clientDocument(int $clientId, string $documentKey)
    {
        $tenantId = (int) session()->get('tenant_id');
        $client = $this->clients->find($clientId);

        if ($client === null || (int) $client['tenant_id'] !== $tenantId) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if (! in_array($documentKey, $this->allowedClientDocuments(), true)) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Document type not available.');
        }

        $document = $this->generator->generateClientDocument($tenantId, $clientId, $documentKey, (int) session()->get('user_id'));
        $this->auditLogger->record('download', 'documents', 'generated_documents', (int) $document['id'], null, $document);

        return $this->response->download($document['storage_path'], null)
            ->setFileName($this->downloadName($document['document_title']));
    }

    public function certificate(int $certificateId)
    {
        $tenantId = (int) session()->get('tenant_id');
        $certificate = $this->certificates
            ->where('tenant_id', $tenantId)
            ->where('id', $certificateId)
            ->first();

        if ($certificate === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Certificate not found.');
        }

        $document = $this->generator->generateCertificate($tenantId, $certificateId, (int) session()->get('user_id'));
        $this->auditLogger->record('download', 'documents', 'generated_documents', (int) $document['id'], null, $document);

        return $this->response->download($document['storage_path'], null)
            ->setFileName($this->downloadName($document['document_title']));
    }

    public function certificateWord(int $certificateId)
    {
        $tenantId = (int) session()->get('tenant_id');
        $certificate = $this->certificates
            ->where('tenant_id', $tenantId)
            ->where('id', $certificateId)
            ->first();

        if ($certificate === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Certificate not found.');
        }

        $document = $this->generator->generateCertificateWord($tenantId, $certificateId, (int) session()->get('user_id'));
        $this->auditLogger->record('download', 'documents', 'generated_documents', (int) $document['id'], null, $document);

        return $this->response->download($document['storage_path'], null)
            ->setFileName($this->downloadName($document['document_title'], 'docx'));
    }

    public function eventDocument(int $clientId, int $eventId, string $documentKey)
    {
        $tenantId = (int) session()->get('tenant_id');
        $client = $this->clients->find($clientId);

        if ($client === null || (int) $client['tenant_id'] !== $tenantId) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        if (! in_array($documentKey, ['auditor_appointment', 'audit_plan', 'audit_report', 'ncr_capa', 'technical_review', 'decision_report'], true)) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Event document type not available.');
        }

        $event = $this->eventForClient($tenantId, $clientId, $eventId);
        if ($event === null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', 'Audit event not found.');
        }

        if (($lockMessage = $this->surveillanceLockMessage($event)) !== null) {
            return redirect()->to('/workflow/certification/' . $clientId)->with('error', $lockMessage);
        }

        $document = $this->generator->generateEventDocument($tenantId, $clientId, $eventId, $documentKey, (int) session()->get('user_id'));
        $this->auditLogger->record('download', 'documents', 'generated_documents', (int) $document['id'], null, $document);

        return $this->response->download($document['storage_path'], null)
            ->setFileName($this->downloadName($document['document_title']));
    }

    private function allowedClientDocuments(): array
    {
        return [
            'proposal',
            'certification_application',
            'application_review',
            'contract',
            'audit_program',
            'audit_plan',
            'audit_report',
            'technical_review',
            'decision_report',
            'feedback',
        ];
    }

    private function downloadName(string $title, string $extension = 'pdf'): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($title)) . '.' . $extension;
    }

    private function eventForClient(int $tenantId, int $clientId, int $eventId): ?array
    {
        $row = Database::connect()->table('audit_events')
            ->select('audit_events.*, audit_programs.surveillance_1_due_date, audit_programs.surveillance_2_due_date')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->where('audit_programs.client_id', $clientId)
            ->where('audit_events.id', $eventId)
            ->get(1)
            ->getRowArray();

        return $row === null ? null : $row;
    }

    private function surveillanceLockMessage(array $event): ?string
    {
        if (! in_array((string) ($event['event_type'] ?? ''), ['surveillance1', 'surveillance2'], true)) {
            return null;
        }

        if (in_array((string) ($event['status'] ?? ''), ['completed', 'closed'], true)) {
            return null;
        }

        $dueDate = (string) ($event['event_type'] === 'surveillance1'
            ? ($event['surveillance_1_due_date'] ?? '')
            : ($event['surveillance_2_due_date'] ?? ''));

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
}

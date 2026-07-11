<?php

namespace App\Services;

use App\Models\GeneratedDocumentModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Image;

class DocumentGeneratorService
{
    private BaseConnection $db;
    private GeneratedDocumentModel $documents;
    private AuditReportNarrativeService $narratives;
    private CertificationApplicationDefaults $applicationDefaults;
    private CommercialTermsService $commercialTerms;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->documents = new GeneratedDocumentModel();
        $this->narratives = new AuditReportNarrativeService();
        $this->applicationDefaults = new CertificationApplicationDefaults();
        $this->commercialTerms = new CommercialTermsService();
    }

    public function generateClientDocument(int $tenantId, int $clientId, string $documentKey, int $userId): array
    {
        $client = $this->client($tenantId, $clientId);
        $data = $this->dataForClientDocument($tenantId, $clientId, $documentKey);
        $title = $this->documentTitle($documentKey, $client['company']);
        $html = $this->renderHtml($documentKey, $title, $client, $data);

        return $this->writePdf($tenantId, $clientId, $documentKey, $title, null, null, $html, $userId);
    }

    public function generateEventDocument(int $tenantId, int $clientId, int $eventId, string $documentKey, int $userId): array
    {
        $client = $this->client($tenantId, $clientId);
        if ($documentKey === 'audit_report') {
            $this->ensureEventChecklist($tenantId, $clientId, $eventId, $userId);
        }
        $data = $this->dataForEventDocument($tenantId, $clientId, $eventId);
        $eventLabel = ucwords(str_replace('_', ' ', (string) ($data['event']['event_type'] ?? 'Audit')));
        $title = $eventLabel . ' ' . ucwords(str_replace('_', ' ', $documentKey)) . ' - ' . $client['company'];
        $html = $this->renderHtml($documentKey, $title, $client, $data);

        return $this->writePdf($tenantId, $clientId, $documentKey, $title, 'audit_events', $eventId, $html, $userId);
    }

    public function generateCertificate(int $tenantId, int $certificateId, int $userId): array
    {
        $certificate = $this->certificateRecord($tenantId, $certificateId);
        $title = 'Certificate - ' . $certificate['certificate_number'];
        $html = $this->certificateHtml($certificate);

        return $this->writePdf(
            $tenantId,
            (int) $certificate['client_id'],
            'certificate',
            $title,
            'certificates',
            $certificateId,
            $html,
            $userId
        );
    }

    public function generateCertificateWord(int $tenantId, int $certificateId, int $userId): array
    {
        $certificate = $this->certificateRecord($tenantId, $certificateId);
        $title = 'Certificate Word - ' . $certificate['certificate_number'];

        return $this->writeCertificateDocx($tenantId, (int) $certificate['client_id'], $title, $certificateId, $certificate, $userId);
    }

    private function certificateRecord(int $tenantId, int $certificateId): array
    {
        $certificate = $this->db->table('certificates')
            ->select('certificates.*, clients.company, clients.legal_name, clients.address, clients.city, clients.country, clients.client_logo_path, standards.code AS standard_code, standards.name AS standard_name')
            ->join('clients', 'clients.id = certificates.client_id')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.tenant_id', $tenantId)
            ->where('certificates.id', $certificateId)
            ->get(1)
            ->getRowArray();

        if ($certificate === null) {
            throw new \RuntimeException('Certificate not found.');
        }

        return $certificate;
    }

    private function dataForClientDocument(int $tenantId, int $clientId, string $documentKey): array
    {
        $program = $this->latest('audit_programs', ['tenant_id' => $tenantId, 'client_id' => $clientId]);
        $events = $program === null ? [] : $this->db->table('audit_events')
            ->where('audit_program_id', (int) $program['id'])
            ->orderBy('planned_start_date', 'ASC')
            ->get()
            ->getResultArray();

        $technicalReview = $this->latestTechnicalReviewForEvents($tenantId, $events);
        $decision = $technicalReview === null ? null : $this->decisionForReview($tenantId, (int) $technicalReview['id']);

        return [
            'client' => $this->client($tenantId, $clientId),
            'certification_application' => $this->applicationData($tenantId, $clientId),
            'application_review' => $this->latest('application_reviews', ['client_id' => $clientId]),
            'proposal' => $this->latest('proposals', ['tenant_id' => $tenantId, 'client_id' => $clientId]),
            'contract' => $this->latest('contracts', ['tenant_id' => $tenantId, 'client_id' => $clientId]),
            'program' => $program,
            'events' => $events,
            'standards' => $this->clientStandards($clientId),
            'clauses' => $this->clientClauses($tenantId, $clientId),
            'appointments' => $program === null ? [] : $this->appointmentsForProgram((int) $program['id']),
            'plan_items' => $program === null ? [] : $this->auditPlanItems((int) $program['id']),
            'reports' => $events === [] ? [] : $this->reportsForEvents($tenantId, $events),
            'ncrs' => $events === [] ? [] : $this->ncrsForEvents($tenantId, $events),
            'capas' => $events === [] ? [] : $this->capasForEvents($tenantId, $events),
            'technical_review' => $technicalReview,
            'decision' => $decision,
            'certificates' => $this->certificateRows($tenantId, $clientId),
            'feedback' => $this->latestFeedback($tenantId, $clientId),
        ];
    }

    private function dataForEventDocument(int $tenantId, int $clientId, int $eventId): array
    {
        $program = $this->latest('audit_programs', ['tenant_id' => $tenantId, 'client_id' => $clientId]);
        $event = $program === null ? null : $this->db->table('audit_events')
            ->where('audit_program_id', (int) $program['id'])
            ->where('id', $eventId)
            ->get(1)
            ->getRowArray();

        if ($program === null || $event === null) {
            throw new \RuntimeException('Audit event not found.');
        }

        $events = [$event];
        $technicalReview = $this->technicalReviewForEvent($tenantId, $eventId);
        $decision = $technicalReview === null ? null : $this->decisionForReview($tenantId, (int) $technicalReview['id']);

        return [
            'client' => $this->client($tenantId, $clientId),
            'proposal' => $this->latest('proposals', ['tenant_id' => $tenantId, 'client_id' => $clientId]),
            'contract' => $this->latest('contracts', ['tenant_id' => $tenantId, 'client_id' => $clientId]),
            'certification_application' => $this->applicationData($tenantId, $clientId),
            'application_review' => $this->latest('application_reviews', ['client_id' => $clientId]),
            'program' => $program,
            'audit_plan' => $this->auditPlanForEvent($eventId),
            'event' => $event,
            'events' => $events,
            'standards' => $this->clientStandards($clientId),
            'appointments' => $this->appointmentsForEvent($eventId),
            'plan_items' => $this->auditPlanItemsForEvent($eventId),
            'reports' => $this->reportsForEvents($tenantId, $events),
            'report_sections' => $this->reportSectionsForEvent($tenantId, $eventId),
            'ncrs' => $this->ncrsForEvents($tenantId, $events),
            'capas' => $this->capasForEvent($tenantId, $eventId),
            'technical_review' => $technicalReview,
            'decision' => $decision,
            'certificates' => $this->certificateRows($tenantId, $clientId),
            'feedback' => $this->latestFeedback($tenantId, $clientId),
        ];
    }

    private function ensureEventChecklist(int $tenantId, int $clientId, int $eventId, int $userId): void
    {
        $report = $this->latest('report_drafts', [
            'tenant_id' => $tenantId,
            'audit_event_id' => $eventId,
            'report_type' => 'audit_execution',
        ]);

        if ($report === null) {
            $this->db->table('report_drafts')->insert([
                'tenant_id' => $tenantId,
                'audit_event_id' => $eventId,
                'report_type' => 'audit_execution',
                'version_number' => 1,
                'status' => 'draft',
                'generated_payload' => json_encode(['source' => 'event_pdf_generation'], JSON_THROW_ON_ERROR),
                'editable_payload' => json_encode([], JSON_THROW_ON_ERROR),
                'prepared_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $report = ['id' => $this->db->insertID()];
        }

        $client = $this->client($tenantId, $clientId);
        $event = $this->db->table('audit_events')->where('id', $eventId)->get(1)->getRowArray();
        $planItems = $this->auditPlanItemsForEvent($eventId);
        $auditTeam = $this->appointmentsForEvent($eventId);
        $clauses = $this->db->table('clause_library')
            ->select('clause_library.*, standards.code AS standard_code')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->join('client_standards', 'client_standards.standard_id = clause_library.standard_id')
            ->where('clause_library.tenant_id', $tenantId)
            ->where('client_standards.client_id', $clientId)
            ->where('clause_library.active', 1)
            ->orderBy('standards.code', 'ASC')
            ->orderBy('clause_library.clause_number', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($clauses as $index => $clause) {
            $exists = $this->db->table('report_sections')
                ->where('report_draft_id', (int) $report['id'])
                ->where('clause_library_id', (int) $clause['id'])
                ->where('section_key', 'conformity')
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $this->db->table('report_sections')->insert([
                'report_draft_id' => (int) $report['id'],
                'clause_library_id' => (int) $clause['id'],
                'section_key' => 'conformity',
                'section_title' => trim((string) $clause['standard_code'] . ' ' . (string) $clause['clause_number'] . ' - ' . (string) $clause['clause_title']),
                'section_content' => $this->narratives->conformityNote($client, $event, $clause, $planItems, $auditTeam),
                'source_type' => 'clause_pool',
                'auditor_confirmed' => 1,
                'confirmed_by_user_id' => $this->leadAuditorUserId($auditTeam),
                'confirmed_at' => date('Y-m-d H:i:s'),
                'confirmation_note' => 'Auto-confirmed on behalf of the assigned auditor from approved Clause Pool / system content.',
                'sort_order' => $index + 1,
            ]);
        }
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

    private function renderHtml(string $documentKey, string $title, array $client, array $data): string
    {
        if ($documentKey === 'certification_application') {
            return $this->certificationApplicationHtml($title, $client, $data);
        }

        if ($documentKey === 'application_review') {
            return $this->applicationReviewHtml($title, $client, $data);
        }

        if ($documentKey === 'proposal') {
            return $this->proposalHtml($title, $client, $data);
        }

        if ($documentKey === 'contract') {
            return $this->contractHtml($title, $client, $data);
        }

        if ($documentKey === 'audit_program') {
            return $this->auditProgramHtml($title, $client, $data);
        }

        if ($documentKey === 'auditor_appointment') {
            return $this->auditorAppointmentHtml($title, $client, $data);
        }

        if ($documentKey === 'audit_plan' && ! empty($data['event'])) {
            return $this->auditPlanHtml($title, $client, $data);
        }

        $sections = match ($documentKey) {
            'proposal' => $this->proposalSections($data),
            'contract' => $this->contractSections($data),
            'audit_program' => $this->auditProgramSections($data),
            'audit_plan' => $this->auditPlanSections($data),
            'audit_report' => $this->auditReportSections($data),
            'ncr_capa' => $this->ncrCapaSections($data),
            'technical_review' => $this->technicalReviewSections($data),
            'decision_report' => $this->decisionSections($data),
            'feedback' => $this->feedbackSections($data),
            default => [['Summary', 'Document type is not configured yet.']],
        };

        return $this->baseHtml($documentKey, $title, $client, $sections, $data);
    }

    private function baseHtml(string $documentKey, string $title, array $client, array $sections, array $data = []): string
    {
        $body = '';
        foreach ($sections as [$heading, $content]) {
            $body .= '<h2>' . esc($heading) . '</h2>' . $content;
        }

        $control = $this->standardDocumentControl($documentKey, $data + ['client' => $client]);
        $subtitle = $control['subtitle'] ?? 'QSI certification document';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . '</style></head><body>'
            . '<header class="document-header"><table><tbody>'
            . '<tr><td class="brand-cell" rowspan="4">' . $this->logoHtml('pdf-logo') . '</td>'
            . '<td class="title-cell" rowspan="4"><div class="doc-title">' . esc($title) . '</div><div class="doc-subtitle">' . esc($subtitle) . '</div></td>'
            . '<td class="control-label">Document No.</td><td class="control-value">' . esc((string) ($control['number'] ?? 'AMS')) . '</td></tr>'
            . '<tr><td class="control-label">Revision No.</td><td class="control-value">' . esc((string) ($control['revision'] ?? '1')) . '</td></tr>'
            . '<tr><td class="control-label">Issue No.</td><td class="control-value">' . esc((string) ($control['issue'] ?? '1')) . '</td></tr>'
            . '<tr><td class="control-label">Date</td><td class="control-value">' . esc((string) ($control['date'] ?? date('Y-m-d'))) . '</td></tr>'
            . '</tbody></table></header>'
            . '<section class="client"><table><tr><th>Client</th><td>' . esc($client['company']) . '</td></tr><tr><th>Scope</th><td>' . nl2br(esc((string) ($client['scope'] ?? ''))) . '</td></tr></table></section>'
            . $body
            . '</body></html>';
    }

    private function standardDocumentControl(string $documentKey, array $data = []): array
    {
        $fallbacks = [
            'auditor_appointment' => ['number' => 'F 30_app', 'revision' => '2', 'issue' => '2', 'date' => '2022-05-15'],
            'audit_plan' => ['number' => 'F 31', 'revision' => '2', 'issue' => '2', 'date' => '2022-05-15'],
            'audit_report' => ['number' => 'F 32', 'revision' => '2', 'issue' => '2', 'date' => '2022-05-15'],
            'ncr_capa' => ['number' => 'F 33', 'revision' => '2', 'issue' => '2', 'date' => '2022-05-15'],
            'technical_review' => ['number' => 'F 34', 'revision' => '2', 'issue' => '2', 'date' => '2022-05-15'],
            'decision_report' => ['number' => 'F 35', 'revision' => '2', 'issue' => '2', 'date' => '2022-05-15'],
            'feedback' => ['number' => 'F 36', 'revision' => '2', 'issue' => '2', 'date' => '2022-05-15'],
        ];
        $key = $this->documentTemplateKey($documentKey, $data);
        $fallback = $fallbacks[$documentKey] ?? ['number' => 'AMS', 'revision' => '1', 'issue' => '1', 'date' => date('Y-m-d')];
        $template = $this->db->table('document_templates')
            ->where('tenant_id', (int) ($data['client']['tenant_id'] ?? session()->get('tenant_id') ?? 1))
            ->where('template_key', $key)
            ->get(1)
            ->getRowArray();

        return [
            'template_key' => $key,
            'number' => trim((string) ($template['document_number'] ?? '')) ?: $fallback['number'],
            'revision' => trim((string) ($template['revision_number'] ?? '')) ?: $fallback['revision'],
            'issue' => trim((string) ($template['issue_number'] ?? '')) ?: $fallback['issue'],
            'date' => $this->displayDocumentDate((string) ($template['document_date'] ?? $fallback['date'])),
            'subtitle' => 'QSI certification document',
        ];
    }

    private function documentTemplateKey(string $documentKey, array $data = []): string
    {
        if ($documentKey !== 'audit_report') {
            return $documentKey;
        }

        $eventType = (string) ($data['event']['event_type'] ?? ($data['events'][0]['event_type'] ?? ''));

        return match ($eventType) {
            'initial_stage1' => 'stage1_report',
            'initial_stage2' => 'stage2_report',
            'surveillance1', 'surveillance2' => 'surveillance_report',
            'recertification' => 'recertification_report',
            default => 'stage2_report',
        };
    }

    private function displayDocumentDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? $date : date('d.m.Y', $timestamp);
    }

    private function certificateHtml(array $certificate): string
    {
        $qr = $this->qrDataUri((string) $certificate['qr_payload']);
        $background = $this->certificateBackgroundDataUri();
        $approvedSignature = $this->certificateSignatureHtml('assets/img/qsi-signature-approved.png');
        $printedSignature = $this->certificateSignatureHtml('assets/img/qsi-signature-printed.png');
        $clientLogo = $this->clientCertificateLogoHtml((string) ($certificate['client_logo_path'] ?? ''));
        $standardCode = (string) ($certificate['standard_code'] ?? '');
        $certificateNumber = (string) ($certificate['certificate_number'] ?? '');
        $companyName = (string) ($certificate['legal_name'] ?: $certificate['company']);
        $companyClass = strlen($companyName) > 45 ? ' company-long' : (strlen($companyName) > 32 ? ' company-medium' : '');
        $issueDate = $this->certificateDate((string) ($certificate['issue_date'] ?? ''));
        $initialDate = $this->certificateDate((string) ($certificate['initial_certification_date'] ?? $certificate['issue_date'] ?? ''));
        $surveillance1 = $this->certificateCycleDate((string) ($certificate['issue_date'] ?? ''), '+1 year');
        $surveillance2 = $this->certificateCycleDate((string) ($certificate['issue_date'] ?? ''), '+2 years');
        $expiryDate = $this->certificateDate((string) ($certificate['expiry_date'] ?? ''));
        $scope = (string) ($certificate['scope'] ?? '');
        $scopeClass = strlen($scope) > 150 ? ' scope-long' : (strlen($scope) > 95 ? ' scope-medium' : '');
        $address = trim(implode(', ', array_filter([
            (string) ($certificate['address'] ?? ''),
            (string) ($certificate['city'] ?? ''),
            (string) ($certificate['country'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->certificateCss() . '</style></head><body>'
            . '<div class="certificate-page"' . ($background === '' ? '' : ' style="background-image: url(' . esc($background, 'attr') . ');"') . '>'
            . '<div class="certificate-content">'
            . $clientLogo
            . '<div class="certificate-intro">This is to certify the ' . esc($this->certificateSystemName($standardCode)) . ' of</div>'
            . '<div class="certificate-company' . esc($companyClass, 'attr') . '">' . esc($companyName) . '</div>'
            . '<div class="certificate-address">' . esc($address) . '</div>'
            . '<div class="certificate-compliance">has been assessed and found to be in compliance with the ' . esc(str_contains(strtoupper($standardCode), 'HACCP') ? 'document' : 'Standard') . '</div>'
            . '<div class="certificate-standard">' . esc($standardCode) . '</div>'
            . '<div class="certificate-description">' . nl2br(esc($this->certificateStandardDescription($standardCode))) . '</div>'
            . '<div class="certificate-applicable">applicable to</div>'
            . '<div class="certificate-scope' . esc($scopeClass, 'attr') . '">' . nl2br(esc($scope)) . '</div>'
            . '<table class="certificate-dates"><colgroup><col class="date-label"><col class="date-value"><col class="date-label"><col class="date-value"></colgroup><tbody>'
            . '<tr><th>Initial Certification Date:</th><td>' . esc($initialDate) . '</td><th>Certification Date:</th><td>' . esc($issueDate) . '</td></tr>'
            . '<tr><th>Surveillance 1 Date:</th><td>' . esc($surveillance1) . '</td><th>Surveillance 2 Date:</th><td>' . esc($surveillance2) . '</td></tr>'
            . '<tr><th>Valid Till:</th><td>' . esc($expiryDate) . '</td><th>Certificate No.:</th><td class="certificate-number-cell">' . esc($certificateNumber) . '</td></tr>'
            . '</tbody></table>'
            . '<div class="certificate-validity-note">This Certificate is valid upon the successful completion of periodic surveillance audits to maintain compliance with the relevant standards.</div>'
            . '<table class="certificate-signatures"><tbody><tr>'
            . '<td><div class="signature-line">' . $approvedSignature . '</div><div>Approved by</div></td>'
            . '<td><div class="signature-line">' . $printedSignature . '</div><div>Printed by</div></td>'
            . '</tr></tbody></table>'
            . '<div class="certificate-verification-block">'
            . '<img src="' . esc($qr, 'attr') . '" alt="Certificate QR">'
            . '<div class="certificate-validity">Validity code: <strong>' . esc($certificateNumber) . '</strong></div>'
            . '<div class="certificate-verify">Check validity of the certificate using this code on <strong>certificate.qsicert.ca</strong><br>Or email us at <strong>info@qsi-cert.com</strong><br>QSI-CERT - P. O. Box No 246049 Riyadh 11312 Kingdom of Saudi Arabia</div>'
            . '</div>'
            . '</div></div></body></html>';
    }

    private function certificateSystemName(string $standardCode): string
    {
        $code = strtoupper($standardCode);

        return match (true) {
            str_contains($code, 'HACCP') => 'Hazard Analysis Critical Control Point System',
            str_contains($code, '22000') || str_contains($code, 'FSSC') => 'Food Safety Management System',
            str_contains($code, '9001') => 'Quality Management System',
            str_contains($code, '14001') => 'Environmental Management System',
            str_contains($code, '45001') => 'Occupational Health and Safety Management System',
            str_contains($code, '13485') => 'Medical Device Quality Management System',
            default => 'Management System',
        };
    }

    private function certificateStandardDescription(string $standardCode): string
    {
        $code = strtoupper($standardCode);

        return match (true) {
            str_contains($code, 'HACCP') => 'General Principles of Food Hygiene and Guidelines for the implementation and certification of the Hazard Analysis and Critical Control Points (HACCP) system, as per the Codex Alimentarius, CXC 1-1969 (2020).',
            str_contains($code, '22000') => 'Food safety management systems - Requirements for any organization in the food chain.',
            str_contains($code, 'FSSC') => 'Food safety system certification requirements for organizations in the food chain.',
            str_contains($code, '9001') => 'Quality management systems - Requirements.',
            str_contains($code, '14001') => 'Environmental management systems - Requirements with guidance for use.',
            str_contains($code, '45001') => 'Occupational health and safety management systems - Requirements with guidance for use.',
            str_contains($code, '13485') => 'Medical devices - Quality management systems - Requirements for regulatory purposes.',
            default => 'Applicable certification standard and scheme requirements.',
        };
    }

    private function certificateDate(string $date): string
    {
        if (trim($date) === '') {
            return '';
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? $date : date('d-m-Y', $timestamp);
    }

    private function certificateCycleDate(string $issueDate, string $modifier): string
    {
        if (trim($issueDate) === '') {
            return '';
        }

        $timestamp = strtotime($modifier . ' -1 day', strtotime($issueDate));

        return $timestamp === false ? '' : date('d-m-Y', $timestamp);
    }

    private function writeCertificateDocx(int $tenantId, int $clientId, string $title, int $certificateId, array $certificate, int $userId): array
    {
        $standardCode = (string) ($certificate['standard_code'] ?? '');
        $certificateNumber = (string) ($certificate['certificate_number'] ?? '');
        $companyName = (string) ($certificate['legal_name'] ?: $certificate['company']);
        $companyFontSize = strlen($companyName) > 45 ? 17.5 : (strlen($companyName) > 32 ? 19.5 : 22);
        $issueDate = $this->certificateDate((string) ($certificate['issue_date'] ?? ''));
        $initialDate = $this->certificateDate((string) ($certificate['initial_certification_date'] ?? $certificate['issue_date'] ?? ''));
        $surveillance1 = $this->certificateCycleDate((string) ($certificate['issue_date'] ?? ''), '+1 year');
        $surveillance2 = $this->certificateCycleDate((string) ($certificate['issue_date'] ?? ''), '+2 years');
        $expiryDate = $this->certificateDate((string) ($certificate['expiry_date'] ?? ''));
        $address = trim(implode(', ', array_filter([
            (string) ($certificate['address'] ?? ''),
            (string) ($certificate['city'] ?? ''),
            (string) ($certificate['country'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));
        $clientLogoPath = $this->writableUploadPath((string) ($certificate['client_logo_path'] ?? ''));

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Aptos');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginLeft' => (int) round(Converter::cmToTwip(5.6)),
            'marginRight' => (int) round(Converter::cmToTwip(1.7)),
            'marginTop' => (int) round(Converter::cmToTwip(2.7)),
            'marginBottom' => (int) round(Converter::cmToTwip(0.8)),
        ]);

        $backgroundPath = $this->publicAssetPath('assets/img/qsi-certificate-template.jpeg');
        if ($backgroundPath !== '') {
            $section->addImage($backgroundPath, [
                'width' => Converter::cmToPixel(21),
                'height' => Converter::cmToPixel(29.7),
                'wrappingStyle' => Image::WRAPPING_STYLE_BEHIND,
                'positioning' => Image::POSITION_ABSOLUTE,
                'posHorizontal' => Image::POSITION_HORIZONTAL_LEFT,
                'posHorizontalRel' => Image::POSITION_RELATIVE_TO_PAGE,
                'posVertical' => Image::POSITION_VERTICAL_TOP,
                'posVerticalRel' => Image::POSITION_RELATIVE_TO_PAGE,
                'marginLeft' => 0,
                'marginTop' => 0,
            ]);
        }

        if ($clientLogoPath !== '') {
            $section->addImage($clientLogoPath, [
                'width' => Converter::cmToPixel(2.6),
                'height' => Converter::cmToPixel(1.55),
                'wrappingStyle' => Image::WRAPPING_STYLE_INFRONT,
                'positioning' => Image::POSITION_ABSOLUTE,
                'posHorizontal' => Image::POSITION_HORIZONTAL_RIGHT,
                'posHorizontalRel' => Image::POSITION_RELATIVE_TO_MARGIN,
                'posVertical' => Image::POSITION_VERTICAL_TOP,
                'posVerticalRel' => Image::POSITION_RELATIVE_TO_PAGE,
                'marginTop' => Converter::cmToPixel(3.8),
            ]);
        }

        $section->addText(
            $this->certificateDocxText('This is to certify the ' . $this->certificateSystemName($standardCode) . ' of'),
            ['size' => 11],
            ['spaceAfter' => Converter::pointToTwip(22)]
        );
        $section->addText(
            $this->certificateDocxText($companyName),
            ['size' => $companyFontSize, 'bold' => true],
            ['spaceAfter' => Converter::pointToTwip(4)]
        );
        $section->addText($this->certificateDocxText($address), ['size' => 9], ['spaceAfter' => Converter::pointToTwip(24)]);
        $section->addText(
            $this->certificateDocxText('has been assessed and found to be in compliance with the ' . (str_contains(strtoupper($standardCode), 'HACCP') ? 'document' : 'Standard')),
            ['size' => 11],
            ['spaceAfter' => Converter::pointToTwip(16)]
        );
        $section->addText($this->certificateDocxText($standardCode), ['size' => 30], ['spaceAfter' => Converter::pointToTwip(10)]);
        $this->addDocxMultilineText(
            $section,
            $this->certificateStandardDescription($standardCode),
            ['size' => 9.5, 'bold' => true, 'italic' => true],
            ['spaceAfter' => Converter::pointToTwip(16)]
        );
        $section->addText($this->certificateDocxText('applicable to'), ['size' => 14], ['spaceAfter' => Converter::pointToTwip(8)]);
        $this->addDocxMultilineText(
            $section,
            (string) ($certificate['scope'] ?? ''),
            ['size' => 15, 'bold' => true],
            ['spaceAfter' => Converter::pointToTwip(10)]
        );

        $dateTable = $section->addTable([
            'borderTopSize' => 8,
            'borderBottomSize' => 8,
            'borderColor' => '1f2933',
            'cellMarginTop' => 45,
            'cellMarginBottom' => 45,
            'cellMarginLeft' => 55,
            'cellMarginRight' => 55,
        ]);
        $this->addCertificateDateRow($dateTable, 'Initial Certification Date:', $initialDate, 'Certification Date:', $issueDate);
        $this->addCertificateDateRow($dateTable, 'Surveillance 1 Date:', $surveillance1, 'Surveillance 2 Date:', $surveillance2);
        $this->addCertificateDateRow($dateTable, 'Valid Till:', $expiryDate, 'Certificate No.:', $certificateNumber, true);

        $section->addText(
            $this->certificateDocxText('This Certificate is valid upon the successful completion of periodic surveillance audits to maintain compliance with the relevant standards.'),
            ['size' => 8.5, 'italic' => true],
            ['spaceBefore' => Converter::pointToTwip(4), 'spaceAfter' => Converter::pointToTwip(24)]
        );

        $signatures = $section->addTable(['cellMarginTop' => 30, 'cellMarginBottom' => 30]);
        $signatures->addRow(760);
        $approvedSignaturePath = $this->publicAssetPath('assets/img/qsi-signature-approved.png');
        $printedSignaturePath = $this->publicAssetPath('assets/img/qsi-signature-printed.png');
        $approved = $signatures->addCell(2850);
        if ($approvedSignaturePath !== '') {
            $approved->addImage($approvedSignaturePath, ['width' => 112, 'height' => 30, 'alignment' => Jc::CENTER]);
        }
        $approved->addText('', [], ['borderBottomSize' => 6, 'borderBottomColor' => '1f2933']);
        $approved->addText($this->certificateDocxText('Approved by'), ['size' => 8.8], ['alignment' => Jc::CENTER]);
        $printed = $signatures->addCell(2850);
        if ($printedSignaturePath !== '') {
            $printed->addImage($printedSignaturePath, ['width' => 112, 'height' => 30, 'alignment' => Jc::CENTER]);
        }
        $printed->addText('', [], ['borderBottomSize' => 6, 'borderBottomColor' => '1f2933']);
        $printed->addText($this->certificateDocxText('Printed by'), ['size' => 8.8], ['alignment' => Jc::CENTER]);

        $verification = $section->addTable(['cellMarginTop' => 15, 'cellMarginBottom' => 15]);
        $verification->addRow();
        $qrCell = $verification->addCell(3550);
        $qrPath = $this->qrPngPath((string) ($certificate['qr_payload'] ?? $certificateNumber));
        if ($qrPath !== '') {
            $qrCell->addImage($qrPath, ['width' => 68, 'height' => 68]);
        }
        $qrCell->addText($this->certificateDocxText('Validity code: ' . $certificateNumber), ['size' => 8.1, 'bold' => true, 'color' => '1c6d8a']);
        $qrCell->addText($this->certificateDocxText('Check validity of the certificate using this code on certificate.qsicert.ca'), ['size' => 6.9]);
        $qrCell->addText($this->certificateDocxText('Or email us at info@qsi-cert.com'), ['size' => 6.9]);
        $qrCell->addText($this->certificateDocxText('QSI-CERT - P. O. Box No 246049 Riyadh 11312 Kingdom of Saudi Arabia'), ['size' => 6.9]);

        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'tenant_' . $tenantId . DIRECTORY_SEPARATOR . 'client_' . $clientId;
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower('certificate-word-' . $title)) . '-' . date('YmdHis') . '.docx';
        $path = $directory . DIRECTORY_SEPARATOR . $fileName;
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $record = [
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'document_key' => 'certificate_docx',
            'document_title' => $title,
            'related_table' => 'certificates',
            'related_id' => $certificateId,
            'storage_path' => $path,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'generated_by' => $userId,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $id = (int) $this->documents->insert($record);
        $record['id'] = $id;

        return $record;
    }

    private function addDocxMultilineText(mixed $container, string $text, array $fontStyle, array $paragraphStyle): void
    {
        $lines = preg_split('/\R+/', trim($text)) ?: [];
        if ($lines === []) {
            $container->addText('', $fontStyle, $paragraphStyle);

            return;
        }

        $lastIndex = count($lines) - 1;
        foreach ($lines as $index => $line) {
            $container->addText($this->certificateDocxText($line), $fontStyle, $index === $lastIndex ? $paragraphStyle : ['spaceAfter' => 0]);
        }
    }

    private function addCertificateDateRow(mixed $table, string $leftLabel, string $leftValue, string $rightLabel, string $rightValue, bool $rightValueEmphasis = false): void
    {
        $table->addRow();
        $table->addCell(2100)->addText($this->certificateDocxText($leftLabel), ['size' => 8.5, 'bold' => true]);
        $table->addCell(1450)->addText($this->certificateDocxText($leftValue), ['size' => 8.5], ['alignment' => Jc::RIGHT]);
        $table->addCell(1800)->addText($this->certificateDocxText($rightLabel), ['size' => 8.5, 'bold' => true]);
        $table->addCell(1700)->addText(
            $this->certificateDocxText($rightValue),
            ['size' => $rightValueEmphasis ? 8.1 : 8.5, 'bold' => $rightValueEmphasis, 'color' => $rightValueEmphasis ? '1c6d8a' : '111827'],
            ['alignment' => Jc::RIGHT]
        );
    }

    private function certificateDocxText(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function writePdf(int $tenantId, ?int $clientId, string $key, string $title, ?string $relatedTable, ?int $relatedId, string $html, int $userId): array
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'tenant_' . $tenantId;
        if ($clientId !== null) {
            $directory .= DIRECTORY_SEPARATOR . 'client_' . $clientId;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($key . '-' . $title)) . '-' . date('YmdHis') . '.pdf';
        $path = $directory . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($path, $dompdf->output());

        $record = [
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'document_key' => $key,
            'document_title' => $title,
            'related_table' => $relatedTable,
            'related_id' => $relatedId,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'generated_by' => $userId,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $id = (int) $this->documents->insert($record);
        $record['id'] = $id;

        return $record;
    }

    private function proposalSections(array $data): array
    {
        $proposal = $data['proposal'] ?? [];
        $client = $data['client'] ?? [];
        $payload = $this->proposalPayloadForDocument($data);
        $preparedBy = $this->userDisplayName($proposal['created_by'] ?? null);
        $approvedBy = $this->userDisplayName($proposal['approved_by'] ?? null);
        $acceptedByClient = in_array((string) ($proposal['status'] ?? ''), ['accepted', 'approved'], true)
            ? (string) ($client['contact_person'] ?? $payload['management_representative'] ?? '')
            : '';
        $acceptedAt = in_array((string) ($proposal['status'] ?? ''), ['accepted', 'approved'], true)
            ? (string) ($proposal['approved_at'] ?? '')
            : '';

        return [
            ['Proposal Control', $this->keyValueTable([
                'Proposal Number' => $proposal['proposal_number'] ?? 'Not created',
                'Proposal Date' => $proposal['proposal_date'] ?? substr((string) ($proposal['created_at'] ?? ''), 0, 10),
                'Client Reference' => $proposal['client_reference'] ?? '',
                'Status' => $proposal['status'] ?? '',
                'Valid Until' => $proposal['valid_until'] ?? '',
                'Prepared By' => $preparedBy,
                'Prepared Date' => substr((string) ($proposal['created_at'] ?? $proposal['proposal_date'] ?? ''), 0, 10),
            ])],
            ['Client Acceptance', $this->keyValueTable([
                'Accepted By Client' => $acceptedByClient,
                'Client Designation' => $client['designation'] ?? 'Authorized Representative',
                'Acceptance Date' => $acceptedAt,
                'QSI Approved / Recorded By' => $approvedBy,
            ])],
            ['Client Detail', $this->keyValueTable([
                'Company / Organisation' => $client['company'] ?? '',
                'Address' => $client['address'] ?? '',
                'Legal Documentation' => $payload['legal_documentation'] ?? '',
                'Management Representative' => $payload['management_representative'] ?? '',
                'Email' => $client['email'] ?? '',
                'Phone / Fax' => $payload['phone_fax'] ?? '',
                'Scope of Certification' => $client['scope'] ?? '',
                'Number of Employees' => $client['employee_count'] ?? '',
                'Number of Locations / Sites' => $payload['number_of_locations'] ?? '',
                'VAT %' => $proposal['vat_percent'] ?? '',
            ])],
            ['Proposal Introduction', '<p>' . nl2br(esc((string) ($payload['intro_message'] ?? ''))) . '</p>'],
            ['Company and Scope of Business', $this->keyValueTable([
                'Scope of Certification Requested' => $client['scope'] ?? '',
                'Number of Employees in Certified Company' => $client['employee_count'] ?? '',
                'Number of Locations Outside Head Office' => $payload['number_of_locations'] ?? '',
            ])],
            ['Audit Scheme', $this->keyValueTable([
                'Standard' => $payload['standards_text'] ?? implode(', ', array_column($data['standards'] ?? [], 'standard_code')),
                'Accreditation Body' => $payload['accreditation_body'] ?? '',
                'Initial Audit Type' => $payload['initial_audit_type'] ?? '',
            ]) . $this->recordTable([[
                'total_audit_days' => $payload['total_audit_days'] ?? '',
                'stage1_days' => $payload['stage1_days'] ?? '',
                'stage2_days' => $payload['stage2_days'] ?? '',
                'surveillance1_days' => $payload['surveillance1_days'] ?? '',
                'surveillance2_days' => $payload['surveillance2_days'] ?? '',
                'recertification_days' => $payload['recertification_days'] ?? '',
            ]], ['total_audit_days', 'stage1_days', 'stage2_days', 'surveillance1_days', 'surveillance2_days', 'recertification_days'])],
            ['Certification Process and Obligations', $this->commercialObligationsHtml((string) ($payload['certification_process_obligations'] ?? ''))],
            ['Fees Detail', $this->keyValueTable([
                'Currency' => $proposal['currency'] ?? '',
                'Initial Certification Audit' => $this->money($proposal['certification_fee'] ?? 0),
                'Surveillance Audit 1' => $this->money($proposal['surveillance1_fee'] ?? 0),
                'Surveillance Audit 2' => $this->money($proposal['surveillance2_fee'] ?? 0),
                'Travel Costs' => $this->money($proposal['travel_fee'] ?? 0),
                'Accommodation Costs' => $this->money($proposal['accommodation_fee'] ?? 0),
                'Additional Services' => $this->money($proposal['training_fee'] ?? 0),
                'Discount' => $this->money($proposal['discount_amount'] ?? 0),
                'VAT Amount' => $this->money($proposal['vat_amount'] ?? 0),
                'Total Cost with VAT' => $this->money($proposal['grand_total'] ?? 0),
            ])],
            ['Payment Terms', '<p>' . nl2br(esc((string) ($payload['payment_terms'] ?? ''))) . '</p>'],
            ['Certification Audit Includes', '<p>' . nl2br(esc((string) ($payload['certification_audit_includes'] ?? ''))) . '</p>'],
            ['Surveillance Audit Includes', '<p>' . nl2br(esc((string) ($payload['surveillance_audit_includes'] ?? ''))) . '</p>'],
            ['Cost of Additional Services', $this->keyValueTable([
                'Additional A4 Copy' => $payload['additional_a4_copy_fee'] ?? '',
                'Certificate Reissue' => $payload['certificate_reissue_fee'] ?? '',
                'Extraordinary Audit 1' => $payload['extraordinary_audit_1_fee'] ?? '',
                'Extraordinary Audit 2' => $payload['extraordinary_audit_2_fee'] ?? '',
            ])],
            ['VAT and Invoice Terms', '<p>' . nl2br(esc((string) ($payload['vat_invoice_terms'] ?? ''))) . '</p>'],
            ['Audit Activities', $this->keyValueTable([
                'Stage 1' => $payload['stage1_activity'] ?? '',
                'Stage 2' => $payload['stage2_activity'] ?? '',
                'Issuance of Certificate' => $payload['certificate_issuance'] ?? '',
                'Surveillance Audit' => $payload['surveillance_activity'] ?? '',
                'Audit Time Reference' => $payload['audit_time_reference'] ?? '',
            ])],
            ['Acceptance and Authorization', $this->commercialAcceptanceTable(
                $preparedBy !== '' ? $preparedBy : 'Engr. Mohammad Ahmad',
                substr((string) ($proposal['created_at'] ?? $proposal['proposal_date'] ?? date('Y-m-d')), 0, 10),
                $acceptedByClient !== '' ? $acceptedByClient : (string) ($client['company'] ?? 'Client Authorized Representative'),
                substr($acceptedAt !== '' ? $acceptedAt : (string) ($proposal['approved_at'] ?? ''), 0, 10),
                in_array((string) ($proposal['status'] ?? ''), ['accepted', 'approved'], true)
            )],
            ['Important Note', $this->commercialImportantNoteHtml($payload)],
            ['Certification Assessment Note', '<p>Following the audit and based on the auditor&apos;s recommendations, QSI-Cert will conduct the certification assessment. If the QSI assessor confirms that all requirements, accreditation conditions, and contractual terms have been met, the corresponding certificates will be issued.</p>'],
        ];
    }

    private function proposalHtml(string $title, array $client, array $data): string
    {
        $proposal = $data['proposal'] ?? [];
        $payload = $this->proposalPayloadForDocument($data);
        $sections = $this->proposalSections($data);
        $body = '';

        foreach ($sections as [$heading, $content]) {
            $body .= '<h2>' . esc($heading) . '</h2>' . $content;
        }

        $cover = $this->commercialCoverHtml(
            'Certification Proposal',
            $client,
            $payload,
            [
                'Proposal Number' => (string) ($proposal['proposal_number'] ?? 'Not created'),
                'Proposal Date' => (string) ($proposal['proposal_date'] ?? substr((string) ($proposal['created_at'] ?? ''), 0, 10)),
                'Valid Until' => (string) ($proposal['valid_until'] ?? ''),
            ]
        );

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->commercialDocumentCss() . '</style></head><body>'
            . $cover
            . '<main class="commercial-body">'
            . $this->commercialBodyHeaderHtml('Certification Proposal', [
                'Proposal No.' => (string) ($proposal['proposal_number'] ?? 'Not created'),
                'Proposal Date' => (string) ($proposal['proposal_date'] ?? substr((string) ($proposal['created_at'] ?? ''), 0, 10)),
                'Valid Until' => (string) ($proposal['valid_until'] ?? ''),
                'Status' => (string) ($proposal['status'] ?? ''),
            ])
            . '<section class="client"><table><tr><th>Client</th><td>' . esc($client['company']) . '</td></tr><tr><th>Scope</th><td>' . nl2br(esc((string) ($client['scope'] ?? ''))) . '</td></tr></table></section>'
            . $body
            . '</main>'
            . '</body></html>';
    }

    private function commercialBodyHeaderHtml(string $documentTitle, array $controlRows): string
    {
        $rows = array_slice(array_filter($controlRows, static fn ($value): bool => trim((string) $value) !== ''), 0, 4, true);
        $fillerIndex = 1;
        while (count($rows) < 4) {
            $rows[str_repeat(' ', $fillerIndex)] = '';
            $fillerIndex++;
        }

        $html = '<header class="commercial-doc-header"><table><tbody>';
        $first = true;
        foreach ($rows as $label => $value) {
            $html .= '<tr>';
            if ($first) {
                $html .= '<td class="commercial-doc-logo" rowspan="4">' . $this->logoHtml('pdf-logo') . '</td>'
                    . '<td class="commercial-doc-title" rowspan="4">' . esc($documentTitle) . '<div>QSI certification document</div></td>';
                $first = false;
            }

            $html .= '<td class="commercial-doc-label">' . esc((string) $label) . '</td><td class="commercial-doc-value">' . esc((string) $value) . '</td></tr>';
        }

        return $html . '</tbody></table></header>';
    }

    private function commercialCoverHtml(string $documentType, array $client, array $payload, array $meta): string
    {
        $coverRows = [
            ['Client Name', (string) ($client['company'] ?? '')],
        ];
        foreach ($meta as $label => $value) {
            if (trim((string) $value) === '') {
                continue;
            }
            $coverRows[] = [(string) $label, (string) $value];
        }

        $standards = trim((string) ($payload['standards_text'] ?? ''));
        if ($standards !== '') {
            $coverRows[] = ['Standard(s)', $standards];
        }

        $scope = trim((string) ($client['scope'] ?? ''));
        if ($scope !== '') {
            $coverRows[] = ['Certification Scope', $scope];
        }

        $coverTableRows = '';
        foreach ($coverRows as [$label, $value]) {
            $coverTableRows .= '<tr><th>' . esc($label) . '</th><td>' . nl2br(esc($value)) . '</td></tr>';
        }

        $cityImage = $this->assetDataUri('assets/img/qsi-cover-city.png');
        $cityHtml = $cityImage !== ''
            ? '<img class="cover-city-img" src="' . esc($cityImage, 'attr') . '" alt="Riyadh skyline">'
            : '<div class="cover-city-fallback"></div>';
        $titleWord = str_contains(strtolower($documentType), 'contract') ? 'CONTRACT' : 'PROPOSAL';
        return '<section class="commercial-cover">'
            . '<div class="cover-city">' . $cityHtml . '</div>'
            . '<div class="cover-footer"><span>+966-56-900-90-21</span><span>info@qsi-cert.com</span></div>'
            . '<div class="cover-logo">' . $this->logoHtml('cover-logo-img') . '</div>'
            . '<div class="cover-company">QSI-CERT CO.</div>'
            . '<div class="cover-rule"></div>'
            . '<div class="cover-title">' . esc($titleWord) . '</div>'
            . '<div class="cover-subtitle">For Certification Services</div>'
            . '<div class="cover-tagline">Your Partner in <strong>Excellence &amp; Compliance</strong></div>'
            . '<table class="cover-badges"><tbody><tr>'
            . '<td>' . $this->coverBadgeHtml('assets/img/qsi-cover-badge-certification.png', 'Certification badge') . '<b>Accredited<br>Certification</b><small>Controlled certification cycle</small></td>'
            . '<td>' . $this->coverBadgeHtml('assets/img/qsi-cover-badge-assessment.png', 'Assessment badge') . '<b>Technical<br>Assessment</b><small>Competent audit team</small></td>'
            . '<td>' . $this->coverBadgeHtml('assets/img/qsi-cover-badge-decision.png', 'Decision badge') . '<b>Impartial<br>Decision</b><small>Independent review</small></td>'
            . '</tr></tbody></table>'
            . '<div class="cover-label">Prepared for</div>'
            . '<table class="cover-info"><tbody>' . $coverTableRows . '</tbody></table>'
            . '</section>';
    }

    private function coverBadgeHtml(string $relativePath, string $alt): string
    {
        $badge = $this->assetDataUri($relativePath);

        if ($badge === '') {
            return '<span class="cover-badge-fallback">QSI</span>';
        }

        return '<img class="cover-badge-img" src="' . esc($badge, 'attr') . '" alt="' . esc($alt, 'attr') . '">';
    }

    private function commercialObligationsHtml(string $text): string
    {
        $headings = [
            'Access to Site and Records',
            'Availability of Last Audit Report',
            'Evidence of Certification Process',
        ];
        $html = '<div class="commercial-obligations">';
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $lines = array_values(array_filter(preg_split('/\R/', $paragraph) ?: [], static fn (string $line): bool => trim($line) !== ''));
            if ($lines !== [] && in_array(trim($lines[0]), $headings, true)) {
                $html .= '<h3>' . esc(trim($lines[0])) . '</h3>';
                $body = trim(implode("\n", array_slice($lines, 1)));
                if ($body !== '') {
                    $html .= '<p>' . nl2br(esc($body)) . '</p>';
                }
            } else {
                $html .= '<p>' . nl2br(esc($paragraph)) . '</p>';
            }
        }

        return $html . '</div>';
    }

    private function proposalPayloadForDocument(array $data): array
    {
        $proposal = $data['proposal'] ?? [];
        $client = $data['client'] ?? [];
        $review = $data['application_review'] ?? [];
        $reviewPayload = json_decode((string) (($data['application_review']['review_payload'] ?? '') ?: ''), true) ?: [];
        $duration = (new AuditDurationService())->calculateApplicationReview($client, $data['standards'] ?? [], $reviewPayload);
        $stored = json_decode((string) ($proposal['proposal_payload'] ?? ''), true) ?: [];
        $stored = $this->discardPartialDurationSet($stored);

        $defaults = [
            'legal_documentation' => '-',
            'management_representative' => $client['contact_person'] ?? '',
            'phone_fax' => $client['phone'] ?? '',
            'number_of_locations' => (string) ($client['number_of_sites'] ?? 1),
            'intro_message' => 'Thank you for expressing your interest in obtaining certification for your company. We are pleased to submit this certification proposal based on the submitted application and application review.',
            'standards_text' => implode(', ', array_keys($duration['standard_days'] ?? [])),
            'accreditation_body' => $reviewPayload['accreditation_body'] ?? 'QSI-Cert',
            'initial_audit_type' => $reviewPayload['initial_audit_type'] ?? 'Initial Certification',
            'total_audit_days' => number_format((float) ($reviewPayload['days_allotted'] ?? $review['md5_duration_days'] ?? $duration['total_days']), 2),
            'stage1_days' => number_format((float) ($reviewPayload['stage1_days'] ?? $review['stage1_days'] ?? $duration['stage1_days']), 2),
            'stage2_days' => number_format((float) ($reviewPayload['stage2_days'] ?? $review['stage2_days'] ?? $duration['stage2_days']), 2),
            'surveillance1_days' => number_format((float) ($reviewPayload['surveillance1_days'] ?? $duration['surveillance1_days'] ?? 1.00), 2),
            'surveillance2_days' => number_format((float) ($reviewPayload['surveillance2_days'] ?? $duration['surveillance2_days'] ?? 1.00), 2),
            'recertification_days' => number_format((float) ($reviewPayload['recertification_days'] ?? $duration['recertification_days'] ?? $duration['stage2_days']), 2),
            'certification_process_obligations' => $this->officialCommercialText('certification_process_obligations'),
            'payment_terms' => "Certification Audit Fee:\n50% payable upon signing the contract.\n50% payable before certificate issue.\n\nSurveillance Audit Fee:\n100% payable one month in advance of the scheduled surveillance audit.\n\nAdditional Fees:\nAdditional services, extra audit days, travel and accommodation are payable as agreed.",
            'certification_audit_includes' => "Audit planning and preparation.\nStage 1 document/readiness review.\nStage 2 on-site implementation audit.\nAudit reporting and technical review.\nCertification decision processing.\nCertificate issue after approval.",
            'surveillance_audit_includes' => "Audit planning and preparation.\nReview of changes since previous audit.\nSurveillance audit execution and reporting.\nFollow-up of previous findings and certification conditions.\nTechnical review and maintain-certification decision where applicable.",
            'additional_a4_copy_fee' => '50 USD',
            'certificate_reissue_fee' => '150 USD',
            'extraordinary_audit_1_fee' => '850 USD',
            'extraordinary_audit_2_fee' => '925 USD',
            'vat_invoice_terms' => $this->officialCommercialText('vat_invoice_terms'),
            'stage1_activity' => $this->officialCommercialText('stage1_activity'),
            'stage2_activity' => $this->officialCommercialText('stage2_activity'),
            'certificate_issuance' => $this->officialCommercialText('certificate_issuance'),
            'surveillance_activity' => $this->officialCommercialText('surveillance_activity'),
            'audit_time_reference' => 'Audit time is calculated from the application review considering selected standard(s), effective personnel, HACCP plans/processes where applicable, sites, shifts, risk and applicable IAF/ISO rules.',
            'important_note' => $this->officialCommercialText('important_note'),
            'contact_line' => 'QSI_CERT TEAM +966569009021 info@qsi-cert.com',
        ];

        if (($stored['total_audit_days'] ?? '') === '' && ($stored['days_allotted'] ?? '') !== '') {
            $stored['total_audit_days'] = $stored['days_allotted'];
        }

        return $this->commercialPayloadWithControlledText($this->mergeNonEmpty($defaults, $stored));
    }

    private function discardPartialDurationSet(array $payload): array
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

    private function certificationApplicationSections(array $data): array
    {
        $applicationData = $data['certification_application'] ?? [];
        $application = $applicationData['application'] ?? [];
        $reviewer = $applicationData['reviewer'] ?? [];
        $answers = $applicationData['answers_by_section'] ?? [];
        $sections = [[
            'Selected Standards',
            $this->recordTable($applicationData['selected_standards'] ?? [], ['standard_code']),
        ]];

        foreach ($answers as $section => $rows) {
            if ($this->certificationApplicationSectionExcluded((string) $section)) {
                continue;
            }

            $sections[] = [$section, $this->recordTable($rows, ['question_text', 'answer_text'], [
                'question_text' => 'Information Required',
                'answer_text' => 'Response',
            ])];
        }

        $sections[] = ['Declaration', $this->keyValueTable([
            'Submitted By' => $application['declaration_name'] ?? '',
            'Position' => $application['declaration_position'] ?? '',
            'Date' => $application['declaration_date'] ?? '',
        ])];
        $sections[] = ['Certification Body Review', $this->keyValueTable([
            'Review Status' => $application['cb_review_status'] ?? 'Not reviewed',
            'Reviewed By' => $reviewer['full_name'] ?? '',
            'Designation' => $reviewer['designation'] ?? 'Technical Manager',
            'Review Notes' => $application['cb_review_notes'] ?? '',
            'Reviewed At' => $this->dateOnly($application['reviewed_at'] ?? ''),
        ])];

        return $sections;
    }

    private function certificationApplicationHtml(string $title, array $client, array $data): string
    {
        $applicationData = $data['certification_application'] ?? [];
        $application = $applicationData['application'] ?? [];
        $sections = $this->certificationApplicationSections($data);
        $body = '';

        foreach ($sections as [$heading, $content]) {
            $body .= '<h2>' . esc($heading) . '</h2>' . $content;
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->certificationApplicationCss() . '</style></head><body>'
            . '<header class="f25-header">'
            . '<table>'
            . '<tr><td class="f25-logo" rowspan="3">' . $this->logoHtml('pdf-logo') . '</td><td class="f25-title" rowspan="3">' . esc($title) . '</td><td>Document Number</td><td>' . esc($application['document_number'] ?? 'F 25') . '</td></tr>'
            . '<tr><td>Revision</td><td>' . esc($application['revision_number'] ?? '1') . '</td></tr>'
            . '<tr><td>Issue / Issue Date</td><td>' . esc(($application['issue_number'] ?? '2') . ' / ' . ($application['issue_date'] ?? '')) . '</td></tr>'
            . '</table>'
            . '</header>'
            . '<section class="client"><strong>Client:</strong> ' . esc($client['company']) . '<br><strong>Scope:</strong> ' . esc((string) ($client['scope'] ?? '')) . '<br><strong>Application:</strong> ' . esc($application['application_number'] ?? 'Not created') . ' | <strong>Status:</strong> ' . esc($application['status'] ?? 'draft') . ' | <strong>Submitted:</strong> ' . esc($this->dateOnly($application['submitted_at'] ?? '')) . '</section>'
            . $body
            . '<footer class="f25-page-footer"><span class="page-number">Page </span></footer>'
            . '</body></html>';
    }

    private function dateOnly(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 10);
    }

    private function excludedCertificationApplicationSections(): array
    {
        return [
            'Supporting Documents',
            'Declaration',
            'HACCP Specific Questions',
        ];
    }

    private function certificationApplicationSectionExcluded(string $section): bool
    {
        return in_array($section, $this->excludedCertificationApplicationSections(), true)
            || str_ends_with(strtoupper(trim($section)), 'SPECIFIC QUESTIONS');
    }

    private function applicationReviewPayloadWithDefaults(array $client, array $standards, array $review): array
    {
        $payload = json_decode((string) ($review['review_payload'] ?? ''), true) ?: [];
        $defaults = $this->applicationDefaults->reviewDefaults($client, $standards);

        foreach ($defaults as $key => $value) {
            if (trim((string) ($payload[$key] ?? '')) === '' && trim((string) $value) !== '') {
                $payload[$key] = $value;
            }
        }
        $applicationHaccpPlans = $this->latestApplicationAnswerByKey((int) ($review['client_id'] ?? $client['id'] ?? 0), 'haccp_plans_processes');
        if ($applicationHaccpPlans !== null) {
            $payload['haccp_plans_processes'] = $applicationHaccpPlans;
        }

        return $payload;
    }

    private function latestApplicationAnswerByKey(int $clientId, string $questionKey): ?string
    {
        if ($clientId <= 0 || ! $this->db->tableExists('certification_applications')) {
            return null;
        }

        $row = $this->db->table('certification_applications')
            ->select('application_answers.answer_text')
            ->join('application_questions', 'application_questions.application_id = certification_applications.id')
            ->join('application_answers', 'application_answers.application_question_id = application_questions.id', 'left')
            ->where('certification_applications.client_id', $clientId)
            ->where('application_questions.question_key', $questionKey)
            ->orderBy('certification_applications.id', 'DESC')
            ->get(1)
            ->getRowArray();

        $answer = trim((string) ($row['answer_text'] ?? ''));

        return $answer === '' ? null : $answer;
    }

    private function applicationReviewSections(array $client, array $data): array
    {
        $review = $data['application_review'] ?? [];
        $payload = $this->applicationReviewPayloadWithDefaults($client, $data['standards'] ?? [], $review);
        $duration = (new AuditDurationService())->calculateApplicationReview($client, $data['standards'] ?? [], $payload);
        $standards = implode(', ', array_keys($duration['standard_days'] ?? []));
        $v = static function (string $key, string $fallback = '') use ($payload, $review): string {
            if (array_key_exists($key, $payload) && (string) $payload[$key] !== '') {
                return (string) $payload[$key];
            }

            return (string) ($review[$key] ?? $fallback);
        };

        $durationRow = [[
            'standard' => $standards ?: 'Standard',
            'days_allotted' => number_format((float) $duration['total_days'], 2),
            'stage1_days' => number_format((float) $duration['stage1_days'], 2),
            'stage2_days' => number_format((float) $duration['stage2_days'], 2),
            'surveillance1_days' => number_format((float) ($duration['surveillance1_days'] ?? 1.00), 2),
            'surveillance2_days' => number_format((float) ($duration['surveillance2_days'] ?? 1.00), 2),
            'recertification_days' => number_format((float) ($duration['recertification_days'] ?? $duration['stage2_days']), 2),
        ], [
            'standard' => 'Reduction',
            'days_allotted' => $v('reduction_days_allotted', '0.00'),
            'stage1_days' => $v('reduction_stage1_days', '0.00'),
            'stage2_days' => $v('reduction_stage2_days', '0.00'),
            'surveillance1_days' => $v('reduction_surveillance1_days', '0.00'),
            'surveillance2_days' => $v('reduction_surveillance2_days', '0.00'),
            'recertification_days' => $v('reduction_recertification_days', '0.00'),
        ]];

        return [
            ['Client Detail', $this->keyValueTable([
                'Application ID' => $v('application_id'),
                'Client' => $client['company'] ?? '',
                'Scope' => $client['scope'] ?? '',
                'Communication Language' => $v('communication_language', 'English'),
                'Client Type' => $v('client_type', 'New Client'),
                'Complexity of Client Management System' => $v('management_system_complexity'),
                'Effective Number of Employees' => $v('effective_employees', (string) ($duration['employee_count'] ?? '')),
                'Number of HACCP Plans / Processes' => $v('haccp_plans_processes'),
                'Shifts Auditing' => $v('shifts_auditing'),
                'Any Seasonal Activity' => $v('seasonal_activity'),
                'Applicable Legal and Regulatory Requirement' => $v('legal_requirements'),
                'Risks associated with products, processes or activities' => $v('product_process_risks'),
                'Risk Classification' => $v('risk_classification', (string) ($review['risk_rating'] ?? '')),
            ])],
            ['Description of Client Scope Activities', $this->keyValueTable([
                'Technical issues arising from the scope' => $v('technical_issues'),
                'Safety condition requirements' => $v('safety_requirements'),
                'Technological and regulatory context' => $v('technological_regulatory_context'),
            ])],
            ['Checklist to Define Client Scope', $this->keyValueTable([
                'Design or development undertaken' => $v('design_development', 'No'),
                'Installation, commissioning or onsite activities' => $v('installation_commissioning', 'No'),
                'Disclaiming parts of the standard' => $v('standard_exclusions', 'No'),
                'Outsourced activity details' => $v('outsourced_activity_details', 'No'),
            ])],
            ['Recertification', $this->keyValueTable([
                'Incident' => $v('incident', 'None'),
                'Change in scope' => $v('scope_change', 'No'),
                'Change in effective employees' => $v('employee_change', 'No'),
            ])],
            ['Multiple or Temporary Sites', $this->keyValueTable([
                'Common management system on multiple or temporary sites' => $v('common_management_system', 'No'),
            ])],
            ['Employees and Accounts', $this->keyValueTable([
                'Effective employees justification' => $v('employee_justification', 'None'),
                'Invoice date and amount established' => $v('invoice_established', 'No'),
            ])],
            ['Audit Scheme', $this->keyValueTable([
                'Standards' => $standards,
                'Accreditation Body' => $v('accreditation_body', 'QSI-Cert'),
                'Initial Audit Type' => $v('initial_audit_type', 'Initial Certification'),
                'Audit Category' => $v('audit_category'),
            ])],
            ['Competence Requirement', $this->keyValueTable([
                'Competence Requirements for Standard' => $v('competence_requirements', 'Competence requirement meet.'),
            ])],
            ['Audit Man Days Calculation',
                $this->recordTable($durationRow, ['standard', 'days_allotted', 'stage1_days', 'stage2_days', 'surveillance1_days', 'surveillance2_days', 'recertification_days'])
                . $this->keyValueTable([
                    'Reduction Percentage' => number_format((float) ($duration['reduction_percent'] ?? 0.00), 2),
                ])
            ],
            ['Calculation Basis', $this->keyValueTable([
                'Formula and reference' => $duration['basis'] ?? '',
            ])],
            ['Reduction', $this->keyValueTable([
                'No design' => $v('no_design', 'None'),
                'Single activity process' => $v('single_activity_process', 'None'),
                'Prior knowledge of organization' => $v('prior_knowledge', 'None'),
                'Shift work' => $v('shift_work', 'None'),
                'Maturity of system' => $v('maturity_of_system', 'None'),
                'Very small site for no. of employees' => $v('very_small_site', 'None'),
                'Client registered with another 3rd party scheme' => $v('registered_scheme', 'None'),
                'Repetitive work' => $v('repetitive_work', 'None'),
                'Low risk product' => $v('low_risk_product', 'None'),
                'Others' => $v('others_reduction', 'None'),
                'No offsite work' => $v('no_offsite_work', 'None'),
            ])],
            ['Reviewer Comments and Application Status', $this->keyValueTable([
                'Application Status' => $v('application_status', (string) ($review['recommendation'] ?? '')),
                'Reviewer Comments/Remarks' => $v('reviewer_comments', (string) ($review['review_notes'] ?? '')),
                'Technical Reviewer Name & Date' => trim((string) ($review['technical_reviewer_name'] ?? '') . ' ' . (string) ($review['technical_review_date'] ?? '')),
            ])],
            ['Quality Manager Comments and Application Status', $this->keyValueTable([
                'Application Approval Status' => $review['quality_manager_status'] ?? '',
                'Quality Manager Comments/Remarks' => $review['quality_manager_comments'] ?? '',
                'Approved by Quality Manager & Date' => trim((string) ($review['quality_manager_name'] ?? '') . ' ' . (string) ($review['quality_manager_date'] ?? '')),
            ])],
        ];
    }

    private function applicationReviewHtml(string $title, array $client, array $data): string
    {
        $review = $data['application_review'] ?? [];
        $payload = $this->applicationReviewPayloadWithDefaults($client, $data['standards'] ?? [], $review);
        $applicationData = $data['certification_application'] ?? [];
        $application = $applicationData['application'] ?? [];
        $duration = (new AuditDurationService())->calculateApplicationReview($client, $data['standards'] ?? [], $payload);
        $payload['days_allotted'] = number_format((float) $duration['total_days'], 2, '.', '');
        $payload['stage1_days'] = number_format((float) $duration['stage1_days'], 2, '.', '');
        $payload['stage2_days'] = number_format((float) $duration['stage2_days'], 2, '.', '');
        $payload['surveillance1_days'] = number_format((float) ($duration['surveillance1_days'] ?? 1.00), 2, '.', '');
        $payload['surveillance2_days'] = number_format((float) ($duration['surveillance2_days'] ?? 1.00), 2, '.', '');
        $payload['recertification_days'] = number_format((float) ($duration['recertification_days'] ?? $duration['stage2_days']), 2, '.', '');
        $payload['reduction_percentage'] = number_format((float) ($duration['reduction_percent'] ?? 0.00), 2, '.', '');
        $payload['calculation_basis'] = (string) ($duration['basis'] ?? ($payload['calculation_basis'] ?? ''));
        $standards = implode(', ', array_keys($duration['standard_days'] ?? []));
        $v = static function (string $key, string $fallback = '') use ($payload, $review): string {
            if (array_key_exists($key, $payload) && (string) $payload[$key] !== '') {
                return (string) $payload[$key];
            }

            return (string) ($review[$key] ?? $fallback);
        };
        $stage1 = $v('stage1_days', (string) ($review['stage1_days'] ?? '1.00'));
        $stage2 = $v('stage2_days', (string) ($review['stage2_days'] ?? '2.00'));

        $body = '<div class="f28-header"><table><tr>'
            . '<td class="f28-logo" rowspan="4">' . $this->logoHtml('pdf-logo') . '</td>'
            . '<td class="f28-title" rowspan="4">APPLICATION REVIEW CHECKLIST<br>REPORT</td>'
            . '<td>Document No.</td><td>' . esc($review['document_number'] ?? 'F 28') . '</td></tr>'
            . '<tr><td>Revision No.</td><td>' . esc($review['revision_number'] ?? '4') . '</td></tr>'
            . '<tr><td>Issue No.</td><td>' . esc($review['issue_number'] ?? '2') . '</td></tr>'
            . '<tr><td>Date</td><td>' . esc($review['document_date'] ?? '2025-02-01') . '</td></tr>'
            . '</table></div>';

        $body .= '<h2>1. Client Detail</h2>' . $this->f28Table([
            'Application ID' => $v('application_id', (string) ($application['application_number'] ?? ($review['application_review_number'] ?? ''))),
            'Client' => $client['company'] ?? '',
            'Scope (Site, Organizational Units, Activities & Processes)' => $client['scope'] ?? '',
            'Communication Language' => $v('communication_language', 'English'),
            'Client Type' => $v('client_type', 'New Client'),
            'Complexity of Client Management System' => $v('management_system_complexity'),
            'Effective Number of Employees' => $v('effective_employees'),
            'Number of HACCP Plans / Processes' => $v('haccp_plans_processes'),
            'Shifts Auditing (If Applicable)' => $v('shifts_auditing'),
            'Any Seasonal Activity' => $v('seasonal_activity'),
            'Applicable Legal and Regulatory Requirement' => $v('legal_requirements'),
            'The risks associated with the products, processes, or activities of the organization' => $v('product_process_risks'),
            'Risk Classification' => $v('risk_classification', (string) ($review['risk_rating'] ?? '')),
        ]);

        $body .= '<h2>2. Description of the activities of the client scope</h2>' . $this->f28Table([
            '1a. Analysis of the technical issues arising from the scope' => $v('technical_issues'),
            '1b. Safety Condition Requirements' => $v('safety_requirements'),
            '1c. Technological and Regulatory Context' => $v('technological_regulatory_context'),
        ]);

        $body .= '<h2>3. Checklist to Define Client Scope</h2>' . $this->f28Table([
            '2a. Any design or development undertaken' => $v('design_development', 'No'),
            '2b. Any installation, commissioning or onsite activities' => $v('installation_commissioning', 'No'),
            '2c. Is the company disclaiming any parts of the standard?' => $v('standard_exclusions', 'No'),
            '2d. Outsourced Activity Details' => $v('outsourced_activity_details', 'No'),
        ]);

        $body .= '<h2>4. Recertification</h2>' . $this->f28Table([
            '3d. Incident' => $v('incident', 'None'),
            '3e. Change in the scope' => $v('scope_change', 'No'),
            '3f. Change in number of effective employees' => $v('employee_change', 'No'),
        ]);

        $body .= '<h2>5. Multiple or Temporary Sites</h2>' . $this->f28Table([
            'Confirm all sites have a common management system on multiple or temporary sites' => $v('common_management_system', 'No'),
        ]);

        $body .= '<h2>6. Effective No. of Employees (Justification)</h2>' . $this->f28Table(['Justification' => $v('employee_justification', 'None')]);
        $body .= '<h2>7. Accounts</h2>' . $this->f28Table(['Invoice date and amount established' => $v('invoice_established', 'No')]);
        $body .= '<h2>8. Audit Scheme</h2>' . $this->f28Table([
            'Standards' => $standards,
            'Accreditation Body' => $v('accreditation_body', 'QSI-Cert'),
            'Initial Audit Type' => $v('initial_audit_type', 'Initial Certification'),
            'Audit Category' => $v('audit_category'),
        ]);
        $body .= '<h2>9. Competence Requirement</h2>' . $this->f28Table([
            'Competence Requirements for Standard' => $v('competence_requirements', 'Competence requirement meet.'),
        ]);

        $body .= '<h2>10. Audit Man Days Calculation</h2>'
            . '<table class="f28-man-days"><thead><tr><th>Standard</th><th>No. of Days Allotted</th><th>Stage 1 (Document Review)</th><th>Stage 2 (On-site Implementation)</th><th>S1</th><th>S2</th><th>Recertification</th></tr></thead><tbody>'
            . '<tr><td>' . esc($standards ?: 'Standard') . '</td><td>' . esc($v('days_allotted', (string) ($review['md5_duration_days'] ?? '3.00'))) . '</td><td>' . esc($stage1) . '</td><td>' . esc($stage2) . '</td><td>' . esc($v('surveillance1_days', '2.00')) . '</td><td>' . esc($v('surveillance2_days', '2.00')) . '</td><td>' . esc($v('recertification_days', '2.00')) . '</td></tr>'
            . '<tr><th>Total Audit Days</th><th>' . esc($v('days_allotted', (string) ($review['md5_duration_days'] ?? '3.00'))) . '</th><th>' . esc($stage1) . '</th><th>' . esc($stage2) . '</th><th>' . esc($v('surveillance1_days', '2.00')) . '</th><th>' . esc($v('surveillance2_days', '2.00')) . '</th><th>' . esc($v('recertification_days', '2.00')) . '</th></tr>'
            . '<tr><td>Reduction</td><td>' . esc($v('reduction_days_allotted', '0.00')) . '</td><td>' . esc($v('reduction_stage1_days', '0.00')) . '</td><td>' . esc($v('reduction_stage2_days', '0.00')) . '</td><td>' . esc($v('reduction_surveillance1_days', '0.00')) . '</td><td>' . esc($v('reduction_surveillance2_days', '0.00')) . '</td><td>' . esc($v('reduction_recertification_days', '0.00')) . '</td></tr>'
            . '<tr><td>Reduction Percentage</td><td colspan="6">' . esc($v('reduction_percentage', (string) ($review['integrated_reduction_percent'] ?? '0.00'))) . '</td></tr>'
            . '<tr><td>Calculation Basis</td><td colspan="6">' . nl2br(esc($v('calculation_basis'))) . '</td></tr>'
            . '</tbody></table>';

        $body .= '<h2>11. Reduction</h2>' . $this->f28Table([
            'No design' => $v('no_design', 'None'),
            'Single activity process' => $v('single_activity_process', 'None'),
            'Prior knowledge of organization' => $v('prior_knowledge', 'None'),
            'Shift work' => $v('shift_work', 'None'),
            'Maturity of system' => $v('maturity_of_system', 'None'),
            'Very small site for no. of employees' => $v('very_small_site', 'None'),
            'Client registered with another 3rd party scheme' => $v('registered_scheme', 'None'),
            'Repetitive work' => $v('repetitive_work', 'None'),
            'Low risk product' => $v('low_risk_product', 'None'),
            'Others' => $v('others_reduction', 'None'),
            'No offsite work' => $v('no_offsite_work', 'None'),
        ]);

        $body .= '<h2>12. Reviewer Comments and Application Status</h2>' . $this->f28Table([
            'Application Status' => $v('application_status', (string) ($review['recommendation'] ?? '')),
            'Reviewer Comments/Remarks' => $v('reviewer_comments', (string) ($review['review_notes'] ?? '')),
            'Technical Reviewer Name & Date' => trim((string) ($review['technical_reviewer_name'] ?? '') . ' ' . (string) ($review['technical_review_date'] ?? '')),
        ]);
        $body .= '<h2>13. Quality Manager Comments and Application Status</h2>' . $this->f28Table([
            'Application Approval Status' => $review['quality_manager_status'] ?? '',
            'Quality Manager Comments/Remarks' => $review['quality_manager_comments'] ?? '',
            'Approved by Quality Manager & Date' => trim((string) ($review['quality_manager_name'] ?? '') . ' ' . (string) ($review['quality_manager_date'] ?? '')),
        ]);

        $body .= '<p class="f28-note">Note: This is an electronically signed document; it does not require any signature or stamp.</p>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->applicationReviewCss() . '</style></head><body>'
            . $body
            . '<footer class="f28-page-footer"><span class="page-number">Page </span></footer>'
            . '</body></html>';
    }

    private function f28Table(array $rows): string
    {
        $html = '<table class="f28-table"><tbody>';
        foreach ($rows as $label => $value) {
            $html .= '<tr><th>' . esc((string) $label) . '</th><td>' . nl2br(esc((string) $value)) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditorAppointmentHtml(string $title, array $client, array $data): string
    {
        $event = $data['event'] ?? [];
        $program = $data['program'] ?? [];
        $contract = $data['contract'] ?? [];
        $application = $data['certification_application']['application'] ?? [];
        $reviewPayload = json_decode((string) (($data['application_review']['review_payload'] ?? '') ?: ''), true) ?: [];
        $appointments = $data['appointments'] ?? [];
        $team = $this->appointmentTeam($appointments);
        $stageLabel = $this->auditEventLabel((string) ($event['event_type'] ?? ''));
        $appointedBy = $this->appointmentApprovedBy($appointments);
        $approvalStatus = $appointments === [] ? 'Pending' : ($this->appointmentHasConflict($appointments) ? 'Pending review' : 'Approved');
        $auditorStatus = $this->appointmentAuditorStatus($appointments);
        $clientStatus = in_array((string) ($contract['status'] ?? ''), ['signed', 'approved', 'accepted'], true) ? 'Confirmed' : 'Pending';
        $planning = $this->auditPlanningSummary($event, $appointments);
        $control = $this->standardDocumentControl('auditor_appointment', $data + ['client' => $client]);

        $body = '<h2>1. Audit Overview</h2>'
            . '<p>Based on the internal procedures, the Certification Body has found all prerequisites fulfilled for the audit beginning including order confirmation by the client. With respect to these fulfilled conditions, the Certification Body is appointing:</p>'
            . $this->f30Table([
                'Application ID' => $application['application_number'] ?? ($program['program_number'] ?? ''),
                'Lead Auditor/Team Lead' => $team['lead_auditor'],
                'Auditor 1' => $team['auditor_1'],
                'Auditor 2' => $team['auditor_2'],
                'Technical Assessor' => $team['technical_assessor'],
                'Additional Auditor and or Trainee Auditor' => $team['additional_auditor'],
                'Witnesser' => $team['witnesser'],
                'Technical Expert / Subject Matter Expert' => $team['technical_expert'],
            ])
            . '<h3>Audit Details</h3>'
            . $this->auditorAppointmentDetailsTable($event, $stageLabel, $team, $planning)
            . '<h2>2. The proposal applies to the following company and scope of business</h2>'
            . $this->f30Table([
                'Name of Company or Organisation' => $client['company'] ?? '',
                'Address' => $client['address'] ?? '',
                'Scope of Company' => $client['scope'] ?? '',
                'Number of Employees in Certified Area' => $client['employee_count'] ?? '',
                'Special Process' => $reviewPayload['special_processes'] ?? 'None',
                'Number of Days Appointed' => $this->auditPlanningBasisText($planning),
            ])
            . '<h2>3. Audit Team Man-day Allocation</h2>'
            . $this->auditPlanningAllocationTable($planning, 'f30-grid')
            . '<h2>4. Appointment Approval Status and Audit Planner&apos;s Comments/Remarks</h2>'
            . $this->f30Table([
                'Approval Status' => $approvalStatus,
                'Comments/Remarks' => $approvalStatus === 'Approved'
                    ? 'I am approving this appointment and submitting for your reference.'
                    : 'Appointment is pending final confirmation or conflict review.',
                'Approved By & Date:' => trim($appointedBy['name'] . "\n" . $appointedBy['date']),
            ])
            . '<h2>5. APPOINTMENT CONFIRMATION AND NEUTRALITY DECLARATION</h2>'
            . '<p>I agree with the appointment and declare that during the previous 2 years I was not personally involved in consulting, implementation or internal audits for the above-mentioned company, and I have no relationship to the company as an employee, the owner or kinship relationships with management or any other intimate relationships, which might affect my impartiality in the position of the auditor in auditing the company. I also declare that I have no relationship with the consultancy company that participated in the previous 2 years in the advisory activities or conduct of internal audits for this company. I declare that I will respect all confidentiality and impartiality requirements and will keep facts found during the audit assessment confidential.</p>'
            . $this->f30Table([
                "Auditor's Confirmation Status" => $auditorStatus,
                "Auditor's Comments/Remarks" => $this->appointmentAuditorComments($appointments),
                'Confirmed by Auditor & Date:' => $this->appointmentAuditorConfirmation($appointments),
            ])
            . '<h2>6. APPOINTMENT CONFIRMATION AND NEUTRALITY DECLARATION</h2>'
            . '<p>I agree with the appointed auditor and the sent audit plan and also declare that the appointed auditor did not participate in consultancy activities or conduct internal audits in our company during the previous 2 years.</p>'
            . $this->f30Table([
                "Client's Confirmation Status" => $clientStatus,
                "Client's Comments/Remarks" => $clientStatus === 'Confirmed' ? 'I agree with the appointed auditor.' : 'Client confirmation is pending.',
                'Confirmed by Client & Date:' => trim((string) ($contract['client_signatory_name'] ?? $client['contact_person'] ?? '') . "\n" . (string) ($contract['client_signatory_date'] ?? $contract['signed_at'] ?? '')),
            ])
            . '<h2>7. Opening Meeting Requirements</h2>'
            . $this->auditorAppointmentRequirementsTable($this->openingMeetingRequirements())
            . '<h2>8. Closing Meeting Requirements</h2>'
            . $this->auditorAppointmentRequirementsTable($this->closingMeetingRequirements());

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->auditorAppointmentCss() . '</style></head><body>'
            . '<header class="f30-header"><table><tbody>'
            . '<tr><td class="f30-logo" rowspan="4">' . $this->logoHtml('pdf-logo') . '</td>'
            . '<td class="f30-title" rowspan="4">AUDITOR APPOINTMENT<div>QSI certification document</div></td>'
            . '<td class="f30-control-label">Document No.</td><td class="f30-control-value">' . esc((string) $control['number']) . '</td></tr>'
            . '<tr><td class="f30-control-label">Revision No.</td><td class="f30-control-value">' . esc((string) $control['revision']) . '</td></tr>'
            . '<tr><td class="f30-control-label">Issue No.</td><td class="f30-control-value">' . esc((string) $control['issue']) . '</td></tr>'
            . '<tr><td class="f30-control-label">Date</td><td class="f30-control-value">' . esc((string) $control['date']) . '</td></tr>'
            . '</tbody></table></header>'
            . $body
            . '</body></html>';
    }

    private function f30Table(array $rows): string
    {
        $html = '<table class="f30-table"><tbody>';
        foreach ($rows as $label => $value) {
            $html .= '<tr><th>' . esc((string) $label) . '</th><td>' . nl2br(esc((string) $value)) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditorAppointmentDetailsTable(array $event, string $stageLabel, array $team, array $planning): string
    {
        $teamText = "Lead Auditor: {$team['lead_auditor']}\nAuditor 1: {$team['auditor_1']}\nAuditor 2: {$team['auditor_2']}\nTechnical Assessor: {$team['technical_assessor']}\nAdditional Auditor and or Trainee Auditor: {$team['additional_auditor']}\nWitnesser: {$team['witnesser']}\nTechnical Expert / SME: {$team['technical_expert']}";

        return '<table class="f30-grid"><thead><tr><th>Sr #</th><th>Audit Team</th><th>Audit Level</th><th>Man-days</th><th>Plan Start</th><th>Plan End</th></tr></thead><tbody><tr>'
            . '<td>1</td><td>' . nl2br(esc($teamText)) . '</td><td>' . esc($stageLabel) . '</td><td>' . esc(number_format((float) $planning['total_man_days'], 2)) . '</td><td>' . esc((string) ($planning['start_date'] ?: ($event['planned_start_date'] ?? ''))) . '</td><td>' . esc((string) ($planning['end_date'] ?: ($event['planned_end_date'] ?? ''))) . '</td>'
            . '</tr></tbody></table>';
    }

    private function appointmentTeam(array $appointments): array
    {
        $team = [
            'lead_auditor' => 'None',
            'auditor_1' => 'None',
            'auditor_2' => 'None',
            'technical_assessor' => 'None',
            'additional_auditor' => 'None',
            'witnesser' => 'None',
            'technical_expert' => 'None',
        ];
        $auditors = [];
        $technicalExperts = [];
        $observers = [];

        foreach ($appointments as $appointment) {
            $name = trim((string) ($appointment['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $role = (string) ($appointment['appointment_role'] ?? '');
            if ($role === 'lead_auditor') {
                $team['lead_auditor'] = $name;
            } elseif ($role === 'auditor') {
                $auditors[] = $name;
            } elseif ($role === 'technical_expert') {
                $technicalExperts[] = $name;
            } elseif ($role === 'observer') {
                $observers[] = $name;
            }
        }

        $team['auditor_1'] = $auditors[0] ?? ($team['lead_auditor'] !== 'None' ? $team['lead_auditor'] : 'None');
        $team['auditor_2'] = $auditors[1] ?? 'None';
        $team['technical_assessor'] = $technicalExperts[0] ?? 'None';
        $team['technical_expert'] = implode(', ', $technicalExperts) ?: 'None';
        $team['witnesser'] = implode(', ', $observers) ?: 'None';

        return $team;
    }

    private function appointmentApprovedBy(array $appointments): array
    {
        foreach ($appointments as $appointment) {
            $name = trim((string) ($appointment['appointed_by_name'] ?? ''));
            if ($name !== '') {
                return ['name' => $name, 'date' => substr((string) ($appointment['appointed_at'] ?? ''), 0, 10)];
            }
        }

        return ['name' => '', 'date' => ''];
    }

    private function appointmentHasConflict(array $appointments): bool
    {
        foreach ($appointments as $appointment) {
            $check = json_decode((string) ($appointment['conflict_check_json'] ?? '{}'), true) ?: [];
            if (! empty($check['conflict_of_interest'])) {
                return true;
            }
        }

        return false;
    }

    private function appointmentAuditorStatus(array $appointments): string
    {
        if ($appointments === []) {
            return 'Pending';
        }

        foreach ($appointments as $appointment) {
            if ((string) ($appointment['status'] ?? '') === 'declined') {
                return 'Declined';
            }
        }

        return 'Confirmed';
    }

    private function appointmentAuditorComments(array $appointments): string
    {
        $notes = [];
        foreach ($appointments as $appointment) {
            $check = json_decode((string) ($appointment['conflict_check_json'] ?? '{}'), true) ?: [];
            $note = trim((string) ($check['notes'] ?? ''));
            if ($note !== '') {
                $notes[] = $note;
            }
        }

        return implode("\n", array_unique($notes)) ?: 'I agree with the appointment, please proceed.';
    }

    private function appointmentAuditorConfirmation(array $appointments): string
    {
        $lines = [];
        foreach ($appointments as $appointment) {
            $name = trim((string) ($appointment['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $lines[] = $name . ' - ' . substr((string) ($appointment['appointed_at'] ?? ''), 0, 10);
        }

        return implode("\n", $lines);
    }

    private function auditorAppointmentRequirementsTable(array $rows): string
    {
        $html = '<table class="f30-grid"><thead><tr><th>No.</th><th>Controlled Element</th><th>Audit Requirement</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . esc((string) $row[0]) . '</td><td>' . esc((string) $row[1]) . '</td><td>' . esc((string) $row[2]) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function openingMeetingRequirements(): array
    {
        return [
            [1, 'Neutrality of auditors', 'Regarding business and other interests and past or present relationships with the organization'],
            [2, 'Introduction of participants', 'Including an outline of their roles'],
            [3, 'Scope of certification', 'Confirmation of the scope of certification'],
            [4, 'Audit plan confirmation', 'Confirmation of the audit plan, including type, scope, objectives, criteria, changes, and other arrangements with the client'],
            [5, 'Communication channels', 'Confirmation of formal communication channels between the audit team and the client'],
            [6, 'Resources availability', 'Confirmation that the resources and facilities needed by the audit team are available'],
            [7, 'Confidentiality', 'Confirmation of matters relating to confidentiality'],
            [8, 'Work safety, emergency, and security procedures', 'Confirmation of relevant procedures for the audit team'],
            [9, 'Guides and observers', 'Confirmation of the availability, roles, and identities of any guides and observers'],
            [10, 'Additional documents', 'Requesting other documents for the specific period of the audit and identification of relevant regulations and standards'],
            [11, 'Safety risks analysis', 'Analyzing possible safety risks based on processes and activities of the subject and introducing safety procedures'],
            [12, 'Reporting method', 'Confirmation of the method of reporting, including any grading of audit findings'],
            [13, 'Audit termination', 'Information about the conditions under which the audit may be prematurely terminated'],
            [14, 'Audit responsibility', 'Confirmation that the audit team leader is responsible for the audit and execution of the audit plan'],
            [15, 'Previous audit findings', 'Confirmation of findings from the previous review or audit and verification of the conditions'],
            [16, 'Audit methods', 'Methods and procedures to be used for conducting the audit based on sampling'],
            [17, 'Audit language', 'Confirmation of the language to be used during the audit'],
            [18, 'Client communication', 'Confirmation that the client will be kept informed of audit progress and concerns during the audit'],
            [19, 'Certification outcomes', 'Expected outcomes from certification'],
            [20, 'Client questions', 'Opportunity for the client to ask questions'],
        ];
    }

    private function closingMeetingRequirements(): array
    {
        return [
            [21, 'Audit evidence', 'Advising the client that the audit evidence obtained was based on a sample of information, introducing uncertainty'],
            [22, 'Reporting method and timeframe', 'Confirmation of the method and timeframe of reporting, including grading of audit findings'],
            [23, 'Handling nonconformities', "The certification body's process for handling nonconformities and consequences relating to the client's certification status"],
            [24, 'Correction plan', 'The timeframe for the client to present a plan for correction and corrective actions for nonconformities identified'],
            [25, 'Post-audit activities', "The certification body's post-audit activities"],
            [26, 'Complaint and appeal processes', 'Information about the complaint and appeal handling processes'],
            [27, 'Final decisions', 'Final decisions based on the appropriateness of accredited certification for defined scope and expected outcomes'],
        ];
    }

    private function auditEventLabel(string $eventType): string
    {
        return match ($eventType) {
            'initial_stage1' => 'Stage 1',
            'initial_stage2' => 'Stage 2',
            'surveillance1' => 'Surveillance 1',
            'surveillance2' => 'Surveillance 2',
            'recertification' => 'Recertification',
            default => ucwords(str_replace('_', ' ', $eventType)),
        };
    }

    private function contractHtml(string $title, array $client, array $data): string
    {
        $contract = $data['contract'] ?? [];
        $proposal = $data['proposal'] ?? [];
        $sections = $this->contractSections($data);
        $documentNumber = $contract['document_number'] ?? 'F 27';
        $revisionNumber = $contract['revision_number'] ?? '2';
        $issueNumber = $contract['issue_number'] ?? '2';
        $documentDate = $contract['document_date'] ?? '2022-05-15';

        $body = '';
        foreach ($sections as [$heading, $content]) {
            $body .= '<h2>' . esc($heading) . '</h2>' . $content;
        }

        $payload = $this->contractPayloadForDocument($data);
        $cover = $this->commercialCoverHtml(
            'Certification Contract',
            $client,
            $payload,
            [
                'Contract Number' => (string) ($contract['contract_number'] ?? 'Not created'),
                'Proposal Number' => (string) ($proposal['proposal_number'] ?? ''),
                'Document No.' => (string) $documentNumber,
                'Document Date' => (string) $documentDate,
            ]
        );

        $clientBlock = '<section class="client"><table>'
            . '<tr><th>Client</th><td>' . esc((string) ($client['company'] ?? '')) . '</td></tr>'
            . '<tr><th>Scope</th><td>' . nl2br(esc((string) ($client['scope'] ?? ''))) . '</td></tr>'
            . '<tr><th>Contract Number</th><td>' . esc((string) ($contract['contract_number'] ?? 'Not created')) . '</td></tr>'
            . '<tr><th>Proposal Number</th><td>' . esc((string) ($proposal['proposal_number'] ?? '')) . '</td></tr>'
            . '<tr><th>Status</th><td>' . esc((string) ($contract['status'] ?? '')) . '</td></tr>'
            . '</table></section>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->contractCss() . $this->commercialDocumentCss() . '</style></head><body>'
            . $cover
            . '<main class="commercial-body">'
            . $this->commercialBodyHeaderHtml('Certification Contract', [
                'Document No.' => (string) $documentNumber,
                'Revision No.' => (string) $revisionNumber,
                'Issue No.' => (string) $issueNumber,
                'Date' => (string) $documentDate,
            ])
            . $clientBlock
            . $body
            . '</main>'
            . '</body></html>';
    }

    private function contractPayloadForDocument(array $data): array
    {
        $contract = $data['contract'] ?? [];
        $proposal = $data['proposal'] ?? [];
        $client = $data['client'] ?? [];
        $review = $data['application_review'] ?? [];
        $reviewPayload = json_decode((string) (($review['review_payload'] ?? '') ?: ''), true) ?: [];
        $duration = (new AuditDurationService())->calculateApplicationReview($client, $data['standards'] ?? [], $reviewPayload);
        $proposalPayload = $this->discardPartialDurationSet(json_decode((string) ($proposal['proposal_payload'] ?? ''), true) ?: []);
        $contractPayload = $this->discardPartialDurationSet(json_decode((string) ($contract['contract_payload'] ?? ''), true) ?: []);

        $defaults = [
            'legal_documentation' => '-',
            'management_representative' => $client['contact_person'] ?? '',
            'phone_fax' => $client['phone'] ?? '',
            'number_of_locations' => (string) ($client['number_of_sites'] ?? 1),
            'standards_text' => implode(', ', array_keys($duration['standard_days'] ?? [])),
            'accreditation_body' => $reviewPayload['accreditation_body'] ?? 'QSI-Cert',
            'initial_audit_type' => $reviewPayload['initial_audit_type'] ?? 'Initial Certification',
            'total_audit_days' => number_format((float) ($reviewPayload['days_allotted'] ?? $review['md5_duration_days'] ?? $duration['total_days']), 2),
            'stage1_days' => number_format((float) ($reviewPayload['stage1_days'] ?? $review['stage1_days'] ?? $duration['stage1_days']), 2),
            'stage2_days' => number_format((float) ($reviewPayload['stage2_days'] ?? $review['stage2_days'] ?? $duration['stage2_days']), 2),
            'surveillance1_days' => number_format((float) ($reviewPayload['surveillance1_days'] ?? $duration['surveillance1_days'] ?? 1.00), 2),
            'surveillance2_days' => number_format((float) ($reviewPayload['surveillance2_days'] ?? $duration['surveillance2_days'] ?? 1.00), 2),
            'recertification_days' => number_format((float) ($reviewPayload['recertification_days'] ?? $duration['recertification_days'] ?? $duration['stage2_days']), 2),
            'certification_process_obligations' => $this->officialCommercialText('certification_process_obligations'),
            'payment_terms' => "Certification Audit Fee:\n50% payable upon signing the contract.\n50% payable after receiving the draft copy of the certificate.\n\nSurveillance Audit Fee:\n100% payable one month in advance of the scheduled audit.",
            'certification_audit_includes' => "Audit planning and preparation.\nReview of management system documentation.\nStage 1 and Stage 2 audit execution.\nAudit reporting, technical review and certification decision.\nIssuance of the certificate after approval.",
            'surveillance_audit_includes' => "Audit planning and preparation.\nReview of changes since previous audit.\nSurveillance audit execution and reporting.\nFollow-up of previous findings and certification conditions.",
            'additional_a4_copy_fee' => '50 USD',
            'certificate_reissue_fee' => '150 USD',
            'extraordinary_audit_1_fee' => '850 USD',
            'extraordinary_audit_2_fee' => '925 USD',
            'vat_invoice_terms' => $this->officialCommercialText('vat_invoice_terms'),
            'stage1_activity' => $this->officialCommercialText('stage1_activity'),
            'stage2_activity' => $this->officialCommercialText('stage2_activity'),
            'certificate_issuance' => $this->officialCommercialText('certificate_issuance'),
            'surveillance_activity' => $this->officialCommercialText('surveillance_activity'),
            'audit_time_reference' => 'Audit time is calculated from the application review considering selected standard(s), effective personnel, HACCP plans/processes where applicable, sites, shifts, risk and applicable IAF/ISO rules.',
            'important_note' => $this->officialCommercialText('important_note'),
            'contact_line' => 'QSI_CERT TEAM +966569009021 info@qsi-cert.com',
        ];

        if (($proposalPayload['total_audit_days'] ?? '') === '' && ($proposalPayload['days_allotted'] ?? '') !== '') {
            $proposalPayload['total_audit_days'] = $proposalPayload['days_allotted'];
        }

        if (($contractPayload['total_audit_days'] ?? '') === '' && ($contractPayload['days_allotted'] ?? '') !== '') {
            $contractPayload['total_audit_days'] = $contractPayload['days_allotted'];
        }

        return $this->commercialPayloadWithControlledText($this->mergeNonEmpty($this->mergeNonEmpty($defaults, $proposalPayload), $contractPayload));
    }

    private function commercialPayloadWithControlledText(array $payload): array
    {
        return $this->commercialTerms->applyControlledText($payload);
    }

    private function shouldUseOfficialCommercialText(string $key, string $value): bool
    {
        return $this->commercialTerms->shouldUseOfficialText($key, $value);
    }

    private function officialCommercialText(string $key): string
    {
        return $this->commercialTerms->text($key);
    }

    private function commercialAcceptanceTable(string $qsiName, string $qsiDate, string $clientName, string $clientDate, bool $confirmed): string
    {
        $clientDate = $clientDate !== '' ? $clientDate : 'Pending';
        $stamp = $confirmed ? '<div class="commercial-stamp">CONFIRMED</div>' : '<div class="commercial-stamp pending">PENDING</div>';

        return '<table class="commercial-acceptance"><thead><tr><th>On Behalf of QSI-Cert</th><th>On Behalf of Client</th></tr></thead><tbody><tr>'
            . '<td><div class="commercial-stamp-wrap">' . $this->stampHtml('commercial-stamp-image') . '</div><div class="commercial-name">' . esc($qsiName) . '</div><div class="commercial-date">' . esc($qsiDate) . '</div></td>'
            . '<td>' . $stamp . '<div class="commercial-name">' . esc($clientName) . '</div><div class="commercial-date">' . esc($clientDate) . '</div></td>'
            . '</tr></tbody></table>';
    }

    private function commercialImportantNoteHtml(array $payload): string
    {
        $note = (string) ($payload['important_note'] ?? $this->officialCommercialText('important_note'));
        $paragraphs = preg_split('/\R{2,}/', trim($note)) ?: [];
        $html = '<div class="commercial-note">';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $lines = preg_split('/\R/', $paragraph) ?: [];
            $bulletLines = array_values(array_filter($lines, static fn (string $line): bool => str_starts_with(trim($line), '- ')));
            if ($bulletLines !== []) {
                $introLines = array_values(array_filter($lines, static fn (string $line): bool => ! str_starts_with(trim($line), '- ')));
                if ($introLines !== []) {
                    $html .= '<p>' . nl2br(esc(implode("\n", $introLines))) . '</p>';
                }
                $html .= '<ul>';
                foreach ($bulletLines as $line) {
                    $item = trim($line);
                    $html .= '<li><span class="annexure-link">' . esc(substr($item, 2)) . '</span></li>';
                }
                $html .= '</ul>';
            } else {
                $html .= '<p>' . nl2br(esc($paragraph)) . '</p>';
            }
        }

        return $html . '</div>' . $this->commercialContactTable();
    }

    private function commercialContactTable(): string
    {
        return '<table class="commercial-contact"><tbody><tr>'
            . '<td>QSI_CERT TEAM</td>'
            . '<td>+966569009021</td>'
            . '<td><span class="annexure-link">info@qsi-cert.com</span></td>'
            . '</tr></tbody></table>';
    }

    private function contractSections(array $data): array
    {
        $contract = $data['contract'] ?? [];
        $proposal = $data['proposal'] ?? [];
        $client = $data['client'] ?? [];
        $payload = $this->contractPayloadForDocument($data);

        return [
            ['Client Detail', $this->keyValueTable([
                'Proposal Date' => $proposal['proposal_date'] ?? substr((string) ($proposal['created_at'] ?? ''), 0, 10),
                'Client Reference' => $proposal['client_reference'] ?? '',
                'Company / Organisation' => $client['company'] ?? '',
                'Address' => $client['address'] ?? '',
                'Legal Documentation' => $payload['legal_documentation'] ?? '',
                'Management Representative' => $payload['management_representative'] ?? '',
                'Email' => $client['email'] ?? '',
                'Phone / Fax' => $payload['phone_fax'] ?? '',
                'Scope of Certification' => $client['scope'] ?? '',
                'Number of Employees' => $client['employee_count'] ?? '',
                'Number of Locations / Sites' => $payload['number_of_locations'] ?? '',
                'VAT %' => $proposal['vat_percent'] ?? '',
            ])],
            ['Audit Scheme', $this->keyValueTable([
                'Standard' => $payload['standards_text'] ?? '',
                'Accreditation Body' => $payload['accreditation_body'] ?? '',
                'Initial Audit Type' => $payload['initial_audit_type'] ?? '',
            ]) . $this->recordTable([[
                'total_audit_days' => $payload['total_audit_days'] ?? '',
                'stage1_days' => $payload['stage1_days'] ?? '',
                'stage2_days' => $payload['stage2_days'] ?? '',
                'surveillance1_days' => $payload['surveillance1_days'] ?? '',
                'surveillance2_days' => $payload['surveillance2_days'] ?? '',
                'recertification_days' => $payload['recertification_days'] ?? '',
            ]], ['total_audit_days', 'stage1_days', 'stage2_days', 'surveillance1_days', 'surveillance2_days', 'recertification_days'])],
            ['Certification Process and Obligations', $this->commercialObligationsHtml((string) ($payload['certification_process_obligations'] ?? ''))],
            ['Fees Detail', $this->keyValueTable([
                'Currency' => $proposal['currency'] ?? '',
                'Initial Certification Audit' => $this->money($proposal['certification_fee'] ?? 0),
                'Surveillance Audit 1' => $this->money($proposal['surveillance1_fee'] ?? 0),
                'Surveillance Audit 2' => $this->money($proposal['surveillance2_fee'] ?? 0),
                'Travel Costs' => $this->money($proposal['travel_fee'] ?? 0),
                'Accommodation Costs' => $this->money($proposal['accommodation_fee'] ?? 0),
                'Additional Services' => $this->money($proposal['training_fee'] ?? 0),
                'Discount' => $this->money($proposal['discount_amount'] ?? 0),
                'VAT Amount' => $this->money($proposal['vat_amount'] ?? 0),
                'Total Cost with VAT' => $this->money($proposal['grand_total'] ?? 0),
            ])],
            ['Payment Terms', '<p>' . nl2br(esc((string) ($payload['payment_terms'] ?? ''))) . '</p>'],
            ['Certification Audit Includes', '<p>' . nl2br(esc((string) ($payload['certification_audit_includes'] ?? ''))) . '</p>'],
            ['Surveillance Audit Includes', '<p>' . nl2br(esc((string) ($payload['surveillance_audit_includes'] ?? ''))) . '</p>'],
            ['Cost of Additional Services', $this->keyValueTable([
                'Additional A4 Copy' => $payload['additional_a4_copy_fee'] ?? '',
                'Certificate Reissue' => $payload['certificate_reissue_fee'] ?? '',
                'Extraordinary Audit 1' => $payload['extraordinary_audit_1_fee'] ?? '',
                'Extraordinary Audit 2' => $payload['extraordinary_audit_2_fee'] ?? '',
            ])],
            ['VAT and Invoice Terms', '<p>' . nl2br(esc((string) ($payload['vat_invoice_terms'] ?? ''))) . '</p>'],
            ['Audit Activities', $this->keyValueTable([
                'Stage 1' => $payload['stage1_activity'] ?? '',
                'Stage 2' => $payload['stage2_activity'] ?? '',
                'Issuance of Certificate' => $payload['certificate_issuance'] ?? '',
                'Surveillance Audit' => $payload['surveillance_activity'] ?? '',
                'Audit Time Reference' => $payload['audit_time_reference'] ?? '',
            ])],
            ['Acceptance and Authorization', $this->commercialAcceptanceTable(
                (string) ($contract['qsi_signatory_name'] ?? 'Engr. Mohammad Ahmad'),
                substr((string) ($contract['qsi_signatory_date'] ?? $contract['approved_at'] ?? $contract['contract_date'] ?? date('Y-m-d')), 0, 10),
                (string) ($contract['client_signatory_name'] ?? $client['contact_person'] ?? $client['company'] ?? 'Client Authorized Representative'),
                substr((string) ($contract['client_signatory_date'] ?? $contract['signed_at'] ?? ''), 0, 10),
                in_array((string) ($contract['status'] ?? ''), ['signed', 'approved', 'accepted'], true)
            )],
            ['Important Note', $this->commercialImportantNoteHtml($payload)],
            ['Certification Assessment Note', '<p>Following the audit and based on the auditor&apos;s recommendations, QSI-Cert will conduct the certification assessment. If the QSI assessor confirms that all requirements, accreditation conditions, and contractual terms have been met, the corresponding certificates will be issued.</p>'],
            ['Certification Cycle', $this->eventTable($data['events'])],
        ];
    }

    private function auditPlanSections(array $data): array
    {
        return [
            ['Audit Event', $this->eventTable($data['events'])],
            ['Auditor Appointment', $this->recordTable($data['appointments'] ?? [], ['full_name', 'appointment_role', 'status', 'appointed_at'])],
            ['Audit Timetable', $this->planItemTable($data['plan_items'])],
        ];
    }

    private function auditPlanHtml(string $title, array $client, array $data): string
    {
        $event = $data['event'] ?? [];
        $plan = $data['audit_plan'] ?? [];
        $program = $data['program'] ?? [];
        $stageLabel = $this->auditEventLabel((string) ($event['event_type'] ?? ''));
        $control = $this->standardDocumentControl('audit_plan', $data + ['client' => $client]);
        $documentNumber = $plan['document_number'] ?? $control['number'];
        $revisionNumber = $plan['revision_number'] ?? $control['revision'];
        $issueNumber = $plan['issue_number'] ?? $control['issue'];
        $documentDate = $this->displayDocumentDate((string) ($plan['document_date'] ?? $control['date']));
        $planning = $this->auditPlanningSummary($event, $data['appointments'] ?? []);
        $rows = $this->auditPlanRowsForDocument($data, $planning);

        $body = '<h2>1. Audit Plan Control</h2>'
            . $this->f31Table([
                'Plan Number' => $plan['plan_number'] ?? 'System prepared',
                'Audit Number' => $event['audit_number'] ?? '',
                'Audit Stage' => $stageLabel,
                'Audit Status' => $event['status'] ?? '',
                'Prepared By' => $plan['prepared_by_name'] ?? '',
                'Approved By' => $plan['approved_by_name'] ?? '',
                'Approved At' => $plan['approved_at'] ?? '',
            ])
            . '<h2>2. Client and Audit Information</h2>'
            . $this->f31Table([
                'Client Name' => $client['company'] ?? '',
                'Address' => $client['address'] ?? '',
                'Certification Scope' => $client['scope'] ?? '',
                'Standard(s)' => $this->standardCodesText($data['standards'] ?? []),
                'Audit Stage' => $stageLabel,
                'Audit Duration' => number_format((float) $planning['total_man_days'], 2) . ' man-day(s)',
                'Appointed Audit Team Capacity' => (string) $planning['auditor_count'] . ' auditor(s) x 1.00 man-day per calendar day',
                'Calendar Days Required' => (string) $planning['calendar_days'] . ' day(s)',
                'Planned Start Date' => $event['planned_start_date'] ?? '',
                'Calculated Plan End Date' => $planning['end_date'],
                'Program Number' => $program['program_number'] ?? '',
            ])
            . '<h2>3. Audit Team</h2>'
            . $this->auditPlanTeamTable($data['appointments'] ?? [], $planning)
            . '<h2>4. Audit Team Man-day Allocation</h2>'
            . $this->auditPlanningAllocationTable($planning, 'f31-grid')
            . '<h2>5. Audit Timetable</h2>'
            . '<p class="f31-note">The timetable is linked with the appointed audit man-days. Each appointed auditor may cover maximum 1.00 man-day per calendar day, planned as 09:00 to 17:00 including lunch. Where more than one auditor is appointed, audit activities are divided and may run in parallel.</p>'
            . $this->auditPlanTimetable($rows)
            . '<h2>6. Audit Plan Notes</h2>'
            . $this->f31Table([
                'Audit Method' => 'Interviews, document review, record sampling, site observation and traceability testing as applicable.',
                'Sampling Basis' => 'The audit is based on sampling. Additional trails may be followed where audit evidence requires.',
                'Confidentiality' => 'The audit team shall maintain confidentiality and impartiality throughout the audit.',
                'Man-day Planning Rule' => $this->auditPlanningBasisText($planning),
                'Lunch Break' => '12:30 to 13:30 unless otherwise agreed with the client.',
            ]);

        $header = '<header class="f31-header"><table><tbody>'
            . '<tr><td class="f31-logo" rowspan="4">' . $this->logoHtml('pdf-logo') . '</td>'
            . '<td class="f31-title" rowspan="4">AUDIT PLAN<span>' . esc($stageLabel) . '</span><div>QSI certification document</div></td>'
            . '<td class="f31-control-label">Document No.</td><td class="f31-control-value">' . esc((string) $documentNumber) . '</td></tr>'
            . '<tr><td class="f31-control-label">Revision No.</td><td class="f31-control-value">' . esc((string) $revisionNumber) . '</td></tr>'
            . '<tr><td class="f31-control-label">Issue No.</td><td class="f31-control-value">' . esc((string) $issueNumber) . '</td></tr>'
            . '<tr><td class="f31-control-label">Date</td><td class="f31-control-value">' . esc((string) $documentDate) . '</td></tr>'
            . '</tbody></table></header>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->auditPlanCss() . '</style></head><body>'
            . $header
            . '<section class="client"><strong>Client:</strong> ' . esc((string) ($client['company'] ?? '')) . '<br><strong>Scope:</strong> ' . esc((string) ($client['scope'] ?? '')) . '<br><strong>Audit:</strong> ' . esc($stageLabel) . ' &nbsp; <strong>Audit No.:</strong> ' . esc((string) ($event['audit_number'] ?? '')) . '</section>'
            . $body
            . '</body></html>';
    }

    private function f31Table(array $rows): string
    {
        $html = '<table class="f31-table"><tbody>';
        foreach ($rows as $label => $value) {
            $html .= '<tr><th>' . esc((string) $label) . '</th><td>' . nl2br(esc((string) $value)) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditPlanTeamTable(array $appointments, array $planning): string
    {
        if ($appointments === []) {
            return '<p class="muted">No auditor appointment recorded for this stage.</p>';
        }

        $allocationByName = [];
        foreach ($planning['allocations'] as $allocation) {
            $name = (string) ($allocation['name'] ?? '');
            if ($name !== '') {
                $allocationByName[$name] = ($allocationByName[$name] ?? 0.0) + (float) ($allocation['man_days'] ?? 0.0);
            }
        }

        $html = '<table class="f31-grid"><thead><tr><th>Auditor Type</th><th>Auditor Name</th><th>Allocated Man-days</th><th>Status</th><th>Appointed At</th></tr></thead><tbody>';
        foreach ($appointments as $appointment) {
            $name = (string) ($appointment['full_name'] ?? '');
            $html .= '<tr>'
                . '<td>' . esc(ucwords(str_replace('_', ' ', (string) ($appointment['appointment_role'] ?? '')))) . '</td>'
                . '<td>' . esc($name) . '</td>'
                . '<td>' . esc(number_format((float) ($allocationByName[$name] ?? 0.0), 2)) . '</td>'
                . '<td>' . esc((string) ($appointment['status'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($appointment['appointed_at'] ?? '')) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditPlanTimetable(array $rows): string
    {
        if ($rows === []) {
            return '<p class="muted">No timetable available.</p>';
        }

        $html = '<table class="f31-grid f31-timetable"><thead><tr><th>Date</th><th>Time</th><th>Process / Unit</th><th>Activity</th><th>Clauses / Criteria</th><th>Auditor Type</th><th>Auditor Name</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . esc((string) ($row['audit_date'] ?? '')) . '</td>'
                . '<td>' . esc(substr((string) ($row['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($row['end_time'] ?? ''), 0, 5)) . '</td>'
                . '<td>' . esc((string) ($row['process_unit'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['activity'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['clauses'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['auditor_type'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['auditor_name'] ?? '')) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditPlanningSummary(array $event, array $appointments): array
    {
        $totalManDays = max(1.0, (float) ($event['duration_days'] ?? 1.0));
        $auditors = $this->auditPlanCapacityAuditors($appointments);
        $auditorCount = max(1, count($auditors));
        $calendarDays = max(1, (int) ceil($totalManDays / $auditorCount));
        $start = new \DateTimeImmutable((string) ($event['planned_start_date'] ?? date('Y-m-d')));
        $remaining = $totalManDays;
        $allocations = [];

        for ($day = 0; $day < $calendarDays && $remaining > 0.0; $day++) {
            $date = $start->add(new \DateInterval('P' . $day . 'D'))->format('Y-m-d');
            foreach ($auditors as $auditor) {
                if ($remaining <= 0.0) {
                    break;
                }

                $manDays = min(1.0, $remaining);
                $allocations[] = [
                    'date' => $date,
                    'name' => $auditor['name'],
                    'role' => $auditor['role'],
                    'man_days' => $manDays,
                    'hours' => $manDays * 8.0,
                ];
                $remaining -= $manDays;
            }
        }

        $endDate = $start->add(new \DateInterval('P' . max(0, $calendarDays - 1) . 'D'))->format('Y-m-d');

        return [
            'total_man_days' => $totalManDays,
            'auditor_count' => $auditorCount,
            'calendar_days' => $calendarDays,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $endDate,
            'allocations' => $allocations,
        ];
    }

    private function auditPlanCapacityAuditors(array $appointments): array
    {
        $lead = null;
        $auditors = [];

        foreach ($appointments as $appointment) {
            $name = trim((string) ($appointment['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $role = (string) ($appointment['appointment_role'] ?? '');
            if ($role === 'lead_auditor') {
                $lead = ['role' => 'Lead Auditor', 'name' => $name];
            } elseif ($role === 'auditor') {
                $auditors[] = ['role' => 'Auditor', 'name' => $name];
            }
        }

        $team = [];
        if ($lead !== null) {
            $team[] = $lead;
        }
        foreach ($auditors as $auditor) {
            if (! in_array($auditor['name'], array_column($team, 'name'), true)) {
                $team[] = $auditor;
            }
        }

        if ($team === []) {
            $team[] = ['role' => 'Auditor', 'name' => 'Not assigned'];
        }

        return $team;
    }

    private function auditPlanningBasisText(array $planning): string
    {
        return number_format((float) $planning['total_man_days'], 2) . ' audit man-day(s) planned over '
            . (string) $planning['calendar_days'] . ' calendar day(s) using '
            . (string) $planning['auditor_count'] . ' audit auditor(s). Each auditor covers maximum 1.00 man-day per calendar day, equal to 8 hours including meeting time and lunch break.';
    }

    private function auditPlanningAllocationTable(array $planning, string $className): string
    {
        if (($planning['allocations'] ?? []) === []) {
            return '<p class="muted">No man-day allocation available.</p>';
        }

        $html = '<table class="' . esc($className) . '"><thead><tr><th>Date</th><th>Auditor Type</th><th>Auditor Name</th><th>Allocated Man-days</th><th>Planned Hours</th></tr></thead><tbody>';
        foreach ($planning['allocations'] as $allocation) {
            $html .= '<tr>'
                . '<td>' . esc((string) ($allocation['date'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($allocation['role'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($allocation['name'] ?? '')) . '</td>'
                . '<td>' . esc(number_format((float) ($allocation['man_days'] ?? 0.0), 2)) . '</td>'
                . '<td>' . esc(number_format((float) ($allocation['hours'] ?? 0.0), 2)) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditPlanRowsForDocument(array $data, array $planning): array
    {
        $manualRows = $this->normalizeManualPlanRows($data['plan_items'] ?? [], $data['appointments'] ?? []);
        $requiredDates = (int) ($planning['calendar_days'] ?? 1);
        $requiredAssignments = (int) ceil((float) ($planning['total_man_days'] ?? 1.0));
        $manualDates = array_unique(array_filter(array_map(static fn (array $row): string => (string) ($row['audit_date'] ?? ''), $manualRows)));
        $manualAssignments = [];
        $manualHours = [];
        foreach ($manualRows as $row) {
            $name = trim((string) ($row['auditor_name'] ?? ''));
            $date = trim((string) ($row['audit_date'] ?? ''));
            $activity = strtolower(trim((string) ($row['activity'] ?? '')));
            if ($name !== '' && $date !== '' && ! str_contains($activity, 'lunch') && ! str_contains($activity, 'break')) {
                $key = $date . '|' . $name;
                $manualAssignments[$key] = true;
                $manualHours[$key] = ($manualHours[$key] ?? 0.0) + $this->timeRangeHours((string) ($row['start_time'] ?? ''), (string) ($row['end_time'] ?? ''));
            }
        }
        $manualHasEnoughHours = true;
        foreach ($planning['allocations'] as $allocation) {
            $key = (string) ($allocation['date'] ?? '') . '|' . (string) ($allocation['name'] ?? '');
            $requiredHours = max(1.0, (float) ($allocation['man_days'] ?? 0.0) * 5.5);
            if (($manualHours[$key] ?? 0.0) < $requiredHours) {
                $manualHasEnoughHours = false;
                break;
            }
        }

        if ($manualRows !== [] && count($manualDates) >= $requiredDates && count($manualAssignments) >= $requiredAssignments && $manualHasEnoughHours) {
            return $manualRows;
        }

        return $this->automaticAuditPlanRows($data, $planning);
    }

    private function normalizeManualPlanRows(array $items, array $appointments): array
    {
        $rolesByName = [];
        foreach ($appointments as $appointment) {
            $name = (string) ($appointment['full_name'] ?? '');
            if ($name !== '') {
                $rolesByName[$name] = ucwords(str_replace('_', ' ', (string) ($appointment['appointment_role'] ?? 'Auditor')));
            }
        }

        $rows = [];
        foreach ($items as $item) {
            $auditorName = (string) ($item['auditor_name'] ?? '');
            $rows[] = [
                'audit_date' => (string) ($item['audit_date'] ?? ''),
                'start_time' => (string) ($item['start_time'] ?? ''),
                'end_time' => (string) ($item['end_time'] ?? ''),
                'process_unit' => trim((string) (($item['department'] ?? '') . (($item['process_name'] ?? '') !== '' ? ' - ' . $item['process_name'] : ''))),
                'activity' => (string) ($item['activity_type'] ?? ''),
                'clauses' => (string) ($item['clauses'] ?? ''),
                'auditor_type' => $rolesByName[$auditorName] ?? ucwords(str_replace('_', ' ', (string) ($item['auditor_role'] ?? 'Auditor'))),
                'auditor_name' => $auditorName,
            ];
        }

        return $rows;
    }

    private function automaticAuditPlanRows(array $data, array $planning): array
    {
        $event = $data['event'] ?? [];
        $start = new \DateTimeImmutable((string) ($event['planned_start_date'] ?? date('Y-m-d')));
        $activities = $this->auditPlanActivityPool($data['standards'] ?? [], (string) ($event['event_type'] ?? ''));
        $activityIndex = 0;
        $rows = [];
        $allocationsByDate = [];

        foreach ($planning['allocations'] as $allocation) {
            $allocationsByDate[(string) $allocation['date']][] = $allocation;
        }

        $dateIndex = 0;
        $lastDateIndex = max(0, count($allocationsByDate) - 1);
        foreach ($allocationsByDate as $date => $allocations) {
            $isFirstDay = $dateIndex === 0;
            $isLastDay = $dateIndex === $lastDateIndex;
            $teamNames = implode(', ', array_filter(array_map(static fn (array $allocation): string => (string) ($allocation['name'] ?? ''), $allocations)));
            $lead = $this->leadAllocation($allocations);

            $rows[] = [
                'audit_date' => $date,
                'start_time' => '09:00',
                'end_time' => '09:30',
                'process_unit' => 'Top management / audit team',
                'activity' => $isFirstDay ? 'Opening meeting and audit plan confirmation' : 'Day opening briefing and allocation confirmation',
                'clauses' => 'Audit objectives, scope, criteria and communication',
                'auditor_type' => $teamNames !== '' ? 'Audit Team' : $lead['role'],
                'auditor_name' => $teamNames !== '' ? $teamNames : $lead['name'],
            ];

            $hasFullDay = false;
            foreach ($allocations as $allocation) {
                $slots = $this->auditorWorkSlots((float) ($allocation['man_days'] ?? 0.0));
                $hasFullDay = $hasFullDay || (float) ($allocation['man_days'] ?? 0.0) >= 1.0;

                foreach ($slots as $slot) {
                    $activity = $activities[$activityIndex % count($activities)];
                    $activityIndex++;
                    $rows[] = [
                        'audit_date' => $date,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'process_unit' => $activity['process_unit'],
                        'activity' => $activity['activity'],
                        'clauses' => $activity['clauses'],
                        'auditor_type' => (string) ($allocation['role'] ?? 'Auditor'),
                        'auditor_name' => (string) ($allocation['name'] ?? 'Not assigned'),
                    ];
                }
            }

            if ($hasFullDay) {
                $rows[] = [
                    'audit_date' => $date,
                    'start_time' => '12:30',
                    'end_time' => '13:30',
                    'process_unit' => 'Break',
                    'activity' => 'Lunch break',
                    'clauses' => 'Not applicable',
                    'auditor_type' => '',
                    'auditor_name' => '',
                ];
            }

            $rows[] = [
                'audit_date' => $date,
                'start_time' => $isLastDay ? '16:30' : '16:30',
                'end_time' => $isLastDay ? '17:00' : '17:00',
                'process_unit' => 'Audit team / management representative',
                'activity' => $isLastDay ? 'Audit team review and closing meeting' : 'Daily audit team review and next day confirmation',
                'clauses' => 'Findings, conclusions and follow-up actions',
                'auditor_type' => $lead['role'],
                'auditor_name' => $lead['name'],
            ];
            $dateIndex++;
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['audit_date'] ?? '', $a['start_time'] ?? '', $a['auditor_name'] ?? ''] <=> [$b['audit_date'] ?? '', $b['start_time'] ?? '', $b['auditor_name'] ?? ''];
        });

        return $rows;
    }

    private function timeRangeHours(string $start, string $end): float
    {
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        if ($startTime === false || $endTime === false || $endTime <= $startTime) {
            return 0.0;
        }

        return ($endTime - $startTime) / 3600;
    }

    private function auditorWorkSlots(float $manDays): array
    {
        $slotCount = max(1, min(4, (int) ceil($manDays * 4)));
        $slots = [
            ['start' => '09:30', 'end' => '11:00'],
            ['start' => '11:00', 'end' => '12:30'],
            ['start' => '13:30', 'end' => '15:00'],
            ['start' => '15:00', 'end' => '16:30'],
        ];

        return array_slice($slots, 0, $slotCount);
    }

    private function leadAllocation(array $allocations): array
    {
        foreach ($allocations as $allocation) {
            if ((string) ($allocation['role'] ?? '') === 'Lead Auditor') {
                return [
                    'role' => 'Lead Auditor',
                    'name' => (string) ($allocation['name'] ?? ''),
                ];
            }
        }

        $first = $allocations[0] ?? ['role' => 'Auditor', 'name' => 'Not assigned'];

        return [
            'role' => (string) ($first['role'] ?? 'Auditor'),
            'name' => (string) ($first['name'] ?? 'Not assigned'),
        ];
    }

    private function fullDaySlots(bool $firstDay, bool $lastDay): array
    {
        return [
            ['start' => '09:00', 'end' => '09:30', 'kind' => 'lead', 'fixed' => ['process_unit' => 'Top management / audit team', 'activity' => $firstDay ? 'Opening meeting and audit plan confirmation' : 'Day opening briefing and follow-up from previous audit trails', 'clauses' => 'Audit objectives, scope, criteria and communication']],
            ['start' => '09:30', 'end' => '11:00', 'kind' => 'audit'],
            ['start' => '11:00', 'end' => '12:30', 'kind' => 'audit'],
            ['start' => '12:30', 'end' => '13:30', 'kind' => 'break', 'fixed' => ['process_unit' => 'Break', 'activity' => 'Lunch break', 'clauses' => 'Not applicable']],
            ['start' => '13:30', 'end' => '15:00', 'kind' => 'audit'],
            ['start' => '15:00', 'end' => '16:30', 'kind' => 'audit'],
            ['start' => '16:30', 'end' => '17:00', 'kind' => 'lead', 'fixed' => ['process_unit' => 'Audit team / management representative', 'activity' => $lastDay ? 'Audit team review and closing meeting' : 'Daily audit team review and next day confirmation', 'clauses' => 'Findings, conclusions and follow-up actions']],
        ];
    }

    private function partialDaySlots(bool $firstDay, bool $lastDay): array
    {
        return [
            ['start' => '09:00', 'end' => '09:15', 'kind' => 'lead', 'fixed' => ['process_unit' => 'Audit team / management representative', 'activity' => $firstDay ? 'Opening briefing and audit plan confirmation' : 'Day opening briefing', 'clauses' => 'Audit objectives, scope and criteria']],
            ['start' => '09:15', 'end' => '10:45', 'kind' => 'audit'],
            ['start' => '10:45', 'end' => '12:15', 'kind' => 'audit'],
            ['start' => '12:15', 'end' => '13:00', 'kind' => 'lead', 'fixed' => ['process_unit' => 'Audit team / management representative', 'activity' => $lastDay ? 'Audit team review and closing meeting' : 'Audit team review and plan confirmation', 'clauses' => 'Findings, conclusions and follow-up actions']],
        ];
    }

    private function auditPlanActivityPool(array $standards, string $eventType): array
    {
        $profile = $this->auditProgramStandardProfile($standards);

        if ($profile['has_food']) {
            if ($eventType === 'initial_stage1') {
                return [
                    ['process_unit' => 'Certification scope / food safety team', 'activity' => 'Application, scope, site readiness and HACCP documentation review', 'clauses' => 'HACCP 4.1-4.4, FS.1-FS.3'],
                    ['process_unit' => 'Food safety team', 'activity' => 'Hazard analysis, HACCP plan, CCP/OPRP identification and validation review', 'clauses' => 'FS.1, FS.3, 8.1'],
                    ['process_unit' => 'Kitchen, stores and dispatch', 'activity' => 'Site tour and PRP readiness verification', 'clauses' => 'FS.2, 7.1, 8.1'],
                    ['process_unit' => 'Management / QA', 'activity' => 'Internal audit, management review, objectives and legal/customer requirements review', 'clauses' => '6.2, 7.4, 9.2, 9.3'],
                ];
            }

            if ($eventType === 'initial_stage2' || $eventType === 'recertification') {
                return [
                    ['process_unit' => 'Receiving and dry/chilled storage', 'activity' => 'PRP implementation, receiving controls, storage temperature and stock rotation audit', 'clauses' => 'FS.2, 8.1, 8.4'],
                    ['process_unit' => 'Hot kitchen / cold kitchen', 'activity' => 'Operational process control and food handling practices audit', 'clauses' => 'FS.2, FS.3, 8.5'],
                    ['process_unit' => 'Food safety team / CCP records', 'activity' => 'CCP/OPRP monitoring, corrective action and verification records review', 'clauses' => 'FS.1-FS.3, 10.2'],
                    ['process_unit' => 'Cleaning, sanitation, pest control and waste', 'activity' => 'PRP effectiveness and hygiene controls audit', 'clauses' => 'FS.2, 7.1, 8.1'],
                    ['process_unit' => 'Dispatch / traceability', 'activity' => 'Traceability, withdrawal, recall and product release audit trail', 'clauses' => 'FS.4, 8.6, 8.7'],
                    ['process_unit' => 'QA / calibration / monitoring', 'activity' => 'Monitoring equipment, verification, validation and analysis of results', 'clauses' => '7.1, 9.1, FS.3'],
                    ['process_unit' => 'Management / QA', 'activity' => 'Internal audit, management review, complaints and improvement review', 'clauses' => '9.2, 9.3, 10.2, 10.3'],
                ];
            }

            return [
                ['process_unit' => 'Food safety management', 'activity' => 'Changes since previous audit, scope, objectives and legal/customer requirements review', 'clauses' => '4.1-4.4, 6.2, 7.4'],
                ['process_unit' => 'Selected PRPs and operations', 'activity' => 'Sampled PRP and operational control implementation audit', 'clauses' => 'FS.2, 8.1, 8.5'],
                ['process_unit' => 'HACCP plan / CCP records', 'activity' => 'CCP/OPRP monitoring, corrections and verification sample', 'clauses' => 'FS.1-FS.3, 10.2'],
                ['process_unit' => 'Traceability / dispatch', 'activity' => 'Traceability, withdrawal and recall arrangement verification', 'clauses' => 'FS.4, 8.6, 8.7'],
                ['process_unit' => 'QA / management', 'activity' => 'Previous findings, complaints, internal audit, management review and improvement follow-up', 'clauses' => '9.2, 9.3, 10.2, 10.3'],
            ];
        }

        return [
            ['process_unit' => 'Management system', 'activity' => 'Context, scope, policy, objectives and risk/opportunity review', 'clauses' => '4, 5, 6'],
            ['process_unit' => 'Core operations', 'activity' => 'Operational planning, service/product realization and process control audit', 'clauses' => '8'],
            ['process_unit' => 'Support processes', 'activity' => 'Resources, competence, awareness, communication and documented information review', 'clauses' => '7'],
            ['process_unit' => 'Performance evaluation', 'activity' => 'Monitoring, measurement, internal audit and management review audit', 'clauses' => '9'],
            ['process_unit' => 'Improvement', 'activity' => 'Nonconformity, corrective action and continual improvement review', 'clauses' => '10'],
        ];
    }

    private function auditPlanAuditorPool(array $appointments): array
    {
        $lead = ['role' => 'Lead Auditor', 'name' => ''];
        $auditors = [];
        $experts = [];

        foreach ($appointments as $appointment) {
            $name = trim((string) ($appointment['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $role = (string) ($appointment['appointment_role'] ?? '');
            if ($role === 'lead_auditor') {
                $lead = ['role' => 'Lead Auditor', 'name' => $name];
            } elseif ($role === 'technical_expert') {
                $experts[] = ['role' => 'Technical Expert', 'name' => $name];
            } elseif ($role === 'auditor') {
                $auditors[] = ['role' => 'Auditor', 'name' => $name];
            }
        }

        if ($lead['name'] === '' && $auditors !== []) {
            $lead = $auditors[0];
        }

        return ['lead' => $lead, 'auditors' => $auditors, 'experts' => $experts];
    }

    private function auditPlanAuditorForSlot(string $kind, array $auditors, int $index): array
    {
        if ($kind === 'break') {
            return ['role' => '', 'name' => ''];
        }

        if ($kind === 'lead') {
            return $auditors['lead'];
        }

        $pool = array_values(array_filter(array_merge($auditors['auditors'], [$auditors['lead']], $auditors['experts']), static fn (array $row): bool => $row['name'] !== ''));
        if ($pool === []) {
            return ['role' => 'Auditor', 'name' => 'Not assigned'];
        }

        return $pool[$index % count($pool)];
    }

    private function standardCodesText(array $standards): string
    {
        return implode(', ', array_filter(array_map(
            static fn (array $standard): string => (string) ($standard['standard_code'] ?? $standard['code'] ?? ''),
            $standards
        )));
    }

    private function auditProgramHtml(string $title, array $client, array $data): string
    {
        $program = $data['program'] ?? [];
        $sections = $this->auditProgramSections($data);
        $documentNumber = $program['document_number'] ?? 'F 42';
        $revisionNumber = $program['revision_number'] ?? '2';
        $issueNumber = $program['issue_number'] ?? '2';
        $documentDate = $program['document_date'] ?? '2022-05-15';

        $body = '';
        foreach ($sections as [$heading, $content]) {
            $body .= '<h2>' . esc($heading) . '</h2>' . $content;
        }

        $header = '<header class="f42-header"><table class="f42-header-table"><tbody>'
            . '<tr><td class="f42-logo" rowspan="4">' . $this->logoHtml('pdf-logo') . '</td>'
            . '<td class="f42-title" rowspan="4">' . esc($title) . '<div>Three-year certification audit programme</div></td>'
            . '<td class="f42-control-label">Document No.</td><td class="f42-control-value">' . esc((string) $documentNumber) . '</td></tr>'
            . '<tr><td class="f42-control-label">Revision No.</td><td class="f42-control-value">' . esc((string) $revisionNumber) . '</td></tr>'
            . '<tr><td class="f42-control-label">Issue No.</td><td class="f42-control-value">' . esc((string) $issueNumber) . '</td></tr>'
            . '<tr><td class="f42-control-label">Date</td><td class="f42-control-value">' . esc((string) $documentDate) . '</td></tr>'
            . '</tbody></table></header>';

        $clientBlock = '<section class="client f42-client"><table><tbody>'
            . '<tr><th>Client</th><td>' . esc((string) ($client['company'] ?? '')) . '</td><th>Program No.</th><td>' . esc((string) ($program['program_number'] ?? 'Not created')) . '</td></tr>'
            . '<tr><th>Scope</th><td colspan="3">' . esc((string) ($client['scope'] ?? '')) . '</td></tr>'
            . '<tr><th>Status</th><td>' . esc((string) ($program['status'] ?? '')) . '</td><th>Generated</th><td>' . esc(date('Y-m-d')) . '</td></tr>'
            . '</tbody></table></section>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->auditProgramCss() . '</style></head><body>'
            . $header
            . $clientBlock
            . $body
            . '</body></html>';
    }

    private function auditProgramSections(array $data): array
    {
        $program = $data['program'] ?? [];
        $client = $data['client'] ?? [];
        $fallbackPayload = $this->auditProgramFallbackPayload($data);
        $payload = json_decode((string) ($program['program_payload'] ?? ''), true) ?: $fallbackPayload;
        if ((int) ($payload['profile_version'] ?? 0) < 2 || ($payload['standard_signature'] ?? '') !== $this->auditProgramStandardSignature($data['standards'] ?? [])) {
            $payload['profile_version'] = 2;
            $payload['standard_signature'] = $fallbackPayload['standard_signature'] ?? '';
            $payload['category_label'] = $fallbackPayload['category_label'] ?? ($payload['category_label'] ?? '');
            $payload['process_label'] = $fallbackPayload['process_label'] ?? ($payload['process_label'] ?? '');
            $payload['category_subcategory'] = $fallbackPayload['category_subcategory'] ?? '';
            $payload['haccp_studies'] = $fallbackPayload['haccp_studies'] ?? '';
            $payload['coverage'] = $fallbackPayload['coverage'] ?? [];
            $payload['nc_summary'] = $fallbackPayload['nc_summary'] ?? [];
            $payload['legend_notes'] = $fallbackPayload['legend_notes'] ?? ($payload['legend_notes'] ?? '');
        }
        $payload = $this->normalizeAuditProgramPayload($payload, $data['standards'] ?? []);
        $payload['nc_summary'] = $this->auditProgramNcSummaryRows($data['standards'] ?? [], $data['ncrs'] ?? []);

        return [
            ['Client and Audit Information', $this->keyValueTable([
                'Client Ref No.' => $payload['client_reference'] ?? '',
                'Standard(s)' => $payload['standards_text'] ?? '',
                (string) ($payload['category_label'] ?? 'Category/Sub-Category') => $payload['category_subcategory'] ?? '',
                'Audit Language' => $payload['audit_language'] ?? '',
                'Audit Type' => $payload['audit_type'] ?? '',
                'Organization Name' => $payload['organization_name'] ?? ($client['company'] ?? ''),
                'Head Office Address' => $payload['head_office_address'] ?? '',
                'Site Address(es)' => $payload['site_addresses'] ?? '',
                'Scope of Company' => $payload['scope'] ?? ($client['scope'] ?? ''),
                'Exclusion(s)' => $payload['exclusions'] ?? '',
                'No. of Employees' => $payload['employee_count'] ?? '',
                'No. of Shifts' => $payload['shifts'] ?? '',
                (string) ($payload['process_label'] ?? 'Key audited processes') => $payload['haccp_studies'] ?? '',
                'Audit Duration (Days)' => ($payload['audit_duration_days'] ?? '') . ' (Total)',
            ])],
            ['Three-Year Certification Cycle', $this->auditProgramEventsTable($data['events'] ?? [], $data['appointments'] ?? [])],
            ['Processes / Standard Clauses', $this->auditProgramCoverageTable($payload['coverage'] ?? [])],
            ['Audit Committee', $this->auditProgramMatrixTable($payload['committee'] ?? [], 'role')],
            ['Audit NC Summary by Stage', $this->auditProgramMatrixTable($payload['nc_summary'] ?? [], 'standard')],
            ['Legend and Approval', $this->keyValueTable([
                'Legend / Notes' => $payload['legend_notes'] ?? '',
                'Prepared By' => $program['prepared_by_name'] ?? '',
                'Prepared Date' => $program['prepared_date'] ?? '',
                'Approved By' => $program['approved_by_name'] ?? '',
                'Approved Date' => $program['approved_date'] ?? '',
                'Electronic Note' => 'This controlled document is prepared through QSI AMS; signature requirements are managed by the certification body workflow.',
            ])],
        ];
    }

    private function auditProgramFallbackPayload(array $data): array
    {
        $client = $data['client'] ?? [];
        $standards = $data['standards'] ?? [];
        $reviewPayload = json_decode((string) (($data['application_review']['review_payload'] ?? '') ?: ''), true) ?: [];
        $duration = (new AuditDurationService())->calculateApplicationReview($client, $standards, $reviewPayload);
        $profile = $this->auditProgramStandardProfile($standards);
        $standardText = implode(', ', array_filter(array_map(
            static fn (array $row): string => (string) ($row['standard_code'] ?? $row['code'] ?? ''),
            $standards
        )));

        $coverage = [];
        foreach ($data['clauses'] ?? [] as $clause) {
            $stageCoverage = $this->auditProgramStageCoverage(
                (string) ($clause['standard_code'] ?? ''),
                (string) ($clause['clause_number'] ?? ''),
                (string) ($clause['clause_title'] ?? '')
            );
            $coverage[] = [
                'standard' => (string) ($clause['standard_code'] ?? ''),
                'clause_number' => (string) ($clause['clause_number'] ?? ''),
                'clause_title' => (string) ($clause['clause_title'] ?? ''),
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

        $committee = [];
        foreach (['Lead Auditor', 'Auditor 1', 'Auditor 2', 'Technical Specialist', 'Additional / Trainee Auditor', 'Observer'] as $role) {
            $committee[] = [
                'role' => $role,
                'initial_stage1' => '',
                'initial_stage2' => '',
                'surveillance1' => '',
                'surveillance2' => '',
                'recertification' => '',
            ];
        }

        foreach ($data['appointments'] ?? [] as $appointment) {
            if (stripos((string) ($appointment['appointment_role'] ?? ''), 'lead') === false) {
                continue;
            }

            foreach ($committee as &$row) {
                if ($row['role'] === 'Lead Auditor') {
                    $row[(string) ($appointment['event_type'] ?? '')] = (string) ($appointment['full_name'] ?? '');
                }
            }
            unset($row);
        }

        return [
            'profile_version' => 2,
            'standard_signature' => $this->auditProgramStandardSignature($standards),
            'client_reference' => $data['contract']['contract_number'] ?? '',
            'standards_text' => $standardText,
            'category_label' => $profile['category_label'],
            'process_label' => $profile['process_label'],
            'category_subcategory' => $reviewPayload['audit_category'] ?? $profile['category_default'],
            'audit_language' => $reviewPayload['communication_language'] ?? 'English',
            'audit_type' => 'Initial Certification',
            'organization_name' => $client['legal_name'] ?: ($client['company'] ?? ''),
            'head_office_address' => $client['address'] ?? '',
            'site_addresses' => 'Same as head office unless otherwise stated.',
            'scope' => $client['scope'] ?? '',
            'exclusions' => $reviewPayload['standard_exclusions'] ?? 'None',
            'employee_count' => (string) ($duration['employee_count'] ?? $client['employee_count'] ?? ''),
            'shifts' => $client['shift_pattern'] ?? '',
            'haccp_studies' => $reviewPayload['haccp_plans_processes'] ?? $profile['process_default'],
            'audit_duration_days' => number_format((float) $duration['total_days'], 2),
            'coverage' => $coverage,
            'committee' => $committee,
            'nc_summary' => $this->auditProgramNcSummaryRows($standards, $data['ncrs'] ?? []),
            'legend_notes' => 'Checked cells are planned for inspection. NE = not covered/applicable. PD = unplanned. X = inspected or planned clause.',
        ];
    }

    private function auditProgramNcSummaryRows(array $standards, array $ncrs): array
    {
        $stageKeys = ['initial_stage1', 'initial_stage2', 'surveillance1', 'surveillance2', 'recertification'];
        $rows = [];

        foreach ($standards as $standard) {
            $code = trim((string) ($standard['standard_code'] ?? $standard['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $rows[$code] = array_fill_keys($stageKeys, 0);
            $rows[$code]['standard'] = $code;
        }

        if ($rows === []) {
            $rows['All standards'] = array_fill_keys($stageKeys, 0);
            $rows['All standards']['standard'] = 'All standards';
        }

        foreach ($ncrs as $ncr) {
            $stage = (string) ($ncr['event_type'] ?? '');
            if (! in_array($stage, $stageKeys, true)) {
                continue;
            }

            $standardCode = trim((string) ($ncr['standard_code'] ?? ''));
            if ($standardCode !== '' && isset($rows[$standardCode])) {
                $rows[$standardCode][$stage]++;
                continue;
            }

            if (count($rows) === 1) {
                $onlyKey = array_key_first($rows);
                $rows[$onlyKey][$stage]++;
            }
        }

        return array_values($rows);
    }

    private function auditProgramEventsTable(array $events, array $appointments): string
    {
        $labels = [
            'initial_stage1' => 'Initial Stage 1',
            'initial_stage2' => 'Initial Stage 2',
            'surveillance1' => 'Surveillance 1',
            'surveillance2' => 'Surveillance 2',
            'recertification' => 'Recertification',
        ];

        if ($events === []) {
            return '<p class="muted">No audit events available.</p>';
        }

        $html = '<table class="f42-table f42-cycle"><thead><tr><th>Audit Stage</th><th>Audit No.</th><th>Planned Dates</th><th>Audit Window</th><th>Duration</th><th>Responsible Auditor</th><th>Status</th></tr></thead><tbody>';
        foreach ($events as $event) {
            $type = (string) ($event['event_type'] ?? '');
            $plannedDates = trim((string) ($event['planned_start_date'] ?? '') . ' to ' . (string) ($event['planned_end_date'] ?? ''), ' to');
            $auditWindow = trim((string) ($event['audit_window_start'] ?? '') . ' to ' . (string) ($event['audit_window_end'] ?? ''), ' to');
            $status = str_replace(' ', '&nbsp;', esc(ucwords(str_replace('_', ' ', (string) ($event['status'] ?? '')))));
            $html .= '<tr>'
                . '<td>' . esc($labels[$type] ?? ucwords(str_replace('_', ' ', $type))) . '</td>'
                . '<td>' . esc((string) ($event['audit_number'] ?? '')) . '</td>'
                . '<td>' . esc($plannedDates) . '</td>'
                . '<td>' . esc($auditWindow) . '</td>'
                . '<td class="center">' . esc((string) ($event['duration_days'] ?? '')) . ' day(s)</td>'
                . '<td>' . esc($this->auditProgramResponsibleAuditor((string) ($event['audit_number'] ?? ''), $type, $appointments)) . '</td>'
                . '<td class="center nowrap">' . $status . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditProgramResponsibleAuditor(string $auditNumber, string $eventType, array $appointments): string
    {
        $auditors = [];
        foreach ($appointments as $appointment) {
            if ((string) ($appointment['event_type'] ?? '') !== $eventType && (string) ($appointment['audit_number'] ?? '') !== $auditNumber) {
                continue;
            }

            $name = trim((string) ($appointment['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $role = ucwords(str_replace('_', ' ', (string) ($appointment['appointment_role'] ?? 'Auditor')));
            $auditors[] = $name . ' (' . $role . ')';
        }

        return $auditors === [] ? 'Not assigned' : implode(', ', array_values(array_unique($auditors)));
    }

    private function auditProgramCoverageTable(array $rows): string
    {
        if ($rows === []) {
            return '<p class="muted">No clause coverage available.</p>';
        }

        $html = '<table class="f42-table f42-coverage"><thead><tr><th>Standard</th><th>Clause / Process</th><th>Coverage Requirement</th><th>Stage 1</th><th>Stage 2</th><th>Surv. 1</th><th>Surv. 2</th><th>Recert.</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . esc((string) ($row['standard'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['clause_number'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['clause_title'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['initial_stage1'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['initial_stage2'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['surveillance1'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['surveillance2'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['recertification'] ?? '')) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditProgramMatrixTable(array $rows, string $labelKey): string
    {
        if ($rows === []) {
            return '<p class="muted">No records available.</p>';
        }

        $html = '<table class="f42-table f42-matrix"><thead><tr><th>' . esc(ucwords(str_replace('_', ' ', $labelKey))) . '</th><th>Stage 1</th><th>Stage 2</th><th>Surv. 1</th><th>Surv. 2</th><th>Recert.</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . esc((string) ($row[$labelKey] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['initial_stage1'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['initial_stage2'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['surveillance1'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['surveillance2'] ?? '')) . '</td>'
                . '<td class="center">' . esc((string) ($row['recertification'] ?? '')) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function normalizeAuditProgramPayload(array $payload, array $standards): array
    {
        $profile = $this->auditProgramStandardProfile($standards);
        $payload['category_label'] = $profile['category_label'];
        $payload['process_label'] = $profile['process_label'];

        if (! $profile['has_food']) {
            $payload['haccp_studies'] = '';
            if (preg_match('/^[A-Z]+\/[A-Z0-9]+$/', (string) ($payload['category_subcategory'] ?? ''))) {
                $payload['category_subcategory'] = $profile['category_default'];
            }
        }

        return $payload;
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
            return ['has_food' => isset($types['food']), 'category_label' => 'Scheme / IAF / category codes', 'category_default' => '', 'process_label' => 'Key audited processes / standard-specific controls', 'process_default' => ''];
        }

        if (isset($types['food'])) {
            return ['has_food' => true, 'category_label' => 'Food chain category / sub-category', 'category_default' => '', 'process_label' => 'HACCP studies / food safety plans', 'process_default' => ''];
        }

        if (isset($types['environment'])) {
            return ['has_food' => false, 'category_label' => 'IAF code / environmental aspect category', 'category_default' => '', 'process_label' => 'Significant environmental aspects / key controls', 'process_default' => ''];
        }

        if (isset($types['ohs'])) {
            return ['has_food' => false, 'category_label' => 'IAF code / OHS risk category', 'category_default' => '', 'process_label' => 'Significant hazards / key OHS controls', 'process_default' => ''];
        }

        if (isset($types['medical'])) {
            return ['has_food' => false, 'category_label' => 'Medical device category / risk class', 'category_default' => '', 'process_label' => 'Device families / regulated processes', 'process_default' => ''];
        }

        if (isset($types['cb_ms'])) {
            return ['has_food' => false, 'category_label' => 'Certification scheme / IAF scope', 'category_default' => '', 'process_label' => 'Certification schemes / witnessed activities', 'process_default' => ''];
        }

        if (isset($types['cb_product'])) {
            return ['has_food' => false, 'category_label' => 'Product certification scheme / product group', 'category_default' => '', 'process_label' => 'Product groups / evaluation activities', 'process_default' => ''];
        }

        return ['has_food' => false, 'category_label' => 'IAF scope code(s)', 'category_default' => '', 'process_label' => 'Key audited processes', 'process_default' => ''];
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
                $requirements = array_merge($requirements, ['Product and process food safety review', 'PRP / OPRP / CCP control effectiveness', 'Traceability, withdrawal and recall arrangements', 'Food safety hazard analysis and validation status', 'Food safety legal and customer requirements']);
            } elseif (str_contains($code, 'ISO 14001')) {
                $requirements = array_merge($requirements, ['Significant environmental aspects and impacts', 'Compliance obligations and evaluation status', 'Operational controls for environmental risks', 'Emergency preparedness and response', 'Environmental performance monitoring']);
            } elseif (str_contains($code, 'ISO 45001')) {
                $requirements = array_merge($requirements, ['Hazard identification and OHS risk controls', 'Legal and other OHS requirements', 'Worker consultation and participation', 'Emergency preparedness and response', 'Incident, injury and ill-health investigation']);
            } elseif (str_contains($code, 'ISO 13485')) {
                $requirements = array_merge($requirements, ['Medical device regulatory requirements', 'Device family, risk class and intended use', 'Sterilization, cleanroom or special process controls', 'Traceability, UDI and post-market surveillance', 'Complaint, vigilance and advisory notice controls']);
            } elseif (str_contains($code, 'ISO 17021')) {
                $requirements = array_merge($requirements, ['Impartiality and certification decision controls', 'Auditor competence and evaluation process', 'Certification process, audit program and file review', 'Complaints, appeals and suspension process', 'Witnessed activity / office assessment coverage']);
            } elseif (str_contains($code, 'ISO 17065')) {
                $requirements = array_merge($requirements, ['Product certification scheme requirements', 'Evaluation, review and certification decision process', 'Impartiality and conflict of interest controls', 'Use of marks, licences and surveillance arrangements', 'Complaints, appeals and product nonconformity controls']);
            } else {
                $requirements = array_merge($requirements, ['Customer and statutory/regulatory requirements', 'Scope and complexity of the management system', 'Process performance and operational control', 'Internal audit and management review results', 'Customer satisfaction, complaints and improvement trends']);
            }
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

    private function auditReportSections(array $data): array
    {
        return [
            ['Audit Events', $this->eventTable($data['events'])],
            ['Audit Report Identification', $this->auditReportIdentification($data)],
            ['Audit Report Submission', $this->keyValueTable([
                'Report Submission Date' => $this->auditReportSubmissionDate($data['reports'] ?? []),
            ])],
            ['Report Drafts', $this->recordTable(
                $this->auditReportDraftRows($data['reports'] ?? []),
                ['audit_number', 'report_type', 'status', 'version_number', 'submitted_date'],
                ['submitted_date' => 'Submitted Date']
            )],
            ['Checklist / Report Notes', $this->reportChecklistNotes($data['report_sections'] ?? [], $data['client'] ?? [])],
            ['Nonconformities', $this->recordTable($data['ncrs'], ['ncr_number', 'classification', 'status', 'finding'])],
            ['CAPA', $this->recordDetailTables($this->auditReportCapaRows($data['capas'] ?? []), ['capa_number', 'status', 'issue', 'immediate_correction', 'root_cause', 'corrective_action', 'preventive_action', 'responsible_person', 'target_date', 'evidence_reference', 'verification', 'effectiveness', 'closed_at'], 'capa_number')],
        ];
    }

    private function auditReportIdentification(array $data): string
    {
        return $this->keyValueTable([
            'Audited by' => $this->auditTeamDisplay($data['appointments'] ?? []),
            'Report submitted to client representative' => $this->clientRepresentativeDisplay($data),
        ]);
    }

    private function auditReportSubmissionDate(array $reports): string
    {
        foreach ($reports as $report) {
            $submittedAt = trim((string) ($report['submitted_at'] ?? ''));
            if ($submittedAt !== '') {
                return $this->dateOnly($submittedAt);
            }
        }

        return 'Not submitted';
    }

    private function auditReportDraftRows(array $reports): array
    {
        return array_map(function (array $report): array {
            $submittedAt = trim((string) ($report['submitted_at'] ?? ''));
            $report['submitted_date'] = $submittedAt !== '' ? $this->dateOnly($submittedAt) : 'Not submitted';
            unset($report['submitted_at']);

            return $report;
        }, $reports);
    }

    private function auditReportCapaRows(array $capas): array
    {
        return array_map(function (array $capa): array {
            $closedAt = trim((string) ($capa['closed_at'] ?? ''));
            if ($closedAt !== '') {
                $capa['closed_at'] = $this->dateOnly($closedAt);
            }

            return $capa;
        }, $capas);
    }

    private function auditTeamDisplay(array $appointments): string
    {
        $names = [];
        foreach ($appointments as $appointment) {
            $name = trim((string) ($appointment['full_name'] ?? $appointment['auditor_name'] ?? $appointment['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $role = trim((string) ($appointment['appointment_role'] ?? $appointment['role'] ?? ''));
            $display = $role !== '' ? $name . ' (' . ucwords(str_replace('_', ' ', $role)) . ')' : $name;
            if (! in_array($display, $names, true)) {
                $names[] = $display;
            }
        }

        return $names === [] ? 'Not assigned' : implode("\n", $names);
    }

    private function clientRepresentativeDisplay(array $data): string
    {
        $client = $data['client'] ?? [];
        $contract = $data['contract'] ?? [];
        $application = $data['certification_application'] ?? [];
        $review = $data['application_review'] ?? [];

        foreach ([
            $contract['client_signatory_name'] ?? null,
            $contract['signed_by_name'] ?? null,
            $client['contact_person'] ?? null,
            $application['declaration_name'] ?? null,
            $review['management_representative'] ?? null,
            $client['contact_name'] ?? null,
        ] as $candidate) {
            $name = trim((string) $candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return 'Client authorized representative';
    }

    private function reportChecklistNotes(array $sections, array $client = []): string
    {
        if ($sections === []) {
            return '<p class="muted">No checklist notes available.</p>';
        }

        $scope = trim((string) ($client['scope'] ?? $client['business_activity'] ?? ''));
        $html = '';
        foreach ($sections as $index => $section) {
            $standard = trim((string) ($section['standard_code'] ?? ''));
            $clauseNumber = trim((string) ($section['clause_number'] ?? ''));
            $clauseTitle = trim((string) ($section['clause_title'] ?? ''));
            $sectionKey = trim((string) ($section['section_key'] ?? 'Audit note'));
            $content = trim((string) ($section['section_content'] ?? ''));
            $headingParts = array_filter([$standard, $clauseNumber, $clauseTitle], static fn (string $value): bool => $value !== '');
            $heading = $headingParts !== [] ? implode(' - ', $headingParts) : 'Checklist item ' . ($index + 1);

            $html .= '<div class="report-note">';
            $html .= '<div class="report-note-heading">' . esc($heading) . '</div>';
            $html .= '<table class="report-note-meta"><tbody><tr>';
            $html .= '<th>Standard</th><td>' . esc($standard !== '' ? $standard : 'N/A') . '</td>';
            $html .= '<th>Clause</th><td>' . esc($clauseNumber !== '' ? $clauseNumber : 'N/A') . '</td>';
            $html .= '<th>Record Type</th><td>' . esc(ucwords(str_replace('_', ' ', $sectionKey))) . '</td>';
            $html .= '</tr></tbody></table>';
            if ($scope !== '') {
                $html .= '<table class="report-note-meta"><tbody><tr><th>Scope</th><td>' . esc($scope) . '</td></tr></tbody></table>';
            }
            $html .= '<div class="report-note-body">';
            $html .= '<div class="report-note-label">Conformity statement and objective evidence</div>';
            $content = $this->cleanReportNoteContent($content);
            $html .= $content !== '' ? nl2br(esc($content)) : '<span class="muted">No note recorded.</span>';
            $html .= '</div></div>';
        }

        return $html;
    }

    private function cleanReportNoteContent(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", trim($content));
        $content = preg_replace('/\n+Clause Pool basis:\s*\n+/i', "\n\n", $content) ?? $content;
        $content = preg_replace('/\n*Template reference:\s*CP-\d+\.\s*Prepared from approved Clause Pool; editable by auditor\.?/i', '', $content) ?? $content;

        return trim($content);
    }

    private function ncrCapaSections(array $data): array
    {
        return [
            ['Audit Event', $this->eventTable($data['events'])],
            ['Nonconformities', $this->recordDetailTables($data['ncrs'], ['ncr_number', 'classification', 'status', 'requirement', 'finding', 'objective_evidence', 'target_date'], 'ncr_number')],
            ['Corrective Action / Preventive Action', $this->recordDetailTables($data['capas'] ?? [], ['capa_number', 'ncr_number', 'clause_number', 'ncr_requirement', 'ncr_finding', 'status', 'immediate_correction', 'root_cause', 'corrective_action', 'preventive_action', 'responsible_person', 'target_date', 'evidence_reference', 'verification', 'effectiveness', 'closed_at'], 'capa_number')],
        ];
    }

    private function technicalReviewSections(array $data): array
    {
        $review = $data['technical_review'] ?? [];
        $payload = ! empty($review['checklist_payload']) ? (json_decode((string) $review['checklist_payload'], true) ?: []) : [];
        $ncrs = $data['ncrs'] ?? [];
        $openNcrs = array_filter($ncrs, static fn (array $ncr): bool => ! in_array((string) ($ncr['status'] ?? ''), ['closed', 'verified_closed'], true));

        $sections = [
            [
                'Technical Review Identification',
                $this->keyValueTable([
                    'Audit file' => trim((string) (($review['audit_number'] ?? '') . ' - ' . str_replace('_', ' ', (string) ($review['event_type'] ?? '')))),
                    'Technical reviewer' => trim((string) (($review['reviewer_name'] ?? '') . ' - ' . str_replace('_', ' ', (string) ($review['reviewer_type'] ?? '')))),
                    'Review status' => $review['status'] ?? 'Not recorded',
                    'Recommendation' => isset($review['recommendation']) ? str_replace('_', ' ', (string) $review['recommendation']) : '',
                    'Reviewed at' => $review['reviewed_at'] ?? 'Not recorded',
                    'Review notes' => $payload['review_notes'] ?? '',
                ]),
            ],
            [
                'Audit Information and Certification Review Decision',
                $this->keyValueTable([
                    'Audit category / NACE code' => $payload['audit_category_nace'] ?? '',
                    'Transfer' => $payload['transfer_status'] ?? '',
                    'Accredited scope held with IAS/SAAC' => $payload['accredited_scope_ias_saac'] ?? '',
                    'Accredited scope held with FSSC' => $payload['accredited_scope_fssc'] ?? '',
                    'IAS/SAAC registration required' => $payload['ias_saac_registration_required'] ?? '',
                    'Audit result' => $payload['audit_result'] ?? '',
                    'Any complaint received' => $payload['complaints_received'] ?? '',
                    'Review of client management system' => $payload['client_management_system_review'] ?? '',
                    'Certificate authorization decision' => $payload['certificate_authorization'] ?? '',
                    'Authorization date' => $payload['authorization_date'] ?? '',
                    'Outstanding items' => $payload['outstanding_items'] ?? '',
                ]),
            ],
            [
                'Technical Review Checklist',
                $this->keyValueTable([
                    'Auditor competence confirmed' => $this->yesNo($review['competency_confirmed'] ?? 0),
                    'Audit duration confirmed' => $this->yesNo($review['duration_confirmed'] ?? 0),
                    'Application and contract scope confirmed' => $this->yesNo($review['application_confirmed'] ?? 0),
                    'Audit reports and evidence reviewed' => $this->yesNo($review['reports_confirmed'] ?? 0),
                    'NCR/CAPA closure confirmed' => $this->yesNo($review['ncr_capa_confirmed'] ?? 0),
                    'Scope, issue date and expiry confirmed' => $this->yesNo($review['scope_dates_confirmed'] ?? 0),
                    'Impartiality and conflict check confirmed' => $this->yesNo($review['impartiality_confirmed'] ?? 0),
                ]),
            ],
        ];

        foreach ($this->groupChecklistRows($payload['checklist_rows'] ?? []) as $group => $rows) {
            $sections[] = [
                $group,
                $this->recordTable($rows, ['ref', 'action_by', 'requirement', 'result', 'evidence']),
            ];
        }

        return array_merge($sections, [
            [
                'File Evidence Summary',
                $this->keyValueTable([
                    'Standards reviewed' => implode(', ', array_filter(array_column($data['standards'] ?? [], 'standard_code'))),
                    'Audit report records' => count($data['reports'] ?? []),
                    'Total NCRs' => count($ncrs),
                    'Open NCRs' => count($openNcrs),
                    'CAPA records' => count($data['capas'] ?? []),
                    'Certificates already issued' => count($data['certificates'] ?? []),
                ]),
            ],
            ['Audit Team Reviewed', $this->recordTable($data['appointments'] ?? [], ['full_name', 'appointment_role', 'competence_status', 'conflict_status', 'status'])],
            ['Report Records Reviewed', $this->recordTable($data['reports'] ?? [], ['audit_number', 'report_type', 'version_number', 'status', 'submitted_at'])],
            ['NCR / CAPA Closure Reviewed', $this->recordDetailTables($data['capas'] ?? [], ['capa_number', 'ncr_number', 'status', 'root_cause', 'corrective_action', 'evidence_reference', 'verification', 'closed_at'], 'capa_number')],
        ]);
    }

    private function decisionSections(array $data): array
    {
        $decision = $data['decision'] ?? [];
        $review = $data['technical_review'] ?? [];
        $decisionPayload = ! empty($decision['decision_payload']) ? (json_decode((string) $decision['decision_payload'], true) ?: []) : [];
        $ncrs = $data['ncrs'] ?? [];
        $openNcrs = array_filter($ncrs, static fn (array $ncr): bool => ! in_array((string) ($ncr['status'] ?? ''), ['closed', 'verified_closed'], true));

        $sections = [
            [
                'Decision Basis',
                $this->keyValueTable([
                    'Audit file' => trim((string) (($review['audit_number'] ?? '') . ' - ' . str_replace('_', ' ', (string) ($review['event_type'] ?? '')))),
                    'Technical review status' => $review['status'] ?? 'Not recorded',
                    'Technical review recommendation' => isset($review['recommendation']) ? str_replace('_', ' ', (string) $review['recommendation']) : '',
                    'Technical reviewer' => $review['reviewer_name'] ?? '',
                    'Open NCRs at decision' => count($openNcrs),
                    'Standards under decision' => implode(', ', array_filter(array_column($data['standards'] ?? [], 'standard_code'))),
                ]),
            ],
            [
                'Pre-Issue General Information',
                $this->keyValueTable([
                    'Application ID' => $decisionPayload['application_id'] ?? '',
                    'Client name' => $data['client']['company'] ?? '',
                    'Site address' => $data['client']['address'] ?? '',
                    'Standard / scheme' => implode(', ', array_filter(array_column($data['standards'] ?? [], 'standard_code'))),
                    'Standard category / NACE' => $decisionPayload['standard_category_nace'] ?? '',
                    'Audit type' => str_replace('_', ' ', (string) ($review['event_type'] ?? '')),
                    'Audit dates' => trim((string) (($review['planned_start_date'] ?? '') . ' to ' . ($review['planned_end_date'] ?? '')), ' to'),
                    'Certificate no.' => $decisionPayload['certificate_number'] ?? '',
                    'Certificate decision date' => $decisionPayload['certificate_decision_date'] ?? '',
                ]),
            ],
            [
                'Certification Decision',
                $this->keyValueTable([
                    'Decision maker' => trim((string) (($decision['decision_maker_name'] ?? '') . ' - ' . str_replace('_', ' ', (string) ($decision['decision_maker_type'] ?? '')))),
                    'Decision' => isset($decision['decision']) ? str_replace('_', ' ', (string) $decision['decision']) : 'Not recorded',
                    'Decision status' => $decision['status'] ?? '',
                    'Decision date' => $decision['decided_at'] ?? '',
                    'Decision reason' => $decision['reason'] ?? '',
                    'Electronic signature' => $decision['electronic_signature'] ?? '',
                ]),
            ],
        ];

        foreach ($this->groupChecklistRows($decisionPayload['checklist_rows'] ?? []) as $group => $rows) {
            $sections[] = [
                $group,
                $this->recordTable($rows, ['ref', 'requirement', 'result', 'evidence']),
            ];
        }

        $sections[] = [
            'Certification Decision Declaration',
            $this->keyValueTable([
                'Declaration confirmed' => $this->yesNo($decisionPayload['declaration_confirmed'] ?? 0),
                'Declaration' => $decisionPayload['declaration_text'] ?? '',
                'Technical reviewer name' => $decisionPayload['technical_reviewer_name'] ?? '',
                'Technical reviewer date' => $decisionPayload['technical_reviewer_date'] ?? '',
                'Certification decision maker name' => $decisionPayload['certification_decision_maker_name'] ?? '',
                'Certification decision maker date' => $decisionPayload['certification_decision_maker_date'] ?? '',
            ]),
        ];

        return array_merge($sections, [
            [
                'General Manager Final Approval',
                $this->keyValueTable([
                    'GM approval status' => ($decision['status'] ?? '') === 'gm_approved' ? 'Approved' : 'Not approved',
                    'Approved by' => $decision['gm_approved_by_name'] ?? '',
                    'Approved at' => $decision['gm_approved_at'] ?? '',
                    'Approval notes' => $decision['gm_approval_notes'] ?? '',
                ]),
            ],
            ['Certificates Issued', $this->recordTable($data['certificates'] ?? [], ['certificate_number', 'standard_code', 'issue_date', 'expiry_date', 'status'])],
        ]);
    }

    private function feedbackSections(array $data): array
    {
        $feedback = $data['feedback'] ?? [];

        return [
            [
                'Client Feedback',
                $this->keyValueTable([
                    'Certificate' => $feedback['certificate_number'] ?? 'Not selected',
                    'Contact name' => $feedback['contact_name'] ?? 'Not recorded',
                    'Contact email' => $feedback['contact_email'] ?? '',
                    'Submitted at' => $feedback['submitted_at'] ?? '',
                    'Status' => $feedback['status'] ?? '',
                    'Overall rating' => $feedback['overall_rating'] ?? '',
                    'Communication rating' => $feedback['communication_rating'] ?? '',
                    'Auditor performance rating' => $feedback['auditor_rating'] ?? '',
                    'Report quality rating' => $feedback['report_quality_rating'] ?? '',
                    'Comments' => $feedback['comments'] ?? '',
                    'Improvement suggestion' => $feedback['improvement_suggestion'] ?? '',
                ]),
            ],
            [
                'Continual Improvement Note',
                $this->keyValueTable([
                    'Monitoring purpose' => 'Feedback is recorded for client satisfaction monitoring, certification process improvement and management review input.',
                    'Follow up required' => trim((string) ($feedback['improvement_suggestion'] ?? '')) !== '' ? 'Yes' : 'No',
                ]),
            ],
        ];
    }

    private function groupChecklistRows(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $group = trim((string) ($row['group'] ?? 'Checklist'));
            if ($group === '') {
                $group = 'Checklist';
            }

            $grouped[$group][] = $row;
        }

        return $grouped;
    }

    private function keyValueTable(array $rows): string
    {
        $html = '<table><tbody>';
        foreach ($rows as $key => $value) {
            $html .= '<tr><th>' . esc((string) $key) . '</th><td>' . nl2br(esc((string) $value)) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function standardTable(array $standards): string
    {
        return $this->recordTable($standards, ['standard_code', 'scheme_type', 'scope']);
    }

    private function eventTable(array $events): string
    {
        return $this->recordTable($events, ['audit_number', 'event_type', 'planned_start_date', 'planned_end_date', 'status']);
    }

    private function planItemTable(array $items): string
    {
        return $this->recordTable($items, ['audit_date', 'start_time', 'end_time', 'activity_type', 'process_name', 'auditor_name']);
    }

    private function recordTable(array $records, array $columns, array $labels = []): string
    {
        if ($records === []) {
            return '<p class="muted">No records available.</p>';
        }

        $html = '<table><thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th>' . esc((string) ($labels[$column] ?? ucwords(str_replace('_', ' ', $column)))) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($records as $record) {
            $html .= '<tr>';
            foreach ($columns as $column) {
                $html .= '<td>' . nl2br(esc((string) ($record[$column] ?? ''))) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function recordDetailTables(array $records, array $columns, ?string $titleColumn = null): string
    {
        if ($records === []) {
            return '<p class="muted">No records available.</p>';
        }

        $html = '';
        foreach ($records as $index => $record) {
            $title = $titleColumn !== null && trim((string) ($record[$titleColumn] ?? '')) !== ''
                ? (string) $record[$titleColumn]
                : 'Record ' . ($index + 1);

            $html .= '<div class="detail-record">';
            $html .= '<div class="detail-record-title">' . esc($title) . '</div>';
            $html .= '<table class="detail-table"><tbody>';
            foreach ($columns as $column) {
                $html .= '<tr><th>' . esc(ucwords(str_replace('_', ' ', $column))) . '</th><td>' . nl2br(esc((string) ($record[$column] ?? ''))) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }

        return $html;
    }

    private function client(int $tenantId, int $clientId): array
    {
        $client = $this->db->table('clients')
            ->where('tenant_id', $tenantId)
            ->where('id', $clientId)
            ->get(1)
            ->getRowArray();

        if ($client === null) {
            throw new \RuntimeException('Client not found.');
        }

        return $client;
    }

    private function latest(string $table, array $where): ?array
    {
        if (! $this->db->tableExists($table)) {
            return null;
        }

        $builder = $this->db->table($table);
        foreach ($where as $field => $value) {
            $builder->where($field, $value);
        }

        return $builder->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    private function latestTechnicalReviewForEvents(int $tenantId, array $events): ?array
    {
        if ($events === []) {
            return null;
        }

        return $this->db->table('technical_reviews')
            ->select('technical_reviews.*, personnel.full_name AS reviewer_name, personnel.personnel_type AS reviewer_type, audit_events.audit_number, audit_events.event_type, audit_events.planned_start_date, audit_events.planned_end_date')
            ->join('personnel', 'personnel.id = technical_reviews.reviewer_personnel_id', 'left')
            ->join('audit_events', 'audit_events.id = technical_reviews.audit_event_id', 'left')
            ->where('technical_reviews.tenant_id', $tenantId)
            ->whereIn('technical_reviews.audit_event_id', array_column($events, 'id'))
            ->orderBy('technical_reviews.id', 'DESC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function technicalReviewForEvent(int $tenantId, int $eventId): ?array
    {
        return $this->db->table('technical_reviews')
            ->select('technical_reviews.*, personnel.full_name AS reviewer_name, personnel.personnel_type AS reviewer_type, audit_events.audit_number, audit_events.event_type, audit_events.planned_start_date, audit_events.planned_end_date')
            ->join('personnel', 'personnel.id = technical_reviews.reviewer_personnel_id', 'left')
            ->join('audit_events', 'audit_events.id = technical_reviews.audit_event_id', 'left')
            ->where('technical_reviews.tenant_id', $tenantId)
            ->where('technical_reviews.audit_event_id', $eventId)
            ->orderBy('technical_reviews.id', 'DESC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function decisionForReview(int $tenantId, int $reviewId): ?array
    {
        return $this->db->table('certification_decisions')
            ->select('certification_decisions.*, personnel.full_name AS decision_maker_name, personnel.personnel_type AS decision_maker_type, users.full_name AS gm_approved_by_name')
            ->join('personnel', 'personnel.id = certification_decisions.decision_maker_personnel_id', 'left')
            ->join('users', 'users.id = certification_decisions.gm_approved_by_user_id', 'left')
            ->where('certification_decisions.tenant_id', $tenantId)
            ->where('certification_decisions.technical_review_id', $reviewId)
            ->orderBy('certification_decisions.id', 'DESC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function latestFeedback(int $tenantId, int $clientId): ?array
    {
        return $this->db->table('client_feedback')
            ->select('client_feedback.*, certificates.certificate_number')
            ->join('certificates', 'certificates.id = client_feedback.certificate_id', 'left')
            ->where('client_feedback.tenant_id', $tenantId)
            ->where('client_feedback.client_id', $clientId)
            ->orderBy('client_feedback.id', 'DESC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function clientStandards(int $clientId): array
    {
        return $this->db->table('client_standards')
            ->select('client_standards.*, standards.code AS standard_code, standards.scheme_type')
            ->join('standards', 'standards.id = client_standards.standard_id')
            ->where('client_standards.client_id', $clientId)
            ->get()
            ->getResultArray();
    }

    private function clientClauses(int $tenantId, int $clientId): array
    {
        return $this->db->table('clause_library')
            ->select('clause_library.*, standards.code AS standard_code')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->join('client_standards', 'client_standards.standard_id = clause_library.standard_id')
            ->where('clause_library.tenant_id', $tenantId)
            ->where('client_standards.client_id', $clientId)
            ->where('clause_library.active', 1)
            ->orderBy('standards.code', 'ASC')
            ->orderBy('clause_library.clause_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function applicationData(int $tenantId, int $clientId): array
    {
        if (! $this->db->tableExists('certification_applications')) {
            return [];
        }

        $application = $this->latest('certification_applications', ['tenant_id' => $tenantId, 'client_id' => $clientId]);
        if ($application === null) {
            return [];
        }

        $selectedStandards = $this->db->table('application_selected_standards')
            ->where('application_id', (int) $application['id'])
            ->orderBy('standard_code', 'ASC')
            ->get()
            ->getResultArray();
        $selectedStandardCodes = $this->normaliseStandardCodes(array_column($selectedStandards, 'standard_code'));

        $rows = $this->db->table('application_questions')
            ->select('application_questions.section, application_questions.question_text, application_questions.display_order, application_questions.standard_codes, application_answers.answer_text, question_library.applicable_standards')
            ->join('application_answers', 'application_answers.application_question_id = application_questions.id', 'left')
            ->join('question_library', 'question_library.id = application_questions.question_library_id', 'left')
            ->where('application_questions.application_id', (int) $application['id'])
            ->whereNotIn('application_questions.section', $this->excludedCertificationApplicationSections())
            ->where('application_questions.question_type !=', 'file')
            ->orderBy('application_questions.section', 'ASC')
            ->orderBy('application_questions.display_order', 'ASC')
            ->get()
            ->getResultArray();

        $answers = [];
        foreach ($rows as $row) {
            if ($this->certificationApplicationSectionExcluded((string) ($row['section'] ?? ''))) {
                continue;
            }

            if (! $this->applicationQuestionAppliesToStandards($row, $selectedStandardCodes)) {
                continue;
            }

            $answers[$row['section']][] = $row;
        }

        return [
            'application' => $application,
            'reviewer' => $this->applicationReviewer($application['reviewed_by'] ?? null),
            'selected_standards' => $selectedStandards,
            'answers_by_section' => $answers,
            'attachments' => $this->db->table('application_attachments')
                ->where('application_id', (int) $application['id'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getResultArray(),
        ];
    }

    private function applicationQuestionAppliesToStandards(array $question, array $selectedStandards): bool
    {
        $libraryStandards = $this->normaliseStandardCodes(json_decode((string) ($question['applicable_standards'] ?? '[]'), true) ?: []);

        if ($libraryStandards !== []) {
            return in_array('COMMON', $libraryStandards, true) || array_intersect($libraryStandards, $selectedStandards) !== [];
        }

        $questionStandards = $this->normaliseStandardCodes(json_decode((string) ($question['standard_codes'] ?? '[]'), true) ?: []);

        return $questionStandards === []
            || in_array('COMMON', $questionStandards, true)
            || array_intersect($questionStandards, $selectedStandards) !== [];
    }

    private function normaliseStandardCodes(array $codes): array
    {
        return array_values(array_unique(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            array_filter(array_map('strval', $codes), static fn (string $code): bool => trim($code) !== '')
        )));
    }

    private function applicationReviewer(mixed $userId): array
    {
        if ($userId === null || $userId === '') {
            return ['full_name' => '', 'designation' => 'Technical Manager'];
        }

        $reviewer = $this->db->table('users')
            ->select('users.full_name, users.email, roles.name AS role_name')
            ->join('roles', 'roles.id = users.primary_role_id', 'left')
            ->where('users.id', (int) $userId)
            ->get(1)
            ->getRowArray();

        if ($reviewer === null) {
            return ['full_name' => '', 'designation' => 'Technical Manager'];
        }

        return [
            'full_name' => trim((string) ($reviewer['full_name'] ?: $reviewer['email'])),
            'designation' => trim((string) ($reviewer['role_name'] ?: 'Technical Manager')),
        ];
    }

    private function auditPlanItems(int $programId): array
    {
        return $this->db->table('audit_plan_items')
            ->select('audit_plan_items.*, personnel.full_name AS auditor_name')
            ->join('audit_plans', 'audit_plans.id = audit_plan_items.audit_plan_id')
            ->join('audit_events', 'audit_events.id = audit_plans.audit_event_id')
            ->join('personnel', 'personnel.id = audit_plan_items.auditor_personnel_id', 'left')
            ->where('audit_events.audit_program_id', $programId)
            ->orderBy('audit_plan_items.audit_date', 'ASC')
            ->orderBy('audit_plan_items.start_time', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function auditPlanItemsForEvent(int $eventId): array
    {
        return $this->db->table('audit_plan_items')
            ->select('audit_plan_items.*, personnel.full_name AS auditor_name, auditor_appointments.appointment_role AS auditor_role')
            ->join('audit_plans', 'audit_plans.id = audit_plan_items.audit_plan_id')
            ->join('personnel', 'personnel.id = audit_plan_items.auditor_personnel_id', 'left')
            ->join('auditor_appointments', 'auditor_appointments.audit_event_id = audit_plans.audit_event_id AND auditor_appointments.personnel_id = audit_plan_items.auditor_personnel_id', 'left')
            ->where('audit_plans.audit_event_id', $eventId)
            ->orderBy('audit_plan_items.audit_date', 'ASC')
            ->orderBy('audit_plan_items.start_time', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function auditPlanForEvent(int $eventId): ?array
    {
        return $this->db->table('audit_plans')
            ->select('audit_plans.*, prepared.full_name AS prepared_by_name, approved.full_name AS approved_by_name')
            ->join('users prepared', 'prepared.id = audit_plans.prepared_by', 'left')
            ->join('users approved', 'approved.id = audit_plans.approved_by', 'left')
            ->where('audit_plans.audit_event_id', $eventId)
            ->orderBy('audit_plans.id', 'DESC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function appointmentsForEvent(int $eventId): array
    {
        return $this->db->table('auditor_appointments')
            ->select('auditor_appointments.*, personnel.full_name, personnel.user_id, users.full_name AS appointed_by_name')
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->join('users', 'users.id = auditor_appointments.appointed_by', 'left')
            ->where('auditor_appointments.audit_event_id', $eventId)
            ->orderBy('auditor_appointments.appointment_role', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function reportSectionsForEvent(int $tenantId, int $eventId): array
    {
        return $this->db->table('report_sections')
            ->select('report_sections.*, clause_library.clause_number, clause_library.clause_title, standards.code AS standard_code')
            ->join('report_drafts', 'report_drafts.id = report_sections.report_draft_id')
            ->join('clause_library', 'clause_library.id = report_sections.clause_library_id', 'left')
            ->join('standards', 'standards.id = clause_library.standard_id', 'left')
            ->where('report_drafts.tenant_id', $tenantId)
            ->where('report_drafts.audit_event_id', $eventId)
            ->orderBy('report_sections.sort_order', 'ASC')
            ->orderBy('report_sections.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function capasForEvent(int $tenantId, int $eventId): array
    {
        return $this->db->table('capas')
            ->select('capas.*, ncrs.ncr_number, ncrs.requirement AS ncr_requirement, ncrs.finding AS ncr_finding, ncrs.classification AS ncr_classification, clause_library.clause_number, clause_library.clause_title')
            ->join('ncrs', 'ncrs.id = capas.ncr_id')
            ->join('clause_library', 'clause_library.id = ncrs.clause_library_id', 'left')
            ->where('capas.tenant_id', $tenantId)
            ->where('ncrs.audit_event_id', $eventId)
            ->orderBy('capas.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function capasForEvents(int $tenantId, array $events): array
    {
        if ($events === []) {
            return [];
        }

        return $this->db->table('capas')
            ->select('capas.*, ncrs.ncr_number, ncrs.requirement AS ncr_requirement, ncrs.finding AS ncr_finding, ncrs.classification AS ncr_classification, audit_events.event_type, clause_library.clause_number, clause_library.clause_title')
            ->join('ncrs', 'ncrs.id = capas.ncr_id')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('clause_library', 'clause_library.id = ncrs.clause_library_id', 'left')
            ->where('capas.tenant_id', $tenantId)
            ->whereIn('ncrs.audit_event_id', array_column($events, 'id'))
            ->orderBy('capas.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function reportsForEvents(int $tenantId, array $events): array
    {
        return $this->db->table('report_drafts')
            ->select('report_drafts.*, audit_events.audit_number')
            ->join('audit_events', 'audit_events.id = report_drafts.audit_event_id')
            ->where('report_drafts.tenant_id', $tenantId)
            ->whereIn('report_drafts.audit_event_id', array_column($events, 'id'))
            ->orderBy('report_drafts.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function appointmentsForProgram(int $programId): array
    {
        return $this->db->table('auditor_appointments')
            ->select('auditor_appointments.*, personnel.full_name, audit_events.event_type, audit_events.audit_number')
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->join('audit_events', 'audit_events.id = auditor_appointments.audit_event_id')
            ->where('audit_events.audit_program_id', $programId)
            ->orderBy('audit_events.planned_start_date', 'ASC')
            ->orderBy('auditor_appointments.appointment_role', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function ncrsForEvents(int $tenantId, array $events): array
    {
        return $this->db->table('ncrs')
            ->select('ncrs.*, audit_events.event_type, standards.code AS standard_code, clause_library.clause_number, clause_library.clause_title')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('clause_library', 'clause_library.id = ncrs.clause_library_id', 'left')
            ->join('standards', 'standards.id = clause_library.standard_id', 'left')
            ->where('ncrs.tenant_id', $tenantId)
            ->whereIn('ncrs.audit_event_id', array_column($events, 'id'))
            ->orderBy('ncrs.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function certificateRows(int $tenantId, int $clientId): array
    {
        return $this->db->table('certificates')
            ->select('certificates.*, standards.code AS standard_code')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.tenant_id', $tenantId)
            ->where('certificates.client_id', $clientId)
            ->orderBy('certificates.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function qrDataUri(string $payload): string
    {
        return Builder::create()
            ->writer(new PngWriter())
            ->data($payload)
            ->size(120)
            ->margin(4)
            ->build()
            ->getDataUri();
    }

    private function qrPngPath(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }

        $directory = WRITEPATH . 'cache';
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . 'certificate-qr-' . sha1($payload) . '.png';
        if (is_file($path)) {
            return $path;
        }

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($payload)
            ->size(220)
            ->margin(5)
            ->build();

        file_put_contents($path, $result->getString());

        return $path;
    }

    private function documentTitle(string $key, string $company): string
    {
        if ($key === 'certification_application') {
            return 'Certification Application Form - ' . $company;
        }

        if ($key === 'application_review') {
            return 'Application Review Checklist Report - ' . $company;
        }

        if ($key === 'audit_program') {
            return 'Audit Program - ' . $company;
        }

        return ucwords(str_replace('_', ' ', $key)) . ' - ' . $company;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2);
    }

    private function mergeNonEmpty(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if (is_array($value) || trim((string) $value) !== '') {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    private function userDisplayName(mixed $userId): string
    {
        if ($userId === null || $userId === '') {
            return '';
        }

        $user = $this->db->table('users')
            ->select('full_name, email')
            ->where('id', (int) $userId)
            ->get(1)
            ->getRowArray();

        if ($user === null) {
            return '';
        }

        return trim((string) ($user['full_name'] ?: $user['email']));
    }

    private function yesNo(mixed $value): string
    {
        return (int) $value === 1 ? 'Yes' : 'No';
    }

    private function logoHtml(string $class = 'pdf-logo'): string
    {
        $logo = $this->logoDataUri();

        if ($logo === '') {
            return '<div class="brand-mark">QSI</div>';
        }

        return '<img class="' . esc($class, 'attr') . '" src="' . esc($logo, 'attr') . '" alt="QSI Canada Cert">';
    }

    private function logoDataUri(): string
    {
        return $this->assetDataUri('assets/img/qsi-logo.png');
    }

    private function stampHtml(string $class): string
    {
        $stamp = $this->assetDataUri('assets/img/qsi-stamp-ksa.png');

        return $stamp === ''
            ? $this->logoHtml($class)
            : '<img class="' . esc($class, 'attr') . '" src="' . esc($stamp, 'attr') . '" alt="QSI-Cert stamp">';
    }

    private function certificateBackgroundDataUri(): string
    {
        return $this->assetDataUri('assets/img/qsi-certificate-template.jpeg');
    }

    private function certificateSignatureHtml(string $relativePath): string
    {
        $signature = $this->assetDataUri($relativePath);

        return $signature === ''
            ? ''
            : '<img class="certificate-signature-image" src="' . esc($signature, 'attr') . '" alt="Signature">';
    }

    private function clientCertificateLogoHtml(string $relativePath): string
    {
        $path = $this->writableUploadPath($relativePath);
        $logo = $path === '' ? '' : $this->pathDataUri($path);

        return $logo === ''
            ? ''
            : '<div class="certificate-client-logo"><img src="' . esc($logo, 'attr') . '" alt="Client logo"></div>';
    }

    private function publicAssetPath(string $relativePath): string
    {
        $path = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        return is_file($path) ? $path : '';
    }

    private function assetDataUri(string $relativePath): string
    {
        $path = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($path)) {
            return '';
        }

        return $this->pathDataUri($path);
    }

    private function writableUploadPath(string $relativePath): string
    {
        $relativePath = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
        if ($relativePath === '' || ! str_starts_with($relativePath, 'uploads' . DIRECTORY_SEPARATOR . 'client-logos' . DIRECTORY_SEPARATOR)) {
            return '';
        }

        $path = realpath(WRITEPATH . $relativePath);
        $base = realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'client-logos');

        if ($path === false || $base === false || ! str_starts_with($path, $base) || ! is_file($path)) {
            return '';
        }

        return $path;
    }

    private function pathDataUri(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function css(): string
    {
        return '
            @page { margin: 38px 42px 46px; }
            * { box-sizing: border-box; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2933; line-height: 1.48; background: #fff; }
            .document-header { border: 0; padding: 0; margin-bottom: 16px; page-break-inside: avoid; }
            .document-header table { width: 100%; border-collapse: collapse; margin-bottom: 0; table-layout: fixed; border: 1.6px solid #0b3558; }
            .document-header td { border: 1px solid #b8cad8; padding: 8px 9px; vertical-align: middle; color: #123d70; }
            .brand-cell { width: 18%; text-align: center; color: #0b5f9e; font-weight: 700; background: #f4f8fb; }
            .brand-mark { font-size: 24px; line-height: 1; font-weight: 700; letter-spacing: .4px; }
            .brand-logo { display: block; width: 94px; max-height: 48px; object-fit: contain; background: #fff; padding: 4px; border-radius: 2px; }
            .pdf-logo { display: block; max-width: 108px; max-height: 54px; margin: 0 auto; object-fit: contain; }
            .title-cell { width: 54%; text-align: center; background: #ffffff; }
            .doc-title { font-size: 14.5px; font-weight: 700; color: #0b3558; line-height: 1.32; }
            .doc-subtitle { margin-top: 7px; color: #607080; font-family: DejaVu Serif, serif; font-size: 8.6px; font-weight: 700; letter-spacing: .85px; text-transform: uppercase; }
            .control-label { width: 15%; background: #f7fafc; color: #0b3558; font-size: 8.8px; font-weight: 700; text-align: left; white-space: nowrap; }
            .control-value { width: 13%; background: #ffffff; color: #123d70; font-size: 9.2px; font-weight: 700; text-align: left; white-space: nowrap; }
            .client { background: #f8fafc; border: 1px solid #d6e1ea; padding: 0; margin-bottom: 16px; page-break-inside: avoid; }
            .client table { margin-bottom: 0; }
            .client th { width: 18%; color: #0b3558; background: #eaf2f8; }
            h2 { font-size: 12.8px; margin: 18px 0 8px; color: #0b3558; page-break-after: avoid; border-bottom: 1.4px solid #d7a500; padding-bottom: 5px; font-weight: 700; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 14px; table-layout: fixed; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            th, td { border: 1px solid #d6e1ea; padding: 7px 8px; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
            th { background: #eaf2f8; text-align: left; font-weight: 700; color: #0f2638; }
            td { color: #243442; }
            tbody tr:nth-child(even) td { background: #fbfdff; }
            p { margin: 0 0 9px; overflow-wrap: anywhere; word-break: break-word; }
            .muted { color: #6b7785; font-style: italic; }
            .commercial-acceptance { margin-top: 8px; page-break-inside: avoid; table-layout: fixed; }
            .commercial-acceptance th { text-align: center; color: #123d70; font-size: 11px; background: #f8fafc; border: 1px solid #1f2933; padding: 8px; }
            .commercial-acceptance td { height: 138px; text-align: center; vertical-align: middle; border: 1px solid #1f2933; background: #fff; padding: 12px 14px; }
            .commercial-stamp-wrap { height: 72px; margin-bottom: 8px; }
            .commercial-stamp-image { max-width: 170px; max-height: 72px; object-fit: contain; }
            .commercial-name { margin-top: 8px; font-size: 11px; font-weight: 700; color: #111827; }
            .commercial-date { margin-top: 9px; font-size: 10.5px; font-weight: 700; color: #111827; }
            .commercial-stamp { display: inline-block; margin-bottom: 12px; padding: 4px 10px; border: 2px solid #d92929; color: #d92929; font-weight: 800; font-size: 11px; letter-spacing: .8px; transform: rotate(-10deg); }
            .commercial-stamp.pending { border-color: #64748b; color: #64748b; transform: none; }
            .commercial-obligations h3 { color: #0b3558; font-size: 11.2px; margin: 11px 0 4px; border-bottom: 0; padding: 0; }
            .commercial-obligations p { margin-bottom: 9px; }
            .commercial-note { color: #123d70; font-style: italic; font-size: 10.4px; line-height: 1.45; page-break-inside: avoid; }
            .commercial-note ul { margin: 6px 0 11px 24px; color: #0033cc; font-style: normal; font-weight: 700; }
            .commercial-note li { margin: 2px 0; }
            .annexure-link { color: #0033cc; text-decoration: underline; font-weight: 700; }
            .commercial-contact { table-layout: fixed; margin-top: 8px; page-break-inside: avoid; }
            .commercial-contact td { background: #dbe7f5; border: 1px solid #1f2933; color: #123d70; font-size: 11px; font-weight: 700; padding: 12px; }
            .qr { margin-top: 8px; font-size: 9px; color: #56616f; }
            .qr img { width: 80px; height: 80px; }
            .detail-record { page-break-inside: avoid; margin-bottom: 12px; }
            .detail-record-title { background: #0b3558; color: #fff; font-weight: 700; padding: 7px 9px; border: 1px solid #0b3558; }
            .detail-table { margin-bottom: 0; }
            .detail-table th { width: 27%; background: #eef5fa; color: #0b3558; }
            .detail-table td { width: 74%; }
            .report-note { border: 1px solid #c8d7e3; margin: 0 0 12px; page-break-inside: avoid; background: #fff; }
            .report-note-heading { background: #0b3558; color: #fff; font-weight: 700; padding: 7px 9px; font-size: 10.5px; line-height: 1.35; }
            .report-note-meta { margin: 0; table-layout: fixed; }
            .report-note-meta th { width: 12%; background: #eaf2f8; color: #0b3558; font-size: 8.8px; padding: 5px 6px; }
            .report-note-meta td { width: 21%; font-size: 8.8px; padding: 5px 6px; color: #334155; }
            .report-note-body { padding: 9px 10px 10px; line-height: 1.55; color: #243442; }
            .report-note-label { color: #0b3558; font-weight: 700; margin-bottom: 5px; font-size: 9.5px; }
            footer { position: fixed; left: 42px; right: 42px; bottom: 18px; border-top: 1px solid #c8d7e3; padding-top: 7px; color: #607080; font-size: 8.6px; }
            footer span:first-child { display: inline-block; width: 78%; }
            .page-number { display: inline-block; width: 20%; text-align: right; }
            .page-number:after { content: counter(page); }
        ';
    }

    private function certificateCss(): string
    {
        return '
            @page { margin: 0; }
            * { box-sizing: border-box; }
            body { margin: 0; padding: 0; font-family: DejaVu Sans, Arial, sans-serif; color: #111827; background: #fff; }
            .certificate-page { position: relative; width: 210mm; height: 297mm; background-repeat: no-repeat; background-position: 0 0; background-size: 210mm 297mm; overflow: hidden; }
            .certificate-content { position: absolute; left: 56mm; right: 17mm; top: 27mm; bottom: 0; text-align: left; }
            .certificate-client-logo { position: absolute; right: 0; top: 9mm; width: 30mm; height: 17mm; text-align: right; }
            .certificate-client-logo img { max-width: 30mm; max-height: 17mm; object-fit: contain; }
            .certificate-intro { font-size: 11.2pt; margin-bottom: 10mm; }
            .certificate-company { font-size: 22pt; line-height: 1.12; font-weight: 700; max-width: 125mm; margin-bottom: 3mm; }
            .certificate-company.company-medium { font-size: 19.5pt; line-height: 1.1; }
            .certificate-company.company-long { font-size: 17.2pt; line-height: 1.08; }
            .certificate-address { font-size: 9.3pt; line-height: 1.22; max-width: 112mm; margin-bottom: 10mm; }
            .certificate-compliance { font-size: 11.2pt; margin-bottom: 8mm; }
            .certificate-standard { font-size: 27pt; line-height: 1; margin-bottom: 4.5mm; font-weight: 400; letter-spacing: 0.2mm; }
            .certificate-description { font-size: 8.8pt; line-height: 1.25; font-weight: 700; font-style: italic; max-width: 130mm; margin-bottom: 6.5mm; }
            .certificate-applicable { font-size: 13pt; margin-bottom: 4mm; }
            .certificate-scope { font-size: 14pt; line-height: 1.14; font-weight: 700; max-width: 134mm; margin-bottom: 4.2mm; }
            .certificate-scope.scope-medium { font-size: 12.6pt; line-height: 1.12; }
            .certificate-scope.scope-long { font-size: 11.2pt; line-height: 1.1; }
            .certificate-dates { width: 137mm; table-layout: fixed; margin: 0 0 3.2mm; border-collapse: collapse; border-top: 0.35mm solid #1f2933; border-bottom: 0.35mm solid #1f2933; }
            .certificate-dates .date-label { width: 38mm; }
            .certificate-dates .date-value { width: 30.5mm; }
            .certificate-dates th, .certificate-dates td { border: 0; padding: 0.82mm 1.2mm; font-size: 7.45pt; color: #111827; background: transparent !important; line-height: 1.12; vertical-align: middle; }
            .certificate-dates tbody tr:nth-child(even) td { background: transparent !important; }
            .certificate-dates th { font-weight: 700; text-align: left; white-space: nowrap; }
            .certificate-dates td { text-align: right; white-space: nowrap; }
            .certificate-dates .certificate-number-cell { color: #1c6d8a; font-weight: 700; font-size: 7.25pt; letter-spacing: 0.05mm; }
            .certificate-validity-note { font-size: 8pt; line-height: 1.24; font-style: italic; max-width: 134mm; margin-bottom: 3.5mm; }
            .certificate-signatures { width: 90mm; table-layout: fixed; border-collapse: collapse; margin: 0 0 3.5mm; }
            .certificate-signatures td { border: 0 !important; background: transparent !important; padding: 0 6mm 0 0; text-align: center; font-size: 8.6pt; vertical-align: top; }
            .signature-line { height: 11mm; border-bottom: 0.25mm solid #1f2933; margin-bottom: 1.2mm; text-align: center; }
            .certificate-signature-image { max-width: 39mm; max-height: 10.5mm; display: inline-block; vertical-align: bottom; }
            .certificate-verification-block { width: 52mm; margin-top: 1mm; }
            .certificate-verification-block img { width: 22mm; height: 22mm; display: block; margin-bottom: 2.1mm; }
            .certificate-validity { font-size: 7.6pt; margin-bottom: 1mm; line-height: 1.16; }
            .certificate-validity strong { color: #1c6d8a; font-size: 8.1pt; }
            .certificate-verify { font-size: 6.7pt; line-height: 1.18; color: #1f2933; }
        ';
    }

    private function certificationApplicationCss(): string
    {
        return '
            @page { margin: 36px 40px 62px; }
            body { color: #1f2933; }
            h2 { color: #0b3558; border-bottom: 1.4px solid #d7a500; padding-bottom: 5px; }
            .f25-header { border: 0; padding: 0; margin-bottom: 14px; }
            .f25-header table { border: 1.5px solid #0b3558; margin-bottom: 14px; }
            .f25-header td { border: 1px solid #b8cad8; padding: 8px; vertical-align: middle; }
            .f25-logo { width: 17%; text-align: center; color: #0b5f9e; font-weight: 700; background: #f4f8fb; }
            .f25-logo-text { font-size: 24px; line-height: 1; }
            .f25-title { width: 50%; text-align: center; font-size: 17px; font-weight: 700; color: #0b3558; }
            .f25-header td:not(.f25-logo):not(.f25-title):nth-child(3),
            .f25-header tr:not(:first-child) td:first-child { background: #f7fafc; color: #0b3558; font-weight: 700; }
            .f25-header td:last-child { color: #123d70; font-weight: 700; }
            .client { background: #f8fafc; border-color: #d6e1ea; }
            th { background: #eaf2f8; color: #0f2638; border-color: #b8cad8; }
            td { border-color: #d6e1ea; color: #243442; }
            tbody tr:nth-child(even) td { background: #f7fafc; }
            footer.f25-page-footer { left: 40px; right: 40px; color: #607080; border-top: 1px solid #c8d7e3; text-align: center; }
            footer.f25-page-footer span:first-child { width: auto; }
            footer.f25-page-footer .page-number { width: auto; text-align: center; }
        ';
    }

    private function auditorAppointmentCss(): string
    {
        return '
            @page { margin: 36px 36px 120px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2933; }
            h2 { color: #0b3558; font-size: 13px; margin: 18px 0 8px; page-break-after: avoid; border-bottom: 1.4px solid #d7a500; padding-bottom: 5px; }
            h3 { color: #0b3558; font-size: 11.5px; margin: 14px 0 8px; page-break-after: avoid; }
            p { line-height: 1.35; margin: 7px 0 12px; }
            .f30-header { border: 0; padding: 0; margin-bottom: 18px; }
            .f30-header table { border: 1.6px solid #0b3558; margin-bottom: 0; table-layout: fixed; }
            .f30-header td { border: 1px solid #b8cad8; padding: 8px 9px; vertical-align: middle; color: #123d70; }
            .f30-logo { width: 18%; text-align: center; color: #0b5f9e; font-weight: 700; background: #f4f8fb; }
            .f30-logo .pdf-logo { width: 90px; max-height: 52px; }
            .f30-logo-text { font-size: 24px; line-height: 1; }
            .f30-title { width: 54%; text-align: center; font-size: 17px; line-height: 1.28; color: #0b3558; background: #ffffff; font-weight: 700; }
            .f30-title div { margin-top: 7px; color: #607080; font-family: DejaVu Serif, serif; font-size: 8.6px; font-weight: 700; text-transform: uppercase; letter-spacing: .85px; }
            .f30-control-label { width: 15%; background: #f7fafc; color: #0b3558; font-size: 8.8px; font-weight: 700; text-align: left; white-space: nowrap; }
            .f30-control-value { width: 13%; background: #ffffff; color: #123d70; font-size: 9.2px; font-weight: 700; text-align: left; white-space: nowrap; }
            .f30-table th { width: 40%; background: #eaf2f8; color: #0f2638; border: 1px solid #b8cad8; padding: 7px 8px; font-weight: 700; }
            .f30-table td { width: 60%; border: 1px solid #d6e1ea; padding: 7px 8px; color: #243442; }
            .f30-table tbody tr:nth-child(even) td { background: #f7fafc; }
            .f30-grid th, .f30-grid td { border: 1px solid #b8cad8; padding: 7px 8px; vertical-align: top; }
            .f30-grid th { background: #eaf2f8; color: #0f2638; text-align: left; }
            .f30-grid tbody tr:nth-child(even) td { background: #f7fafc; }
        ';
    }

    private function auditPlanCss(): string
    {
        return '
            @page { margin: 32px 30px 42px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.4px; color: #1f2933; }
            h2 { color: #0b3558; font-size: 12.6px; margin: 15px 0 7px; page-break-after: avoid; border-bottom: 1.4px solid #d7a500; padding-bottom: 4px; }
            p { line-height: 1.35; margin: 6px 0 10px; }
            .f31-header { border: 0; padding: 0; margin-bottom: 12px; }
            .f31-header table { border: 1.6px solid #0b3558; margin-bottom: 0; table-layout: fixed; }
            .f31-header td { border: 1px solid #b8cad8; padding: 8px 9px; vertical-align: middle; color: #123d70; }
            .f31-logo { width: 18%; text-align: center; color: #0b5f9e; font-weight: 700; background: #f4f8fb; }
            .f31-logo .pdf-logo { width: 90px; max-height: 52px; }
            .f31-logo-text { font-size: 24px; line-height: 1; }
            .f31-title { width: 54%; text-align: center; font-size: 17px; font-weight: 700; color: #0b3558; line-height: 1.28; background: #ffffff; }
            .f31-title span { display: block; margin-top: 4px; font-size: 12.5px; color: #123d70; }
            .f31-title div { margin-top: 7px; color: #607080; font-family: DejaVu Serif, serif; font-size: 8.6px; font-weight: 700; text-transform: uppercase; letter-spacing: .85px; }
            .f31-control-label { width: 15%; background: #f7fafc; color: #0b3558; font-size: 8.8px; font-weight: 700; text-align: left; white-space: nowrap; }
            .f31-control-value { width: 13%; background: #ffffff; color: #123d70; font-size: 9.2px; font-weight: 700; text-align: left; white-space: nowrap; }
            .client { background: #f8fafc; border: 1px solid #d6e1ea; padding: 8px; margin-bottom: 12px; }
            .f31-table th { width: 32%; background: #eaf2f8; color: #0f2638; border: 1px solid #b8cad8; padding: 6px 7px; font-weight: 700; }
            .f31-table td { width: 68%; border: 1px solid #b8cad8; padding: 6px 7px; }
            .f31-grid th, .f31-grid td { border: 1px solid #b8cad8; padding: 5px 6px; vertical-align: top; }
            .f31-grid th { background: #eaf2f8; color: #0f2638; text-align: left; font-weight: 700; }
            .f31-grid thead { display: table-header-group; }
            .f31-grid tr { page-break-inside: avoid; page-break-after: auto; }
            .f31-timetable th, .f31-timetable td { font-size: 8.8px; }
            .f31-note { color: #334155; }
        ';
    }

    private function contractCss(): string
    {
        return '
            @page { margin: 36px 40px 88px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2933; }
            h2 { color: #0b3558; font-size: 12.8px; margin: 17px 0 8px; page-break-after: avoid; border-bottom: 1.4px solid #d7a500; padding-bottom: 5px; }
            .f27-header { border: 0; padding: 0; margin-bottom: 14px; }
            .f27-header table { border: 1.5px solid #0b3558; margin-bottom: 12px; }
            .f27-header td { border: 1px solid #b8cad8; padding: 8px; vertical-align: middle; }
            .f27-logo { width: 17%; text-align: center; color: #0b5f9e; font-weight: 700; background: #f4f8fb; }
            .f27-logo-text { font-size: 24px; line-height: 1; }
            .f27-title { width: 50%; text-align: center; font-size: 17px; font-weight: 700; color: #0a3765; }
            .f27-control { margin-bottom: 0; }
            .f27-control th { width: 48%; background: #eaf2f8; color: #0f2638; }
            .f27-control th, .f27-control td { border: 1px solid #b8cad8; padding: 5px 6px; }
            .client { background: #f8fafc; border: 1px solid #d6e1ea; padding: 10px; margin-bottom: 14px; }
            footer { left: 40px; right: 40px; color: #607080; border-top: 1px solid #c8d7e3; }
        ';
    }

    private function commercialDocumentCss(): string
    {
        return '
            @page { margin: 34px 42px 42px; }
            @page:first { margin: 0; }
            body { font-size: 10.2px; margin: 0; }
            .commercial-cover { page-break-after: always; width: 210mm; height: 297mm; position: relative; padding: 0; background: #ffffff; overflow: hidden; }
            .commercial-cover:before { content: ""; position: absolute; left: 0; bottom: 0; width: 210mm; height: 23mm; background: #082b4d; z-index: 1; }
            .commercial-cover:after { content: ""; position: absolute; left: 116mm; top: 0; width: 18mm; height: 244mm; background: #ffffff; transform: skewX(-24deg); z-index: 2; }
            .cover-city { position: absolute; z-index: 0; right: 0; top: 0; width: 84mm; height: 297mm; overflow: hidden; background: #0b3558; }
            .cover-city-img { display: block; width: 84mm; height: 297mm; }
            .cover-city-fallback { width: 84mm; height: 297mm; background: #0b3558; }
            .cover-footer { position: absolute; z-index: 3; left: 0; right: 0; bottom: 0; height: 23mm; color: #ffffff; font-size: 12.5px; letter-spacing: .4px; padding-top: 9mm; text-align: center; }
            .cover-footer span { display: inline-block; margin: 0 14mm; }
            .cover-logo { position: absolute; z-index: 4; left: 13mm; top: 15mm; }
            .cover-logo-img { display: block; width: 74mm; max-height: 36mm; object-fit: contain; }
            .cover-company { position: absolute; z-index: 4; left: 13mm; top: 58mm; color: #0b3558; font-size: 24px; font-weight: 800; letter-spacing: 1px; }
            .cover-rule { position: absolute; z-index: 4; left: 13mm; top: 72mm; width: 34mm; height: 1.8px; background: #e11f27; }
            .cover-title { position: absolute; z-index: 4; left: 13mm; top: 86mm; color: #0b3558; font-size: 54px; line-height: .95; font-weight: 900; letter-spacing: .5px; }
            .cover-subtitle { position: absolute; z-index: 4; left: 13mm; top: 117mm; width: 92mm; color: #253241; font-size: 23px; line-height: 1.15; }
            .cover-tagline { position: absolute; z-index: 4; left: 13mm; top: 139mm; width: 92mm; border-left: 2px solid #e11f27; padding-left: 8px; color: #253241; font-size: 14.5px; line-height: 1.25; }
            .cover-tagline strong { color: #e11f27; }
            .cover-badges { position: absolute; z-index: 4; left: 0; top: 164mm; width: 124mm; height: 38mm; margin: 0; table-layout: fixed; background: #082b4d; color: #ffffff; }
            .cover-badges td { border: 0; border-right: 1px solid rgba(255,255,255,.45); text-align: center; vertical-align: middle; padding: 5px 7px; }
            .cover-badges td:last-child { border-right: 0; }
            .cover-badge-img { display: block; width: 16.5mm; height: 16.5mm; margin: 0 auto 4px; }
            .cover-badge-fallback { display: block; width: 15mm; height: 15mm; line-height: 15mm; margin: 0 auto 4px; border: 1.5px solid #ffffff; border-radius: 50%; background: #e11f27; color: #ffffff; font-size: 9px; font-weight: 900; }
            .cover-badges b { display: block; color: #ffffff; font-size: 8.9px; line-height: 1.18; text-transform: uppercase; letter-spacing: .2px; }
            .cover-badges small { display: block; margin-top: 3px; color: #dce8f4; font-size: 7.2px; line-height: 1.15; }
            .cover-label { position: absolute; z-index: 4; left: 13mm; top: 208mm; color: #0b3558; text-transform: uppercase; letter-spacing: .7px; font-size: 12px; font-weight: 800; border-bottom: 1.6px solid #e11f27; padding-bottom: 3px; width: 42mm; }
            .cover-info { position: absolute; z-index: 4; left: 13mm; top: 223mm; width: 101mm; table-layout: fixed; margin: 0; }
            .cover-info th { width: 34%; background: #0b3558; color: #ffffff; border: 1px solid #0b3558; padding: 5px 7px; font-size: 8.1px; text-transform: uppercase; letter-spacing: .2px; }
            .cover-info td { background: #f7fafc; color: #123d70; border: 1px solid #c4d2df; padding: 5px 7px; font-size: 8.3px; line-height: 1.25; font-weight: 700; }
            .commercial-body { page-break-before: auto; }
            .commercial-body h2:first-child { margin-top: 0; }
            .commercial-body .client { margin-bottom: 18px; }
            .commercial-doc-header { border: 0; padding: 0; margin: 0 0 16px; page-break-inside: avoid; }
            .commercial-doc-header table { border: 1.6px solid #0b3558; margin-bottom: 0; table-layout: fixed; }
            .commercial-doc-header td { border: 1px solid #b8cad8; padding: 8px 9px; vertical-align: middle; color: #123d70; }
            .commercial-doc-logo { width: 18%; background: #f4f8fb; text-align: center; }
            .commercial-doc-logo .pdf-logo { width: 90px; max-height: 52px; }
            .commercial-doc-title { width: 46%; text-align: center; font-size: 17px; line-height: 1.28; color: #0b3558; background: #ffffff; font-weight: 700; }
            .commercial-doc-title div { margin-top: 7px; color: #607080; font-family: DejaVu Serif, serif; font-size: 8.6px; font-weight: 700; text-transform: uppercase; letter-spacing: .85px; }
            .commercial-doc-label { width: 16%; background: #f7fafc; color: #0b3558; font-size: 8.8px; font-weight: 700; text-align: left; white-space: nowrap; }
            .commercial-doc-value { width: 20%; background: #ffffff; color: #123d70; font-size: 9.2px; font-weight: 700; text-align: left; white-space: nowrap; }
            .commercial-body h2 { border-bottom: 1.4px solid #d7a500; }
            .commercial-body th { background: #eaf2f8; color: #0f2638; border-color: #b8cad8; }
            .commercial-body td { border-color: #d6e1ea; color: #243442; }
            .commercial-body tbody tr:nth-child(even) td { background: #f7fafc; }
            .commercial-body .commercial-contact td { font-size: 10.4px; }
        ';
    }

    private function auditProgramCss(): string
    {
        return '
            @page { margin: 30px 34px 38px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.6px; color: #1f2933; }
            h2 { color: #0b3558; font-size: 12.4px; margin: 16px 0 7px; page-break-after: avoid; border-bottom: 1.4px solid #d7a500; padding-bottom: 5px; }
            table { page-break-inside: auto; border-collapse: collapse; width: 100%; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            th, td { vertical-align: top; }
            .center { text-align: center; }
            .nowrap { white-space: nowrap; }
            .f42-header { border: 0; padding: 0; margin-bottom: 12px; }
            .f42-header-table { border: 1.6px solid #0b3558; margin-bottom: 0; table-layout: fixed; }
            .f42-header-table td { border: 1px solid #b8cad8; padding: 8px 9px; vertical-align: middle; color: #123d70; }
            .f42-logo { width: 18%; background: #f4f8fb; text-align: center; }
            .f42-logo .pdf-logo { width: 90px; max-height: 52px; }
            .f42-logo-text { font-size: 24px; line-height: 1; }
            .f42-title { width: 54%; text-align: center; font-size: 17px; line-height: 1.28; color: #0b3558; background: #ffffff; font-weight: 700; }
            .f42-title div { margin-top: 7px; color: #607080; font-family: DejaVu Serif, serif; font-size: 8.6px; font-weight: 700; text-transform: uppercase; letter-spacing: .85px; }
            .f42-control-label { width: 15%; background: #f7fafc; color: #0b3558; font-size: 8.8px; font-weight: 700; text-align: left; white-space: nowrap; }
            .f42-control-value { width: 13%; background: #ffffff; color: #123d70; font-size: 9.2px; font-weight: 700; text-align: left; white-space: nowrap; }
            .f42-client { background: #f8fafc; border: 1px solid #d6e1ea; padding: 0; margin-bottom: 13px; }
            .f42-client th { width: 14%; background: #eaf2f8; color: #0f2638; border: 1px solid #c8d7e3; padding: 6px 7px; text-align: left; }
            .f42-client td { border: 1px solid #d6e1ea; padding: 6px 7px; }
            .f42-table { border: 1px solid #c4d2df; margin-bottom: 12px; table-layout: fixed; }
            .f42-table th { background: #0b3558; color: #ffffff; border: 1px solid #0b3558; padding: 6px 6px; font-size: 8.6px; text-align: left; }
            .f42-table td { border: 1px solid #d6e1ea; padding: 6px 6px; line-height: 1.32; }
            .f42-table tbody tr:nth-child(even) td { background: #f7fafc; }
            .f42-cycle th:nth-child(1) { width: 15%; }
            .f42-cycle th:nth-child(2) { width: 17%; }
            .f42-cycle th:nth-child(3) { width: 15%; }
            .f42-cycle th:nth-child(4) { width: 15%; }
            .f42-cycle th:nth-child(5) { width: 8%; text-align: center; }
            .f42-cycle th:nth-child(6) { width: 20%; }
            .f42-cycle th:nth-child(7) { width: 10%; text-align: center; }
            .f42-coverage { font-size: 8.4px; }
            .f42-coverage th:nth-child(1) { width: 13%; }
            .f42-coverage th:nth-child(2) { width: 11%; text-align: center; }
            .f42-coverage th:nth-child(3) { width: 40%; }
            .f42-coverage th:nth-child(n+4) { width: 7.2%; text-align: center; }
            .f42-matrix th:first-child { width: 36%; }
            .f42-matrix th:not(:first-child) { text-align: center; }
        ';
    }

    private function applicationReviewCss(): string
    {
        return '
            @page { margin: 36px 40px 62px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2933; }
            h2 { color: #0b3558; font-size: 12.8px; margin: 17px 0 8px; page-break-after: avoid; border-bottom: 1.4px solid #d7a500; padding-bottom: 5px; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .f28-header table { border: 1.5px solid #0b3558; margin-bottom: 18px; }
            .f28-header td { border: 1px solid #b8cad8; padding: 8px; vertical-align: middle; }
            .f28-logo { width: 18%; text-align: center; color: #0b5f9e; font-weight: 700; font-size: 13px; background: #f4f8fb; }
            .f28-logo-text { font-size: 28px; line-height: 1; }
            .f28-title { width: 54%; text-align: center; font-size: 18px; font-weight: 700; line-height: 1.15; color: #0b3558; }
            .f28-cert { background: #eaf2f8; text-align: center; color: #0b3558; font-size: 15px; font-weight: 700; letter-spacing: 1px; }
            .f28-header td:not(.f28-logo):not(.f28-title):nth-child(3),
            .f28-header tr:not(:first-child) td:first-child { background: #f7fafc; color: #0b3558; font-weight: 700; }
            .f28-header td:last-child { color: #123d70; font-weight: 700; }
            .f28-table th { width: 40%; background: #eaf2f8; color: #0f2638; border: 1px solid #b8cad8; padding: 7px 8px; font-weight: 700; }
            .f28-table td { width: 60%; border: 1px solid #d6e1ea; padding: 7px 8px; font-weight: 600; color: #243442; }
            .f28-table tbody tr:nth-child(even) td { background: #f7fafc; }
            .f28-man-days th, .f28-man-days td { border: 1px solid #b8cad8; padding: 6px; text-align: center; }
            .f28-man-days th { background: #eaf2f8; color: #0f2638; }
            .f28-man-days tbody tr:nth-child(even) td { background: #f7fafc; }
            .f28-note { font-size: 10px; margin-top: 18px; }
            footer.f28-page-footer { position: fixed; left: 40px; right: 40px; bottom: 16px; border-top: 1px solid #c8d7e3; padding-top: 6px; color: #607080; font-size: 8.6px; text-align: center; }
            footer.f28-page-footer span:first-child { width: auto; }
            footer.f28-page-footer .page-number { width: auto; text-align: center; }
        ';
    }
}

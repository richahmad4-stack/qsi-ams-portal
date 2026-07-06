<?php

namespace App\Services;

use App\Models\GeneratedDocumentModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class DocumentGeneratorService
{
    private BaseConnection $db;
    private GeneratedDocumentModel $documents;
    private AuditReportNarrativeService $narratives;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->documents = new GeneratedDocumentModel();
        $this->narratives = new AuditReportNarrativeService();
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
        $certificate = $this->db->table('certificates')
            ->select('certificates.*, clients.company, clients.legal_name, clients.address, clients.city, clients.country, standards.code AS standard_code, standards.name AS standard_name')
            ->join('clients', 'clients.id = certificates.client_id')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.tenant_id', $tenantId)
            ->where('certificates.id', $certificateId)
            ->get(1)
            ->getRowArray();

        if ($certificate === null) {
            throw new \RuntimeException('Certificate not found.');
        }

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
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function renderHtml(string $documentKey, string $title, array $client, array $data): string
    {
        if ($documentKey === 'certification_application') {
            return $this->certificationApplicationHtml($title, $client, $data);
        }

        if ($documentKey === 'application_review') {
            return $this->applicationReviewHtml($title, $client, $data);
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

        return $this->baseHtml($title, $client, $sections);
    }

    private function baseHtml(string $title, array $client, array $sections): string
    {
        $body = '';
        foreach ($sections as [$heading, $content]) {
            $body .= '<h2>' . esc($heading) . '</h2>' . $content;
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . '</style></head><body>'
            . '<header><div class="brand">QSI AMS</div><div class="doc-title">' . esc($title) . '</div></header>'
            . '<section class="client"><strong>Client:</strong> ' . esc($client['company']) . '<br><strong>Scope:</strong> ' . esc((string) ($client['scope'] ?? '')) . '</section>'
            . $body
            . '<footer>Generated on ' . esc(date('Y-m-d H:i')) . ' | Controlled document generated by QSI AMS</footer>'
            . '</body></html>';
    }

    private function certificateHtml(array $certificate): string
    {
        $qr = $this->qrDataUri((string) $certificate['qr_payload']);

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->certificateCss() . '</style></head><body>'
            . '<div class="certificate-shell">'
            . '<div class="brand">QSI AMS</div>'
            . '<h1>Certificate of Registration</h1>'
            . '<p class="certifies">This is to certify that</p>'
            . '<h2>' . esc($certificate['company']) . '</h2>'
            . '<p>' . esc(trim((string) ($certificate['address'] . ', ' . $certificate['city'] . ', ' . $certificate['country']))) . '</p>'
            . '<p class="certifies">has been assessed and registered for</p>'
            . '<h3>' . esc($certificate['standard_code']) . '</h3>'
            . '<div class="scope">' . nl2br(esc($certificate['scope'])) . '</div>'
            . '<table class="meta"><tr><th>Certificate No.</th><td>' . esc($certificate['certificate_number']) . '</td><th>Issue Date</th><td>' . esc($certificate['issue_date']) . '</td></tr>'
            . '<tr><th>Initial Date</th><td>' . esc((string) $certificate['initial_certification_date']) . '</td><th>Expiry Date</th><td>' . esc($certificate['expiry_date']) . '</td></tr></table>'
            . '<div class="qr"><img src="' . esc($qr, 'attr') . '"><div>Scan to verify<br>' . esc($certificate['public_slug']) . '</div></div>'
            . '<footer>Generated on ' . esc(date('Y-m-d H:i')) . ' | Verification URL: ' . esc($certificate['qr_payload']) . '</footer>'
            . '</div></body></html>';
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
            ['Certification Process and Obligations', '<p>' . nl2br(esc((string) ($payload['certification_process_obligations'] ?? ''))) . '</p>'],
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
        ];
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
            'certification_process_obligations' => 'QSI-Cert delivers certification services in accordance with accreditation requirements and applicable standards. The client shall provide accurate information, maintain compliance with certification requirements, allow access for audit activities, and notify QSI-Cert of significant changes affecting certification.',
            'payment_terms' => "Certification Audit Fee:\n50% payable upon signing the contract.\n50% payable before certificate issue.\n\nSurveillance Audit Fee:\n100% payable one month in advance of the scheduled surveillance audit.\n\nAdditional Fees:\nAdditional services, extra audit days, travel and accommodation are payable as agreed.",
            'certification_audit_includes' => "Audit planning and preparation.\nStage 1 document/readiness review.\nStage 2 on-site implementation audit.\nAudit reporting and technical review.\nCertification decision processing.\nCertificate issue after approval.",
            'surveillance_audit_includes' => "Audit planning and preparation.\nReview of changes since previous audit.\nSurveillance audit execution and reporting.\nFollow-up of previous findings and certification conditions.\nTechnical review and maintain-certification decision where applicable.",
            'additional_a4_copy_fee' => '50 USD',
            'certificate_reissue_fee' => '150 USD',
            'extraordinary_audit_1_fee' => '850 USD',
            'extraordinary_audit_2_fee' => '925 USD',
            'vat_invoice_terms' => "VAT will be applied according to applicable regulations. Invoices may be sent electronically by email.\nThe proposal is valid until the stated validity date and is subject to changes in scope, sites, employees, risk, standards or applicable requirements.",
            'stage1_activity' => 'Stage 1 verifies documentation, scope, site readiness, internal audit, management review, legal/regulatory awareness and preparedness for Stage 2.',
            'stage2_activity' => 'Stage 2 verifies implementation and effectiveness of the management system against the applicable standard and certification scope.',
            'certificate_issuance' => 'Certificate issue is subject to successful audit completion, closure of applicable nonconformities, technical review, certification decision and final approval.',
            'surveillance_activity' => 'Surveillance audits verify continued conformity, changes, previous findings, internal audit, management review, objectives, operational controls and use of certification marks.',
            'audit_time_reference' => 'Audit time is calculated from the application review considering selected standard(s), effective personnel, HACCP plans/processes where applicable, sites, shifts, risk and applicable IAF/ISO rules.',
        ];

        if (($stored['total_audit_days'] ?? '') === '' && ($stored['days_allotted'] ?? '') !== '') {
            $stored['total_audit_days'] = $stored['days_allotted'];
        }

        return $this->mergeNonEmpty($defaults, $stored);
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
        $qr = $this->qrDataUri(site_url('workflow/certification/' . ($application['client_id'] ?? 0) . '/application'));
        $sections = [[
            'Selected Standards',
            $this->recordTable($applicationData['selected_standards'] ?? [], ['standard_code']),
        ]];

        foreach ($answers as $section => $rows) {
            if (in_array((string) $section, $this->excludedCertificationApplicationSections(), true)) {
                continue;
            }

            $sections[] = [$section, $this->recordTable($rows, ['question_text', 'answer_text'])];
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
            'Reviewed At' => $application['reviewed_at'] ?? '',
        ])];
        $sections[] = ['QR Code', '<div class="qr"><img src="' . esc($qr, 'attr') . '"><div>Application record QR</div></div>'];

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
            . '<tr><td class="f25-logo" rowspan="3"><div class="f25-logo-text">QSI</div><div>AMS</div></td><td class="f25-title" rowspan="3">' . esc($title) . '</td><td>Document Number</td><td>' . esc($application['document_number'] ?? 'F 25') . '</td></tr>'
            . '<tr><td>Revision</td><td>' . esc($application['revision_number'] ?? '1') . '</td></tr>'
            . '<tr><td>Issue / Issue Date</td><td>' . esc(($application['issue_number'] ?? '2') . ' / ' . ($application['issue_date'] ?? '')) . '</td></tr>'
            . '</table>'
            . '</header>'
            . '<section class="client"><strong>Client:</strong> ' . esc($client['company']) . '<br><strong>Scope:</strong> ' . esc((string) ($client['scope'] ?? '')) . '<br><strong>Application:</strong> ' . esc($application['application_number'] ?? 'Not created') . ' | <strong>Status:</strong> ' . esc($application['status'] ?? 'draft') . ' | <strong>Submitted:</strong> ' . esc($application['submitted_at'] ?? '') . '</section>'
            . $body
            . '<footer>Document No: ' . esc($application['document_number'] ?? 'F 25') . ' | Revision: ' . esc($application['revision_number'] ?? '1') . ' | Issue: ' . esc($application['issue_number'] ?? '2') . ' | Issue Date: ' . esc($application['issue_date'] ?? '') . '</footer>'
            . '</body></html>';
    }

    private function excludedCertificationApplicationSections(): array
    {
        return [
            'Supporting Documents',
            'Declaration',
            'HACCP Specific Questions',
        ];
    }

    private function applicationReviewSections(array $client, array $data): array
    {
        $review = $data['application_review'] ?? [];
        $payload = json_decode((string) ($review['review_payload'] ?? ''), true) ?: [];
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
        $payload = json_decode((string) ($review['review_payload'] ?? ''), true) ?: [];
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
            . '<td class="f28-logo" rowspan="4"><div class="f28-logo-text">QSI</div><div>AMS</div></td>'
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
            . '<footer>Document No: ' . esc($review['document_number'] ?? 'F 28') . ' | Revision No: ' . esc($review['revision_number'] ?? '4') . ' | Issue No: ' . esc($review['issue_number'] ?? '2') . ' | Date: ' . esc($review['document_date'] ?? '2025-02-01') . '</footer>'
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
            . '<header class="f30-header"><table><tr>'
            . '<td class="f30-logo"><div class="f30-logo-text">QSI</div><div>CERT</div></td>'
            . '<td class="f30-title"><div>AUDITOR APPOINTMENT</div><div class="f30-cert">QSI - CERT</div></td>'
            . '<td><table class="f30-control">'
            . '<tr><th>Document No.</th><td>F 30_app</td></tr>'
            . '<tr><th>Revision No.</th><td>2</td></tr>'
            . '<tr><th>Issue No.</th><td>2</td></tr>'
            . '<tr><th>Date</th><td>15.05.2022</td></tr>'
            . '</table></td></tr></table></header>'
            . $body
            . '<footer>Document No: F 30_app | Revision No: 2 | Issue No: 2 | Date: 15.05.2022</footer>'
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

        $header = '<header class="f27-header"><table><tr>'
            . '<td class="f27-logo"><div class="f27-logo-text">QSI</div><div>AMS</div></td>'
            . '<td class="f27-title">' . esc($title) . '</td>'
            . '<td><table class="f27-control">'
            . '<tr><th>Document No.</th><td>' . esc((string) $documentNumber) . '</td></tr>'
            . '<tr><th>Revision No.</th><td>' . esc((string) $revisionNumber) . '</td></tr>'
            . '<tr><th>Issue No.</th><td>' . esc((string) $issueNumber) . '</td></tr>'
            . '<tr><th>Date</th><td>' . esc((string) $documentDate) . '</td></tr>'
            . '</table></td></tr></table></header>';

        $clientBlock = '<section class="client"><strong>Client:</strong> ' . esc((string) ($client['company'] ?? ''))
            . '<br><strong>Scope:</strong> ' . esc((string) ($client['scope'] ?? ''))
            . '<br><strong>Contract Number:</strong> ' . esc((string) ($contract['contract_number'] ?? 'Not created'))
            . ' &nbsp; <strong>Proposal Number:</strong> ' . esc((string) ($proposal['proposal_number'] ?? ''))
            . ' &nbsp; <strong>Status:</strong> ' . esc((string) ($contract['status'] ?? ''))
            . '</section>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->contractCss() . '</style></head><body>'
            . $header
            . $clientBlock
            . $body
            . '<footer>Document No: ' . esc((string) $documentNumber) . ' | Revision No: ' . esc((string) $revisionNumber) . ' | Issue No: ' . esc((string) $issueNumber) . ' | Date: ' . esc((string) $documentDate) . '</footer>'
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
            'certification_process_obligations' => 'QSI-Cert delivers certification services in accordance with accreditation requirements and applicable standards. Compliance is verified through planned audits, technical review, certification decision and surveillance activities.',
            'payment_terms' => "Certification Audit Fee:\n50% payable upon signing the contract.\n50% payable after receiving the draft copy of the certificate.\n\nSurveillance Audit Fee:\n100% payable one month in advance of the scheduled audit.",
            'certification_audit_includes' => "Audit planning and preparation.\nReview of management system documentation.\nStage 1 and Stage 2 audit execution.\nAudit reporting, technical review and certification decision.\nIssuance of the certificate after approval.",
            'surveillance_audit_includes' => "Audit planning and preparation.\nReview of changes since previous audit.\nSurveillance audit execution and reporting.\nFollow-up of previous findings and certification conditions.",
            'additional_a4_copy_fee' => '50 USD',
            'certificate_reissue_fee' => '150 USD',
            'extraordinary_audit_1_fee' => '850 USD',
            'extraordinary_audit_2_fee' => '925 USD',
            'vat_invoice_terms' => 'VAT will be applied according to applicable regulations. Invoices may be sent electronically by email.',
            'stage1_activity' => 'Stage 1 focuses on reviewing documentation, internal audit, management review, site conditions and readiness for Stage 2.',
            'stage2_activity' => 'Stage 2 evaluates implementation and effectiveness of the management system and verifies compliance with applicable standard requirements.',
            'certificate_issuance' => 'A Certificate of Registration valid for three years will be issued after successful audit completion, nonconformity closure where applicable, technical review, certification decision and final approval.',
            'surveillance_activity' => 'Surveillance audits review changes, internal audit, management review, objectives, operational control, legal compliance, previous findings and use of certification marks.',
            'audit_time_reference' => 'Audit time is calculated from the application review considering selected standard(s), effective personnel, HACCP plans/processes where applicable, sites, shifts, risk and applicable IAF/ISO rules.',
            'important_note' => 'By signing this agreement, the Client confirms acceptance of the certification agreement, rules for certification, business conditions, and the requirement to provide necessary information for the certification process.',
            'contact_line' => 'QSI_CERT TEAM +966569009021 info@qsi-cert.com',
        ];

        if (($proposalPayload['total_audit_days'] ?? '') === '' && ($proposalPayload['days_allotted'] ?? '') !== '') {
            $proposalPayload['total_audit_days'] = $proposalPayload['days_allotted'];
        }

        if (($contractPayload['total_audit_days'] ?? '') === '' && ($contractPayload['days_allotted'] ?? '') !== '') {
            $contractPayload['total_audit_days'] = $contractPayload['days_allotted'];
        }

        return $this->mergeNonEmpty($this->mergeNonEmpty($defaults, $proposalPayload), $contractPayload);
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
            ['Certification Process and Obligations', '<p>' . nl2br(esc((string) ($payload['certification_process_obligations'] ?? ''))) . '</p>'],
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
            ['Signatures', $this->keyValueTable([
                'On Behalf of QSI-Cert' => trim((string) ($contract['qsi_signatory_name'] ?? '') . ' ' . (string) ($contract['qsi_signatory_date'] ?? '')),
                'On Behalf of Client' => trim((string) ($contract['client_signatory_name'] ?? '') . ' ' . (string) ($contract['client_signatory_date'] ?? '')),
                'Signed By' => $contract['signed_by_name'] ?? '',
                'Signed At' => $contract['signed_at'] ?? '',
            ])],
            ['Important Note', '<p>' . nl2br(esc((string) ($payload['important_note'] ?? ''))) . '</p><p>' . esc((string) ($payload['contact_line'] ?? '')) . '</p>'],
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
        $documentNumber = $plan['document_number'] ?? 'F 31';
        $revisionNumber = $plan['revision_number'] ?? '2';
        $issueNumber = $plan['issue_number'] ?? '2';
        $documentDate = $plan['document_date'] ?? '15.05.2022';
        $planning = $this->auditPlanningSummary($event, $data['appointments'] ?? []);
        $rows = $this->auditPlanRowsForDocument($data, $planning);

        $body = '<h2>1. Audit Plan Control</h2>'
            . $this->f31Table([
                'Plan Number' => $plan['plan_number'] ?? 'Auto generated',
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

        $header = '<header class="f31-header"><table><tr>'
            . '<td class="f31-logo"><div class="f31-logo-text">QSI</div><div>AMS</div></td>'
            . '<td class="f31-title">AUDIT PLAN<br><span>' . esc($stageLabel) . '</span></td>'
            . '<td><table class="f31-control">'
            . '<tr><th>Document No.</th><td>' . esc((string) $documentNumber) . '</td></tr>'
            . '<tr><th>Revision No.</th><td>' . esc((string) $revisionNumber) . '</td></tr>'
            . '<tr><th>Issue No.</th><td>' . esc((string) $issueNumber) . '</td></tr>'
            . '<tr><th>Date</th><td>' . esc((string) $documentDate) . '</td></tr>'
            . '</table></td></tr></table></header>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->auditPlanCss() . '</style></head><body>'
            . $header
            . '<section class="client"><strong>Client:</strong> ' . esc((string) ($client['company'] ?? '')) . '<br><strong>Scope:</strong> ' . esc((string) ($client['scope'] ?? '')) . '<br><strong>Audit:</strong> ' . esc($stageLabel) . ' &nbsp; <strong>Audit No.:</strong> ' . esc((string) ($event['audit_number'] ?? '')) . '</section>'
            . $body
            . '<footer>Document No: ' . esc((string) $documentNumber) . ' | Revision No: ' . esc((string) $revisionNumber) . ' | Issue No: ' . esc((string) $issueNumber) . ' | Date: ' . esc((string) $documentDate) . '</footer>'
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

        $header = '<header class="f42-header"><table><tr>'
            . '<td class="f42-logo"><div class="f42-logo-text">QSI</div><div>AMS</div></td>'
            . '<td class="f42-title">' . esc($title) . '</td>'
            . '<td><table class="f42-control">'
            . '<tr><th>Document No.</th><td>' . esc((string) $documentNumber) . '</td></tr>'
            . '<tr><th>Revision No.</th><td>' . esc((string) $revisionNumber) . '</td></tr>'
            . '<tr><th>Issue No.</th><td>' . esc((string) $issueNumber) . '</td></tr>'
            . '<tr><th>Date</th><td>' . esc((string) $documentDate) . '</td></tr>'
            . '</table></td></tr></table></header>';

        $clientBlock = '<section class="client"><strong>Client:</strong> ' . esc((string) ($client['company'] ?? ''))
            . '<br><strong>Scope:</strong> ' . esc((string) ($client['scope'] ?? ''))
            . '<br><strong>Program Number:</strong> ' . esc((string) ($program['program_number'] ?? 'Not created'))
            . ' &nbsp; <strong>Status:</strong> ' . esc((string) ($program['status'] ?? ''))
            . '</section>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $this->css() . $this->auditProgramCss() . '</style></head><body>'
            . $header
            . $clientBlock
            . $body
            . '<footer>Document No: ' . esc((string) $documentNumber) . ' | Revision No: ' . esc((string) $revisionNumber) . ' | Issue No: ' . esc((string) $issueNumber) . ' | Date: ' . esc((string) $documentDate) . '</footer>'
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
            ['Audit Dates and Durations', $this->auditProgramEventsTable($data['events'] ?? [])],
            ['Processes / Standard Clauses', $this->auditProgramCoverageTable($payload['coverage'] ?? [])],
            ['Audit Committee', $this->auditProgramMatrixTable($payload['committee'] ?? [], 'role')],
            ['Audit NC Summary by Stage', $this->auditProgramMatrixTable($payload['nc_summary'] ?? [], 'standard')],
            ['Legend and Approval', $this->keyValueTable([
                'Legend / Notes' => $payload['legend_notes'] ?? '',
                'Prepared By' => $program['prepared_by_name'] ?? '',
                'Prepared Date' => $program['prepared_date'] ?? '',
                'Approved By' => $program['approved_by_name'] ?? '',
                'Approved Date' => $program['approved_date'] ?? '',
                'Electronic Note' => 'This is an electronically generated controlled document; signature requirements are managed by the certification body workflow.',
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

    private function auditProgramEventsTable(array $events): string
    {
        $labels = [
            'initial_stage1' => 'Stage 1',
            'initial_stage2' => 'Stage 2',
            'surveillance1' => 'Surv. 1',
            'surveillance2' => 'Surv. 2',
            'recertification' => 'Recert.',
        ];

        if ($events === []) {
            return '<p class="muted">No audit events available.</p>';
        }

        $html = '<table><thead><tr><th>Audit</th><th>Audit No.</th><th>Start Date</th><th>End Date</th><th>Window Start</th><th>Window End</th><th>Number of Days</th><th>Status</th></tr></thead><tbody>';
        foreach ($events as $event) {
            $type = (string) ($event['event_type'] ?? '');
            $html .= '<tr>'
                . '<td>' . esc($labels[$type] ?? ucwords(str_replace('_', ' ', $type))) . '</td>'
                . '<td>' . esc((string) ($event['audit_number'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($event['planned_start_date'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($event['planned_end_date'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($event['audit_window_start'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($event['audit_window_end'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($event['duration_days'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($event['status'] ?? '')) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditProgramCoverageTable(array $rows): string
    {
        if ($rows === []) {
            return '<p class="muted">No clause coverage available.</p>';
        }

        $html = '<table><thead><tr><th>Standard</th><th>Clause No.</th><th>Clause Title</th><th>Stage 1</th><th>Stage 2</th><th>Surv. 1</th><th>Surv. 2</th><th>Recert.</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . esc((string) ($row['standard'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['clause_number'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['clause_title'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['initial_stage1'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['initial_stage2'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['surveillance1'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['surveillance2'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['recertification'] ?? '')) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function auditProgramMatrixTable(array $rows, string $labelKey): string
    {
        if ($rows === []) {
            return '<p class="muted">No records available.</p>';
        }

        $html = '<table><thead><tr><th>' . esc(ucwords(str_replace('_', ' ', $labelKey))) . '</th><th>Stage 1</th><th>Stage 2</th><th>Surv. 1</th><th>Surv. 2</th><th>Recert.</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . esc((string) ($row[$labelKey] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['initial_stage1'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['initial_stage2'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['surveillance1'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['surveillance2'] ?? '')) . '</td>'
                . '<td>' . esc((string) ($row['recertification'] ?? '')) . '</td>'
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
            ['Audit Report Submission', $this->keyValueTable([
                'Report Submission Date' => $this->auditReportSubmissionDate($data['reports'] ?? []),
            ])],
            ['Report Drafts', $this->recordTable($data['reports'], ['audit_number', 'report_type', 'status', 'version_number', 'submitted_at'])],
            ['Checklist / Report Notes', $this->recordTable($data['report_sections'] ?? [], ['standard_code', 'clause_number', 'clause_title', 'section_key', 'section_content'])],
            ['Nonconformities', $this->recordTable($data['ncrs'], ['ncr_number', 'classification', 'status', 'finding'])],
            ['CAPA', $this->recordTable($data['capas'] ?? [], ['capa_number', 'status', 'issue', 'immediate_correction', 'root_cause', 'corrective_action', 'preventive_action', 'responsible_person', 'target_date', 'evidence_reference', 'verification', 'effectiveness', 'closed_at'])],
        ];
    }

    private function auditReportSubmissionDate(array $reports): string
    {
        foreach ($reports as $report) {
            $submittedAt = trim((string) ($report['submitted_at'] ?? ''));
            if ($submittedAt !== '') {
                return $submittedAt;
            }
        }

        return 'Not submitted';
    }

    private function ncrCapaSections(array $data): array
    {
        return [
            ['Audit Event', $this->eventTable($data['events'])],
            ['Nonconformities', $this->recordTable($data['ncrs'], ['ncr_number', 'classification', 'status', 'requirement', 'finding', 'objective_evidence', 'target_date'])],
            ['Corrective Action / Preventive Action', $this->recordTable($data['capas'] ?? [], ['capa_number', 'ncr_number', 'clause_number', 'ncr_requirement', 'ncr_finding', 'status', 'immediate_correction', 'root_cause', 'corrective_action', 'preventive_action', 'responsible_person', 'target_date', 'evidence_reference', 'verification', 'effectiveness', 'closed_at'])],
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
            ['NCR / CAPA Closure Reviewed', $this->recordTable($data['capas'] ?? [], ['capa_number', 'ncr_number', 'status', 'root_cause', 'corrective_action', 'evidence_reference', 'verification', 'closed_at'])],
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

    private function recordTable(array $records, array $columns): string
    {
        if ($records === []) {
            return '<p class="muted">No records available.</p>';
        }

        $html = '<table><thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th>' . esc(ucwords(str_replace('_', ' ', $column))) . '</th>';
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

        $rows = $this->db->table('application_questions')
            ->select('application_questions.section, application_questions.question_text, application_questions.display_order, application_answers.answer_text')
            ->join('application_answers', 'application_answers.application_question_id = application_questions.id', 'left')
            ->where('application_questions.application_id', (int) $application['id'])
            ->whereNotIn('application_questions.section', $this->excludedCertificationApplicationSections())
            ->where('application_questions.question_type !=', 'file')
            ->orderBy('application_questions.section', 'ASC')
            ->orderBy('application_questions.display_order', 'ASC')
            ->get()
            ->getResultArray();

        $answers = [];
        foreach ($rows as $row) {
            $answers[$row['section']][] = $row;
        }

        return [
            'application' => $application,
            'reviewer' => $this->applicationReviewer($application['reviewed_by'] ?? null),
            'selected_standards' => $this->db->table('application_selected_standards')
                ->where('application_id', (int) $application['id'])
                ->orderBy('standard_code', 'ASC')
                ->get()
                ->getResultArray(),
            'answers_by_section' => $answers,
            'attachments' => $this->db->table('application_attachments')
                ->where('application_id', (int) $application['id'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getResultArray(),
        ];
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
            ->select('auditor_appointments.*, personnel.full_name, users.full_name AS appointed_by_name')
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

    private function css(): string
    {
        return '
            @page { margin: 28px 34px 76px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #17202a; }
            header { border-bottom: 2px solid #0f5ea8; padding-bottom: 10px; margin-bottom: 18px; }
            .brand { color: #0f5ea8; font-size: 18px; font-weight: 700; }
            .doc-title { font-size: 16px; font-weight: 700; margin-top: 4px; }
            .client { background: #f4f7fb; border: 1px solid #dbe5ef; padding: 10px; margin-bottom: 14px; }
            h2 { font-size: 14px; margin: 16px 0 8px; color: #0a3765; page-break-after: avoid; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
            tr { page-break-inside: avoid; }
            th, td { border: 1px solid #dbe5ef; padding: 6px; vertical-align: top; }
            th { background: #eef5fb; text-align: left; font-weight: 700; }
            .muted { color: #6b7785; }
            .qr { margin-top: 8px; font-size: 9px; color: #56616f; }
            .qr img { width: 80px; height: 80px; }
            footer { position: fixed; left: 34px; right: 34px; bottom: 16px; border-top: 1px solid #dbe5ef; padding-top: 6px; color: #6b7785; font-size: 9px; }
        ';
    }

    private function certificateCss(): string
    {
        return '
            body { text-align: center; }
            .certificate-shell { border: 5px solid #0f5ea8; padding: 34px; min-height: 930px; }
            h1 { font-size: 28px; color: #0a3765; margin: 42px 0 24px; }
            h2 { font-size: 24px; color: #17202a; margin: 14px 0; }
            h3 { font-size: 22px; color: #0f5ea8; margin: 18px 0; }
            .certifies { color: #56616f; font-size: 13px; }
            .scope { border: 1px solid #dbe5ef; padding: 14px; margin: 22px 20px; font-size: 13px; }
            .meta th, .meta td { font-size: 10px; text-align: left; }
            .qr { margin-top: 20px; font-size: 9px; color: #56616f; }
            .qr img { width: 92px; height: 92px; }
        ';
    }

    private function certificationApplicationCss(): string
    {
        return '
            @page { margin: 30px 32px 52px; }
            .f25-header { border: 0; padding: 0; margin-bottom: 14px; }
            .f25-header table { border: 2px solid #1d2b3a; margin-bottom: 12px; }
            .f25-header td { border: 1px solid #1d2b3a; padding: 8px; vertical-align: middle; }
            .f25-logo { width: 17%; text-align: center; color: #0f5ea8; font-weight: 700; }
            .f25-logo-text { font-size: 24px; line-height: 1; }
            .f25-title { width: 50%; text-align: center; font-size: 17px; font-weight: 700; color: #0a3765; }
            footer { color: #000; border-top: 1px solid #1d2b3a; }
        ';
    }

    private function auditorAppointmentCss(): string
    {
        return '
            @page { margin: 30px 28px 260px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #000; }
            h2 { color: #000; font-size: 14px; margin: 18px 0 8px; page-break-after: avoid; }
            h3 { color: #000; font-size: 12px; margin: 14px 0 8px; page-break-after: avoid; }
            p { line-height: 1.35; margin: 7px 0 12px; }
            .f30-header { border: 0; padding: 0; margin-bottom: 18px; }
            .f30-header table { border: 2px solid #000; margin-bottom: 0; }
            .f30-header td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
            .f30-logo { width: 23%; text-align: center; color: #0f70bd; font-weight: 700; }
            .f30-logo-text { font-size: 34px; line-height: 1; }
            .f30-title { width: 43%; text-align: center; font-size: 17px; font-weight: 700; }
            .f30-cert { background: #d9e2f3; color: #1f497d; font-size: 15px; padding: 16px 0; margin: 24px -8px -8px; }
            .f30-control { margin-bottom: 0; }
            .f30-control th { width: 54%; background: #fff; border: 1px solid #000; padding: 6px 8px; color: #000; }
            .f30-control td { width: 46%; background: #d9e2f3; border: 1px solid #000; padding: 6px 8px; color: #1f497d; }
            .f30-table th { width: 40%; background: #d9e2f3; color: #000; border: 1px solid #000; padding: 7px 8px; font-weight: 700; }
            .f30-table td { width: 60%; border: 1px solid #000; padding: 7px 8px; }
            .f30-grid th, .f30-grid td { border: 1px solid #000; padding: 7px 8px; vertical-align: top; }
            .f30-grid th { background: #d9e2f3; color: #000; text-align: left; }
            footer { position: fixed; left: 28px; right: 28px; bottom: 24px; border-top: 1px solid #000; padding-top: 5px; color: #000; font-size: 9px; }
        ';
    }

    private function auditPlanCss(): string
    {
        return '
            @page { margin: 28px 24px 112px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.6px; color: #000; }
            h2 { color: #003366; font-size: 13px; margin: 14px 0 7px; page-break-after: avoid; }
            p { line-height: 1.35; margin: 6px 0 10px; }
            .f31-header { border: 0; padding: 0; margin-bottom: 12px; }
            .f31-header table { border: 2px solid #1d2b3a; margin-bottom: 0; }
            .f31-header td { border: 1px solid #1d2b3a; padding: 7px; vertical-align: middle; }
            .f31-logo { width: 18%; text-align: center; color: #0f5ea8; font-weight: 700; }
            .f31-logo-text { font-size: 28px; line-height: 1; }
            .f31-title { width: 49%; text-align: center; font-size: 18px; font-weight: 700; color: #0a3765; line-height: 1.25; }
            .f31-title span { font-size: 13px; color: #1f497d; }
            .f31-control { margin-bottom: 0; }
            .f31-control th { width: 52%; background: #eef5fb; color: #000; border: 1px solid #1d2b3a; padding: 5px 6px; }
            .f31-control td { width: 48%; border: 1px solid #1d2b3a; padding: 5px 6px; color: #1f497d; }
            .client { background: #f4f7fb; border: 1px solid #dbe5ef; padding: 8px; margin-bottom: 12px; }
            .f31-table th { width: 32%; background: #eaf2fb; color: #000; border: 1px solid #b8c8d8; padding: 6px 7px; font-weight: 700; }
            .f31-table td { width: 68%; border: 1px solid #b8c8d8; padding: 6px 7px; }
            .f31-grid th, .f31-grid td { border: 1px solid #b8c8d8; padding: 5px 6px; vertical-align: top; }
            .f31-grid th { background: #eaf2fb; color: #000; text-align: left; font-weight: 700; }
            .f31-grid thead { display: table-header-group; }
            .f31-grid tr { page-break-inside: avoid; page-break-after: auto; }
            .f31-timetable th, .f31-timetable td { font-size: 8.8px; }
            .f31-note { color: #334155; }
            footer { position: fixed; left: 24px; right: 24px; bottom: 18px; border-top: 1px solid #1d2b3a; padding-top: 5px; color: #000; font-size: 8.5px; }
        ';
    }

    private function contractCss(): string
    {
        return '
            @page { margin: 30px 32px 118px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #000; }
            h2 { color: #003366; font-size: 14px; margin: 16px 0 8px; page-break-after: avoid; }
            .f27-header { border: 0; padding: 0; margin-bottom: 14px; }
            .f27-header table { border: 2px solid #1d2b3a; margin-bottom: 12px; }
            .f27-header td { border: 1px solid #1d2b3a; padding: 8px; vertical-align: middle; }
            .f27-logo { width: 17%; text-align: center; color: #0f5ea8; font-weight: 700; }
            .f27-logo-text { font-size: 24px; line-height: 1; }
            .f27-title { width: 50%; text-align: center; font-size: 17px; font-weight: 700; color: #0a3765; }
            .f27-control { margin-bottom: 0; }
            .f27-control th { width: 48%; background: #eef5fb; color: #000; }
            .f27-control th, .f27-control td { border: 1px solid #1d2b3a; padding: 5px 6px; }
            .client { background: #f4f7fb; border: 1px solid #dbe5ef; padding: 10px; margin-bottom: 14px; }
            footer { color: #000; border-top: 1px solid #1d2b3a; }
        ';
    }

    private function auditProgramCss(): string
    {
        return '
            @page { margin: 30px 32px 126px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #000; }
            h2 { color: #003366; font-size: 14px; margin: 16px 0 8px; page-break-after: avoid; }
            .f42-header { border: 0; padding: 0; margin-bottom: 14px; }
            .f42-header table { border: 2px solid #1d2b3a; margin-bottom: 12px; }
            .f42-header td { border: 1px solid #1d2b3a; padding: 8px; vertical-align: middle; }
            .f42-logo { width: 17%; text-align: center; color: #0f5ea8; font-weight: 700; }
            .f42-logo-text { font-size: 24px; line-height: 1; }
            .f42-title { width: 50%; text-align: center; font-size: 17px; font-weight: 700; color: #0a3765; }
            .f42-control { margin-bottom: 0; }
            .f42-control th { width: 48%; background: #eef5fb; color: #000; }
            .f42-control th, .f42-control td { border: 1px solid #1d2b3a; padding: 5px 6px; }
            .client { background: #f4f7fb; border: 1px solid #dbe5ef; padding: 10px; margin-bottom: 14px; }
            footer { color: #000; border-top: 1px solid #1d2b3a; }
        ';
    }

    private function applicationReviewCss(): string
    {
        return '
            @page { margin: 34px 28px 46px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #000; }
            h2 { color: #000; font-size: 14px; margin: 16px 0 8px; page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .f28-header table { border: 2px solid #000; margin-bottom: 18px; }
            .f28-header td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
            .f28-logo { width: 18%; text-align: center; color: #0f70bd; font-weight: 700; font-size: 13px; }
            .f28-logo-text { font-size: 28px; line-height: 1; }
            .f28-title { width: 54%; text-align: center; font-size: 18px; font-weight: 700; line-height: 1.15; }
            .f28-cert { background: #d9e2f3; text-align: center; color: #1f497d; font-size: 15px; font-weight: 700; letter-spacing: 1px; }
            .f28-table th { width: 40%; background: #d9e2f3; color: #000; border: 1px solid #000; padding: 7px 8px; font-weight: 700; }
            .f28-table td { width: 60%; border: 1px solid #000; padding: 7px 8px; font-weight: 600; }
            .f28-man-days th, .f28-man-days td { border: 1px solid #000; padding: 6px; text-align: center; }
            .f28-man-days th { background: #d9e2f3; color: #000; }
            .f28-note { font-size: 10px; margin-top: 18px; }
            footer { position: fixed; left: 28px; right: 28px; bottom: 14px; border-top: 1px solid #000; padding-top: 5px; color: #000; font-size: 9px; }
        ';
    }
}

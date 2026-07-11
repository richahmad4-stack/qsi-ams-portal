<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class CycleAutomationService
{
    private BaseConnection $db;
    private AuditDurationService $duration;
    private AuditLogger $logger;
    private SmartAuditContentEngine $contentEngine;
    private CertificationApplicationDefaults $applicationDefaults;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->duration = new AuditDurationService();
        $this->logger = new AuditLogger();
        $this->contentEngine = new SmartAuditContentEngine();
        $this->applicationDefaults = new CertificationApplicationDefaults();
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
        $assignments = $this->assignStaff($tenantId, $input, $standards);
        $events = $this->eventPlan($cycle, $timeline, $duration, $assignments);
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
            throw new RuntimeException('Cycle Builder cannot prepare the file while critical preview controls are open.');
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
            throw new RuntimeException('Cycle Builder could not complete the certification file preparation.');
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

    public function importBatch(UploadedFile $file, int $tenantId, int $userId): array
    {
        $extension = strtolower($file->getExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw new RuntimeException('Please upload CSV or XLSX only.');
        }

        $rows = $extension === 'csv'
            ? $this->readCsvRows($file->getTempName())
            : $this->readXlsxRows($file->getTempName());

        if ($rows === []) {
            throw new RuntimeException('The uploaded file does not contain client rows.');
        }

        $prepared = [];
        $failed = [];
        foreach ($rows as $index => $row) {
            try {
                $input = $this->inputFromBatchRow($row);
                $preview = $this->preview($input, $tenantId, $userId);
                if (! ($preview['can_generate'] ?? false)) {
                    $messages = array_map(static fn (array $warning): string => (string) ($warning['message'] ?? ''), $preview['warnings'] ?? []);
                    throw new RuntimeException(implode('; ', array_filter($messages)) ?: 'Preview controls were not satisfied.');
                }
                $result = $this->generate($preview, $tenantId, $userId);
                $prepared[] = [
                    'row' => $index + 2,
                    'client_name' => $input['client_name'],
                    'client_id' => $result['client_id'],
                    'message' => 'Certification file prepared.',
                ];
            } catch (\Throwable $exception) {
                $failed[] = [
                    'row' => $index + 2,
                    'client_name' => (string) ($row['client_name'] ?? ''),
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'prepared' => $prepared,
            'failed' => $failed,
            'total' => count($rows),
        ];
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }
        $headers = array_map([$this, 'batchHeader'], $headers);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (implode('', array_map('trim', $line)) === '') {
                continue;
            }
            $rows[] = $this->combineBatchRow($headers, $line);
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsxRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX support requires PHP ZipArchive. Please upload CSV on this machine.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to read uploaded XLSX file.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = new SimpleXMLElement($sharedXml);
            foreach ($shared->si as $item) {
                $text = '';
                if (isset($item->t)) {
                    $text = (string) $item->t;
                } elseif (isset($item->r)) {
                    foreach ($item->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('The XLSX file must contain data on the first worksheet.');
        }

        $sheet = new SimpleXMLElement($sheetXml);
        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $index = $this->excelColumnIndex(preg_replace('/\d+/', '', $ref));
                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $line[$index] = $value;
            }
            if ($line !== []) {
                ksort($line);
                $max = max(array_keys($line));
                $matrix[] = array_map(static fn (int $idx): string => (string) ($line[$idx] ?? ''), range(0, $max));
            }
        }

        if ($matrix === []) {
            return [];
        }

        $headers = array_map([$this, 'batchHeader'], array_shift($matrix));
        $rows = [];
        foreach ($matrix as $line) {
            if (implode('', array_map('trim', $line)) === '') {
                continue;
            }
            $rows[] = $this->combineBatchRow($headers, $line);
        }

        return $rows;
    }

    private function excelColumnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $number = 0;
        for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
            $number = $number * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $number - 1);
    }

    private function combineBatchRow(array $headers, array $line): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = trim((string) ($line[$index] ?? ''));
        }

        return $row;
    }

    private function batchHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $header), '_'));
    }

    private function inputFromBatchRow(array $row): array
    {
        $standardsText = (string) ($row['standards'] ?? $row['standard'] ?? '');

        return [
            'client_name' => $row['client_name'] ?? $row['company'] ?? '',
            'client_address' => $row['client_address'] ?? $row['address'] ?? '',
            'contact_person' => $row['contact_person'] ?? $row['management_representative'] ?? '',
            'designation' => $row['designation'] ?? 'Management Representative',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'standard_ids' => $this->standardIdsFromText($standardsText),
            'scope' => $row['scope'] ?? $row['certification_scope'] ?? '',
            'iaf_code_id' => $this->referenceIdFromText('iaf_codes', (string) ($row['iaf_code'] ?? '')),
            'food_category_id' => $this->referenceIdFromText('food_chain_categories', (string) ($row['food_category'] ?? '')),
            'medical_category_id' => $this->referenceIdFromText('medical_device_categories', (string) ($row['medical_category'] ?? '')),
            'employee_count' => $row['employee_count'] ?? $row['employees'] ?? 1,
            'number_of_sites' => $row['number_of_sites'] ?? $row['sites'] ?? 1,
            'certificate_issue_date' => $this->batchDate((string) ($row['certificate_issue_date'] ?? $row['issue_date'] ?? '')),
            'certificate_expiry_date' => $this->batchDate((string) ($row['certificate_expiry_date'] ?? $row['expiry_date'] ?? ''), false),
            'certification_status' => $row['certification_status'] ?? 'certified',
            'current_cycle_stage' => $row['current_cycle_stage'] ?? 'auto',
            'risk_category' => $row['risk_category'] ?? 'medium',
            'special_notes' => $row['special_notes'] ?? '',
            'ncr_mode' => $row['ncr_mode'] ?? 'sample_minor',
            'generation_mode' => $row['generation_mode'] ?? 'standard',
            'application_review_notes' => $row['application_review_notes'] ?? '',
            'audit_evidence_summary' => $row['audit_evidence_summary'] ?? '',
            'audit_plan_notes' => $row['audit_plan_notes'] ?? '',
            'technical_review_notes' => $row['technical_review_notes'] ?? '',
            'decision_basis' => $row['decision_basis'] ?? '',
        ];
    }

    private function standardIdsFromText(string $text): array
    {
        $parts = array_filter(array_map('trim', preg_split('/[;,|]+/', $text) ?: []));
        if ($parts === []) {
            return [];
        }

        $ids = [];
        foreach ($parts as $part) {
            $standard = $this->db->table('standards')
                ->select('id')
                ->where('active', 1)
                ->groupStart()
                    ->where('code', $part)
                    ->orLike('code', $part)
                    ->orLike('name', $part)
                ->groupEnd()
                ->orderBy('id', 'ASC')
                ->get(1)
                ->getRowArray();
            if ($standard !== null) {
                $ids[] = (int) $standard['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private function referenceIdFromText(string $table, string $text): ?int
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $row = $this->db->table($table)
            ->select('id')
            ->groupStart()
                ->where('code', $text)
                ->orLike('code', $text)
                ->orLike('title', $text)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function batchDate(string $value, bool $required = true): string
    {
        $value = trim($value);
        if ($value === '') {
            if ($required) {
                throw new RuntimeException('Certificate issue date is required.');
            }

            return '';
        }
        if (is_numeric($value)) {
            return (new DateTimeImmutable('1899-12-30'))->add(new DateInterval('P' . (int) $value . 'D'))->format('Y-m-d');
        }

        return (new DateTimeImmutable($value))->format('Y-m-d');
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
            'generation_mode' => trim((string) ($input['generation_mode'] ?? 'standard')) ?: 'standard',
            'application_review_notes' => trim((string) ($input['application_review_notes'] ?? '')),
            'audit_evidence_summary' => trim((string) ($input['audit_evidence_summary'] ?? '')),
            'audit_plan_notes' => trim((string) ($input['audit_plan_notes'] ?? '')),
            'technical_review_notes' => trim((string) ($input['technical_review_notes'] ?? '')),
            'decision_basis' => trim((string) ($input['decision_basis'] ?? '')),
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

    private function eventPlan(array $cycle, array $timeline, array $duration, array $assignments): array
    {
        return [
            'initial_stage1' => $this->eventRow('initial_stage1', $timeline['stage1_audit'], (float) $duration['stage1_days'], $assignments),
            'initial_stage2' => $this->eventRow('initial_stage2', $timeline['stage2_audit'], (float) $duration['stage2_days'], $assignments),
            'surveillance1' => $this->eventRow('surveillance1', (new DateTimeImmutable($cycle['surveillance1']))->sub(new DateInterval('P12D'))->format('Y-m-d'), (float) $duration['surveillance1_days'], $assignments),
            'surveillance2' => $this->eventRow('surveillance2', (new DateTimeImmutable($cycle['surveillance2']))->sub(new DateInterval('P12D'))->format('Y-m-d'), (float) $duration['surveillance2_days'], $assignments),
            'recertification' => $this->eventRow('recertification', (new DateTimeImmutable($cycle['expiry']))->sub(new DateInterval('P90D'))->format('Y-m-d'), (float) $duration['recertification_days'], $assignments),
        ];
    }

    private function eventRow(string $type, string $startDate, float $days, array $assignments): array
    {
        $start = new DateTimeImmutable($startDate);
        $capacity = $this->auditCapacity($days, $assignments);
        $calendarDays = max(1, (int) ceil(max(0.50, $days) / $capacity));
        $end = $start->add(new DateInterval('P' . max(0, $calendarDays - 1) . 'D'));
        $today = new DateTimeImmutable(date('Y-m-d'));
        $status = $end < $today ? 'completed' : ($start <= $today && $end >= $today ? 'in_progress' : 'planned');

        return [
            'type' => $type,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'days' => $days,
            'calendar_days' => $calendarDays,
            'auditor_capacity' => $capacity,
            'status' => $status,
        ];
    }

    private function auditCapacity(float $days, array $assignments): int
    {
        if ($days <= 1.0 || empty($assignments['auditor'])) {
            return 1;
        }

        return 2;
    }

    private function assignStaff(int $tenantId, array $input, array $standards): array
    {
        $assignments = [
            'decision_maker' => $this->eligiblePerson($tenantId, ['certification_decision_maker'], $input, $standards, [], ['Eng. Mohammad Ahmad', 'Dr. Rana Amjad Hanif']),
        ];
        $blocked = array_filter([(int) ($assignments['decision_maker']['id'] ?? 0)]);

        $assignments['technical_reviewer'] = $this->eligiblePerson($tenantId, ['technical_reviewer', 'technical_manager'], $input, $standards, $blocked, ['Ms. Rimsha Mahmoud', 'Mohammad Raheel', 'Dr. Rana Amjad Hanif']);
        if ($assignments['technical_reviewer'] !== null) {
            $blocked[] = (int) $assignments['technical_reviewer']['id'];
        }

        $assignments['lead_auditor'] = $this->eligiblePerson($tenantId, ['lead_auditor'], $input, $standards, $blocked, ['Mr. Rifki El-Sherbeny', 'Mohammad Arshad Ali', 'Qammar Shahzad', 'Rana Arslan Khan']);
        if ($assignments['lead_auditor'] !== null) {
            $blocked[] = (int) $assignments['lead_auditor']['id'];
        }

        $assignments['auditor'] = $this->eligiblePerson($tenantId, ['auditor'], $input, $standards, $blocked);
        $assignments['quality_manager'] = $this->eligiblePerson($tenantId, ['quality_manager'], $input, $standards, []);
        $assignments['certification_manager'] = $this->eligiblePerson($tenantId, ['certification_manager', 'administrator'], $input, $standards, []);
        $assignments['general_manager'] = $this->eligiblePerson($tenantId, ['general_manager'], $input, $standards, []);
        $assignments['finance'] = $this->eligiblePerson($tenantId, ['finance'], $input, $standards, []);

        return [
            'lead_auditor' => $assignments['lead_auditor'],
            'auditor' => $assignments['auditor'],
            'technical_reviewer' => $assignments['technical_reviewer'],
            'decision_maker' => $assignments['decision_maker'],
            'quality_manager' => $assignments['quality_manager'],
            'certification_manager' => $assignments['certification_manager'],
            'general_manager' => $assignments['general_manager'],
            'finance' => $assignments['finance'],
        ];
    }

    private function eligiblePerson(int $tenantId, array $roleCodes, array $input, array $standards, array $blocked, array $preferredNames = []): ?array
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
        usort($people, function (array $a, array $b) use ($preferredNames): int {
            return $this->personPreferenceScore($a, $preferredNames) <=> $this->personPreferenceScore($b, $preferredNames);
        });

        foreach ($people as $person) {
            if ($this->coversCompetence((int) $person['id'], $input, $standards)) {
                return $person;
            }
        }

        return $people[0] ?? null;
    }

    private function personPreferenceScore(array $person, array $preferredNames): int
    {
        $name = strtolower((string) ($person['full_name'] ?? ''));
        foreach ($preferredNames as $index => $preferred) {
            if ($name === strtolower($preferred) || str_contains($name, strtolower($preferred))) {
                return $index;
            }
        }

        $roles = strtolower((string) ($person['role_codes'] ?? ''));
        $score = 100;
        if (str_contains($roles, 'certification_decision_maker')) {
            $score += 10;
        }
        if (str_contains($roles, 'technical_manager')) {
            $score += 5;
        }

        return $score;
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
        if (($input['generation_mode'] ?? '') === 'historical_confirmed' && trim((string) ($input['audit_evidence_summary'] ?? '')) === '') {
            $warnings[] = ['level' => 'critical', 'message' => 'Completed historical file mode requires actual audit evidence summary. Otherwise use standard workflow pack mode.'];
        }
        if (($input['generation_mode'] ?? '') === 'historical_confirmed' && trim((string) ($input['technical_review_notes'] ?? '')) === '') {
            $warnings[] = ['level' => 'critical', 'message' => 'Historical completed mode requires Technical Review notes from the actual file.'];
        }
        if (($input['generation_mode'] ?? '') === 'historical_confirmed' && trim((string) ($input['decision_basis'] ?? '')) === '') {
            $warnings[] = ['level' => 'critical', 'message' => 'Historical completed mode requires Decision basis from the actual file.'];
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
            'notes' => trim("Prepared by Cycle Builder.\n" . $input['special_notes']),
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
                'description' => 'Cycle process coverage for ' . $process . '.',
            ]);
        }
    }

    private function createApplication(int $tenantId, int $clientId, int $userId, array $input, array $preview): int
    {
        $number = $this->number('APP-AUTO', $clientId);
        $confirmed = $this->workflowPackComplete($input);
        $this->db->table('certification_applications')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'application_number' => $number,
            'document_number' => 'F 25',
            'revision_number' => '1',
            'issue_number' => '2',
            'issue_date' => '2024-11-01',
            'status' => $confirmed ? 'approved' : 'submitted',
            'submitted_at' => $preview['timeline']['application_submitted'] . ' 10:00:00',
            'declaration_name' => $input['contact_person'],
            'declaration_position' => $input['designation'],
            'declaration_date' => $preview['timeline']['application_submitted'],
            'cb_review_status' => $confirmed ? 'accepted' : 'pending',
            'cb_review_notes' => $confirmed
                ? $this->nonEmpty($input['application_review_notes'], 'Application reviewed and accepted for certification processing.')
                : 'System prepared the application record. Certification Body review must be completed by the Technical Manager.',
            'reviewed_by' => $confirmed ? ($preview['assignments']['technical_reviewer']['user_id'] ?? $userId) : null,
            'reviewed_at' => $confirmed ? $preview['timeline']['application_review'] . ' 11:30:00' : null,
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
        $this->createApplicationResponses($applicationId, $userId, $input, $preview);

        return $applicationId;
    }

    private function createApplicationResponses(int $applicationId, int $userId, array $input, array $preview): void
    {
        $standards = implode(', ', array_column($preview['standards'], 'code'));
        $client = $this->clientShape($input);
        $answer = function (string $key, string $fallback = '') use ($client, $preview): string {
            return $this->applicationDefaults->applicationAnswer($key, $client, $preview['standards']) ?? $fallback;
        };
        $rows = [
            'Audit Preferences' => [
                'Language of Audit' => $answer('language_of_audit', 'English / Arabic'),
            ],
            'Background Information' => [
                'Has previous contact been made with QSI personnel?' => $answer('previous_qsi_contact', 'No'),
                'If yes, state the person name and meeting/visit details' => $answer('qsi_contact_details', 'Not applicable'),
                'Where did you hear about QSI?' => $answer('heard_about_qsi', 'SFDA list, website, social media'),
                'Do you currently use any other QSI services?' => $answer('other_qsi_services', 'No'),
            ],
            'Certification Required' => [
                'Integrated Management System' => count($preview['standards']) > 1 ? 'Yes - ' . $standards : 'No',
                'Applicable Legal and Regulatory Requirement' => $answer('legal_statutory_requirements', 'Applicable legal, statutory, regulatory and customer requirements related to the certification scope will be verified during the audit.'),
                'Risks associated with products, processes or activities' => $answer('product_process_risks', 'Operational and compliance risks related to the certification scope will be verified during the audit.'),
                'Number of HACCP Studies / Plans' => $answer('haccp_plans_processes', '1'),
                'Any incident / accident in the past?' => 'No incident reported at application stage',
                '1a. Analysis of technical issues arising from the scope' => $answer('technical_issues'),
                '1b. Safety condition requirements' => $answer('safety_requirements'),
                '1c. Technological and Regulatory Context' => $answer('technological_regulatory_context'),
            ],
            'Scope / Processes' => [
                'Scope of Certification' => $answer('scope_of_certification', $input['scope']),
                'Products' => $answer('products', 'Products covered by the requested certification scope.'),
                'Services' => $answer('services', 'Services covered by the requested certification scope.'),
                'Processes' => $answer('processes', $this->defaultProcesses($preview['standards'])),
                'Outsourced Processes' => $answer('outsourced_processes', 'No outsourced process declared at application stage.'),
            ],
            'Company / Organisation Details' => [
                'Company Name' => $input['client_name'],
                'Legal Name' => $input['client_name'],
                'Commercial Registration Number' => 'To be provided by client',
                'VAT Number' => 'To be provided by client',
                'License Number' => 'To be provided by client',
                'Address' => $input['client_address'],
                'Country' => 'To be confirmed',
                'City' => 'To be confirmed',
                'Website' => 'To be provided by client',
                'Contact Person' => $input['contact_person'],
                'Designation' => $input['designation'],
                'Email' => $input['email'],
                'Phone' => $input['phone'],
                'Mobile' => $input['phone'],
            ],
            'Consultant Information' => [
                'Consultant Used' => 'No',
            ],
            'Employees and Working Patterns' => [
                'Number of Employees' => (string) $input['employee_count'],
                'Number of employees in the activities to be certified' => (string) $input['employee_count'],
                'Permanent Employees' => (string) $input['employee_count'],
                'Temporary Employees' => '0',
                'Contract Workers' => '0',
                'Number of Shifts' => '1',
                'Working Hours' => '08:00 to 17:00',
                'Seasonal Operations' => 'No',
            ],
            'Existing Registrations / Transfer' => [
                'Previous Certification' => 'No',
                'Certification Body' => 'Not applicable',
                'Certificate Number' => 'Not applicable',
                'Transfer Certification' => 'No',
                'Certification Status' => ucwords(str_replace('_', ' ', $input['certification_status'])),
                'Expiry Date' => $preview['cycle']['expiry'],
                'Audit Reports Available' => 'Not applicable',
                'NC Status' => 'Not applicable',
                'Customer Complaints' => 'No complaint reported at application stage',
            ],
            'Locations' => [
                'Number of Sites' => (string) $input['number_of_sites'],
                'Head Office' => $input['client_address'],
                'Branches' => $input['number_of_sites'] > 1 ? 'Additional sites to be listed by client' : 'Not applicable',
                'Remote Locations' => 'Not applicable',
            ],
            'Management System Readiness' => [
                'Management System Status' => $answer('management_system_status', 'Mixed'),
                'Implementation of the system completed?' => $answer('implementation_status', 'Yes'),
                'Internal Audit conducted?' => $answer('internal_audit_conducted', 'Yes'),
                'Management Review conducted?' => $answer('management_review_conducted', 'Yes'),
                'Last management review meeting conducted?' => $answer('last_management_review_meeting_conducted', 'Yes'),
                'Certification scope requested' => $input['scope'],
            ],
        ];

        $order = 1;
        foreach ($rows as $section => $questions) {
            foreach ($questions as $question => $answer) {
                $key = 'cycle_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $section . '_' . $question));
                $questionLibraryId = $this->ensureQuestionLibrary($key, $section, $question, $order);
                $this->db->table('application_questions')->insert([
                    'application_id' => $applicationId,
                    'question_library_id' => $questionLibraryId,
                    'question_key' => $key,
                    'question_text' => $question,
                    'question_type' => 'text',
                    'section' => $section,
                    'display_order' => $order,
                    'mandatory' => 0,
                    'validation_rules' => json_encode([], JSON_THROW_ON_ERROR),
                    'help_text' => null,
                    'standard_codes' => json_encode(array_values(array_column($preview['standards'], 'code')), JSON_THROW_ON_ERROR),
                ]);
                $applicationQuestionId = (int) $this->db->insertID();
                $this->db->table('application_answers')->insert([
                    'application_id' => $applicationId,
                    'application_question_id' => $applicationQuestionId,
                    'question_library_id' => $questionLibraryId,
                    'answer_text' => $answer,
                    'answered_by' => $userId,
                    'answered_at' => $preview['timeline']['application_submitted'] . ' 10:15:00',
                ]);
                $order++;
            }
        }
    }

    private function ensureQuestionLibrary(string $key, string $section, string $question, int $order): int
    {
        $existing = $this->db->table('question_library')->select('id')->where('question_key', $key)->get(1)->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $this->db->table('question_library')->insert([
            'question_key' => $key,
            'question_text' => $question,
            'question_type' => 'text',
            'applicable_standards' => json_encode([], JSON_THROW_ON_ERROR),
            'mandatory' => 0,
            'section' => $section,
            'display_order' => $order,
            'validation_rules' => json_encode([], JSON_THROW_ON_ERROR),
            'help_text' => null,
            'default_answer' => null,
            'active' => 1,
        ]);

        return (int) $this->db->insertID();
    }

    private function createApplicationReview(int $clientId, int $applicationId, array $preview): int
    {
        $duration = $preview['duration'];
        $input = $preview['input'];
        $confirmed = $this->workflowPackComplete($input);
        $reviewDefaults = $this->applicationDefaults->reviewDefaults($this->clientShape($input), $preview['standards']);
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
            'completeness_status' => $confirmed ? 'complete' : 'pending_review',
            'risk_rating' => $input['risk_category'],
            'recommendation' => $confirmed ? 'Proceed to proposal' : 'Pending Technical Manager / Quality Manager approval',
            'md5_duration_days' => (float) $duration['total_days'],
            'integrated_reduction_percent' => (float) ($duration['reduction_percent'] ?? 0),
            'stage1_days' => (float) $duration['stage1_days'],
            'stage2_days' => (float) $duration['stage2_days'],
            'review_notes' => $confirmed
                ? $this->nonEmpty($input['application_review_notes'], 'Application review completed. Scope, competence, resources, impartiality and audit time were checked for the selected standards.')
                : 'System prepared the application review. Technical Manager shall verify scope, competence, resources, impartiality, audit time and selected standards before approval.',
            'review_payload' => json_encode(array_merge([
                'standards_text' => implode(', ', array_column($preview['standards'], 'code')),
                'effective_employees' => $input['employee_count'],
                'days_allotted' => number_format((float) $duration['total_days'], 2),
                'stage1_days' => number_format((float) $duration['stage1_days'], 2),
                'stage2_days' => number_format((float) $duration['stage2_days'], 2),
                'surveillance1_days' => number_format((float) $duration['surveillance1_days'], 2),
                'surveillance2_days' => number_format((float) $duration['surveillance2_days'], 2),
                'recertification_days' => number_format((float) $duration['recertification_days'], 2),
                'calculation_basis' => $duration['basis'],
                'application_review_notes' => $input['application_review_notes'],
                'automation_mode' => $input['generation_mode'],
            ], $reviewDefaults), JSON_THROW_ON_ERROR),
            'status' => $confirmed ? 'qm_approved' : 'draft',
            'reviewed_at' => $confirmed ? $preview['timeline']['application_review'] . ' 14:00:00' : null,
            'technical_reviewer_name' => $preview['assignments']['technical_reviewer']['full_name'] ?? '',
            'technical_review_date' => $confirmed ? $preview['timeline']['application_review'] : null,
            'quality_manager_status' => $confirmed ? 'approved' : 'pending',
            'quality_manager_comments' => $confirmed ? 'Independent Quality Manager approval completed for the application review.' : 'Quality Manager approval required.',
            'quality_manager_name' => $preview['assignments']['quality_manager']['full_name'] ?? '',
            'quality_manager_date' => $confirmed ? $preview['timeline']['application_review'] : null,
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
        $confirmed = $this->workflowPackComplete($preview['input']);

        $this->db->table('proposals')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'application_review_id' => $reviewId,
            'proposal_number' => $this->number('PROP-AUTO', $clientId),
            'version_number' => 1,
            'status' => $confirmed ? 'accepted' : 'draft',
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
            'proposal_payload' => json_encode([
                'payment_terms' => '50% before Stage 1 audit and 50% before certificate issue.',
                'system_prepared' => true,
                'automation_mode' => $preview['input']['generation_mode'],
                'client_acceptance_note' => $confirmed ? 'Client acceptance recorded and proposal approved for contract preparation.' : 'Client acceptance must be recorded before contract approval.',
            ], JSON_THROW_ON_ERROR),
            'created_by' => $userId,
            'approved_by' => $confirmed ? ($preview['assignments']['certification_manager']['user_id'] ?? $userId) : null,
            'approved_at' => $confirmed ? $preview['timeline']['proposal_accepted'] . ' 16:00:00' : null,
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
        $confirmed = $this->workflowPackComplete($input);
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
            'status' => $confirmed ? 'signed' : 'draft',
            'signed_at' => $confirmed ? $preview['timeline']['contract_signed'] . ' 11:00:00' : null,
            'signed_by_name' => $input['contact_person'],
            'contract_payload' => json_encode([
                'scope' => $input['scope'],
                'cycle' => $preview['cycle'],
                'system_prepared' => true,
                'automation_mode' => $input['generation_mode'],
                'contract_note' => $confirmed ? 'Contract accepted and approved for audit programme creation.' : 'Contract requires client and QSI signatures before approval.',
            ], JSON_THROW_ON_ERROR),
            'qsi_signatory_name' => $preview['assignments']['general_manager']['full_name'] ?? '',
            'qsi_signatory_date' => $confirmed ? $preview['timeline']['contract_signed'] : null,
            'client_signatory_name' => $input['contact_person'],
            'client_signatory_date' => $confirmed ? $preview['timeline']['contract_signed'] : null,
            'created_by' => $userId,
            'created_at' => $preview['timeline']['contract_signed'] . ' 10:00:00',
        ]);

        return (int) $this->db->insertID();
    }

    private function createInvoiceAndPayment(int $tenantId, int $clientId, array $preview): void
    {
        $proposal = $this->db->table('proposals')->where('tenant_id', $tenantId)->where('client_id', $clientId)->orderBy('id', 'DESC')->get()->getRowArray();
        $amount = (float) ($proposal['grand_total'] ?? 0);
        $paid = $this->workflowPackComplete($preview['input']) && (new DateTimeImmutable($preview['timeline']['contract_signed'])) < new DateTimeImmutable(date('Y-m-d'));
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
                'method' => 'System entry',
                'reference_number' => $this->number('PAY-AUTO', $clientId),
                'received_by' => $preview['assignments']['finance']['user_id'] ?? null,
                'notes' => 'Payment status prepared from the certification cycle file.',
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
                'legend_notes' => 'Prepared by Cycle Builder.',
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
            $this->createAppointments($eventId, $event, $preview);
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

    private function createAppointments(int $eventId, array $event, array $preview): void
    {
        $team = ['lead_auditor' => 'lead_auditor'];
        if (($event['auditor_capacity'] ?? 1) > 1 && ! empty($preview['assignments']['auditor'])) {
            $team['auditor'] = 'auditor';
        }

        foreach ($team as $key => $role) {
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
                    'notes' => 'Prepared after competence and impartiality checks.',
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
        $auditors = array_values(array_filter([$preview['assignments']['lead_auditor'] ?? null, $preview['assignments']['auditor'] ?? null]));
        $calendarDays = max(1, (int) ($event['calendar_days'] ?? 1));
        $sort = 1;

        for ($day = 0; $day < $calendarDays; $day++) {
            $date = (new DateTimeImmutable($event['start']))->add(new DateInterval('P' . $day . 'D'))->format('Y-m-d');
            $slots = $this->auditPlanSlots($type, $day, $processes);
            foreach ($slots as $index => [$start, $end, $activity, $department, $process, $clauses]) {
                $auditor = $auditors[$index % max(1, count($auditors))] ?? null;
                if (stripos($activity, 'lunch') !== false) {
                    $auditor = null;
                }
                $this->db->table('audit_plan_items')->insert([
                    'audit_plan_id' => $planId,
                    'audit_date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'activity_type' => $activity,
                    'department' => $department,
                    'process_name' => $process,
                    'clauses' => $clauses,
                    'auditor_personnel_id' => $auditor['id'] ?? null,
                    'notes' => $this->nonEmpty($preview['input']['audit_plan_notes'], 'Prepared by Cycle Builder; edit timings and process coverage where needed.'),
                    'sort_order' => $sort++,
                ]);
            }
        }
    }

    private function auditPlanSlots(string $type, int $day, array $processes): array
    {
        $core1 = trim($processes[$day % max(1, count($processes))] ?? 'Core process');
        $core2 = trim($processes[($day + 1) % max(1, count($processes))] ?? 'Support process');

        if ($type === 'initial_stage1') {
            return [
                ['09:00:00', '09:30:00', 'Opening meeting', 'Top Management', 'Audit objectives, scope, criteria, confidentiality and communication', 'ISO/IEC 17021-1 planning requirements'],
                ['09:30:00', '10:45:00', 'Document and scope review', 'Management system', 'Scope, boundaries, sites, processes and exclusions', '4.1, 4.2, 4.3, 4.4'],
                ['10:45:00', '12:30:00', 'Readiness review', 'Management / process owners', 'Policy, objectives, responsibilities, legal and customer requirements', '5.1, 5.2, 5.3, 6.1, 6.2'],
                ['12:30:00', '13:30:00', 'Lunch break', '', '', ''],
                ['13:30:00', '14:45:00', 'Internal audit and management review review', 'Quality / food safety / HSE', 'Internal audit, management review and corrective action readiness', '9.2, 9.3, 10.2'],
                ['14:45:00', '15:45:00', 'Site tour and Stage 2 readiness confirmation', 'Operational areas', $core1, '7.1, 7.2, 7.5, 8.1'],
                ['15:45:00', '16:30:00', 'Stage 1 conclusions and Stage 2 planning inputs', 'Audit team', 'Readiness issues, Stage 2 focus, audit programme confirmation', 'Stage 1 conclusion'],
                ['16:30:00', '17:00:00', 'Closing meeting', 'Top Management', 'Stage 1 result and actions before Stage 2', 'Audit conclusion'],
            ];
        }

        return [
            ['09:00:00', '09:30:00', 'Opening meeting', 'Top Management', 'Audit objectives, scope, criteria, audit team and plan confirmation', 'ISO/IEC 17021-1 planning requirements'],
            ['09:30:00', '10:45:00', 'Process audit', 'Operations', $core1, '8.1 and applicable operational controls'],
            ['10:45:00', '12:30:00', 'Process audit and record sampling', 'Operations / support', $core2, '7.1, 7.2, 7.5, 8.x'],
            ['12:30:00', '13:30:00', 'Lunch break', '', '', ''],
            ['13:30:00', '14:30:00', 'Performance evaluation', 'Quality / food safety / HSE', 'Monitoring, measurement, analysis and evaluation', '9.1'],
            ['14:30:00', '15:30:00', 'Internal audit and management review', 'Management system', 'Audit programme, audit results, management review outputs and actions', '9.2, 9.3'],
            ['15:30:00', '16:15:00', 'Improvement and NCR/CAPA review', 'Process owners', 'Nonconformity, correction, root cause, corrective action and effectiveness', '10.1, 10.2, 10.3'],
            ['16:15:00', '16:40:00', 'Audit team meeting', 'Audit team', 'Review evidence, agree findings and conclusions', 'Audit conclusion'],
            ['16:40:00', '17:00:00', 'Closing meeting', 'Top Management', 'Audit findings, conclusion, NCR/CAPA timeline and next steps', 'Audit conclusion'],
        ];
    }

    private function createReport(int $tenantId, int $eventId, string $type, array $event, array $preview): int
    {
        $lead = $preview['assignments']['lead_auditor'] ?? null;
        $reviewer = $preview['assignments']['technical_reviewer'] ?? null;
        $confirmed = $this->workflowPackComplete($preview['input']);
        $evidenceSummary = $this->auditEvidenceSummary($preview['input'], $type);
        $payload = [
            'audit_objectives' => 'Verify conformity, implementation and effectiveness for the selected certification scope.',
            'audit_criteria' => implode(', ', array_column($preview['standards'], 'code')) . ', client procedures, legal and customer requirements.',
            'audit_scope' => $preview['input']['scope'],
            'recommendation' => $type === 'initial_stage1' ? 'Proceed to Stage 2 subject to readiness actions.' : 'Maintain/grant certification subject to NCR/CAPA status.',
            'system_prepared' => true,
            'automation_mode' => $preview['input']['generation_mode'],
            'audit_evidence_summary' => $evidenceSummary,
        ];
        $this->db->table('report_drafts')->insert([
            'tenant_id' => $tenantId,
            'audit_event_id' => $eventId,
            'report_type' => 'audit_execution',
            'version_number' => 1,
            'status' => $confirmed && $event['status'] !== 'planned' ? 'approved' : 'draft',
            'generated_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'editable_payload' => json_encode([
                'auditor_notes' => $confirmed
                    ? $evidenceSummary
                    : 'Auditor shall complete clause notes with actual sampled evidence before report submission.',
            ], JSON_THROW_ON_ERROR),
            'prepared_by' => $lead['user_id'] ?? null,
            'approved_by' => $confirmed && $event['status'] !== 'planned' ? ($reviewer['user_id'] ?? null) : null,
            'approved_at' => $confirmed && $event['status'] !== 'planned' ? $event['end'] . ' 16:00:00' : null,
            'submitted_at' => $confirmed && $event['status'] !== 'planned' ? $event['end'] . ' 15:00:00' : null,
            'created_at' => $event['end'] . ' 10:00:00',
        ]);
        $reportId = (int) $this->db->insertID();
        $poolClient = $this->poolClient($tenantId, $preview);
        $poolEvent = ['event_type' => $type, 'audit_number' => $event['audit_number'] ?? ''];
        foreach ($this->clauses($preview['standards'], 12) as $sort => $clause) {
            $package = $this->contentEngine->conformitySection($poolClient, $poolEvent, $clause);
            $this->db->table('report_sections')->insert([
                'report_draft_id' => $reportId,
                'clause_library_id' => empty($clause['id']) ? null : (int) $clause['id'],
                'section_key' => 'conformity',
                'section_title' => $clause['standard_code'] . ' ' . $clause['clause_number'] . ' - ' . $clause['clause_title'],
                'section_content' => $confirmed
                    ? $package['content']
                    : $this->draftConformityText($preview['input']['client_name'], $clause, $type),
                'source_type' => $confirmed ? $package['source_type'] : 'system_prepared',
                'auditor_confirmed' => $confirmed ? 1 : 0,
                'confirmed_by_user_id' => $confirmed ? ($lead['user_id'] ?? null) : null,
                'confirmed_at' => $confirmed ? $event['end'] . ' 14:30:00' : null,
                'confirmation_note' => $confirmed ? $package['confirmation_note'] : 'Auditor confirmation required before report submission.',
                'sort_order' => $sort + 1,
            ]);
        }

        return $reportId;
    }

    private function createNcrCapa(int $tenantId, int $clientId, int $eventId, string $type, array $event, int $userId, array $preview): array
    {
        $mode = $preview['input']['ncr_mode'];
        $confirmed = $this->workflowPackComplete($preview['input']);
        $existingIds = $this->existingNcrIds($eventId);
        if ($existingIds !== []) {
            return $existingIds;
        }

        $poolClient = $this->poolClient($tenantId, $preview);
        $poolEvent = ['event_type' => $type];
        $count = match ($mode) {
            'none' => 0,
            'major' => in_array($type, ['initial_stage2', 'surveillance1', 'surveillance2', 'recertification'], true) ? 4 : 2,
            default => in_array($type, ['initial_stage2', 'surveillance1', 'surveillance2', 'recertification'], true) ? 4 : 0,
        };
        $ids = [];
        $clauses = $this->clauses($preview['standards'], max(1, $count));
        for ($i = 1; $i <= $count; $i++) {
            $clause = $clauses[($i - 1) % count($clauses)];
            $severity = $mode === 'major' && $i === 1 ? 'major' : 'minor';
            $closed = $confirmed && $event['status'] !== 'planned';
            $ncrNumber = $this->number('NCR-AUTO-' . strtoupper(str_replace('_', '-', $type)), $clientId) . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $target = (new DateTimeImmutable($event['end']))->add(new DateInterval('P21D'))->format('Y-m-d');
            $package = $this->contentEngine->ncrCapaPackage($poolClient, $poolEvent, $clause, $severity, $i);
            $this->db->table('ncrs')->insert([
                'tenant_id' => $tenantId,
                'audit_event_id' => $eventId,
                'clause_library_id' => empty($clause['id']) ? null : (int) $clause['id'],
                'ncr_number' => $ncrNumber,
                'requirement' => $package['requirement'],
                'finding' => $package['finding'],
                'objective_evidence' => $package['objective_evidence'],
                'classification' => $severity,
                'correction' => $closed ? $package['correction'] : 'To be completed by client.',
                'root_cause' => $closed ? $package['root_cause'] : 'To be completed by client using accepted root-cause method.',
                'corrective_action' => $closed ? $package['corrective_action'] : 'To be completed by client and verified by auditor.',
                'responsible_person' => $preview['input']['contact_person'],
                'target_date' => $target,
                'verification' => $closed ? $package['verification'] : 'Pending auditor verification.',
                'closure_notes' => $closed ? 'Closed from the prepared cycle file with linked correction, root cause, action and verification evidence.' : 'Open for client action.',
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
                'issue' => $package['finding'],
                'immediate_correction' => $closed ? $package['correction'] : 'To be completed by client.',
                'root_cause' => $closed ? $package['root_cause'] : 'To be completed by client.',
                'five_why' => json_encode($closed ? ['Why was the issue raised?' => $package['root_cause'], 'Why was it not detected?' => 'Verification did not identify the missing evidence before audit sampling.'] : ['status' => 'To be completed by client'], JSON_THROW_ON_ERROR),
                'fishbone' => json_encode($closed ? ['method' => $package['root_cause'], 'records' => $package['objective_evidence'], 'people' => 'Responsible staff awareness verified through CAPA evidence'] : ['status' => 'To be completed by client'], JSON_THROW_ON_ERROR),
                'corrective_action' => $closed ? $package['corrective_action'] : 'To be completed by client.',
                'preventive_action' => $closed ? $package['preventive_action'] : 'To be completed by client where applicable.',
                'responsible_person' => $preview['input']['contact_person'],
                'target_date' => $target,
                'evidence_reference' => $package['evidence_reference'],
                'verification' => $closed ? $package['verification'] : 'Pending review.',
                'effectiveness' => $closed ? $package['effectiveness'] : 'Pending effectiveness verification.',
                'closure_notes' => $closed ? $package['closure_notes'] : 'Awaiting evidence.',
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
        $approved = $this->workflowPackComplete($preview['input']) && $event['status'] !== 'planned';
        $reviewNotes = $this->technicalReviewSummary($preview['input'], $type, count($ncrIds));
        $evidenceSummary = $this->auditEvidenceSummary($preview['input'], $type);
        $this->db->table('technical_reviews')->insert([
            'tenant_id' => $tenantId,
            'audit_event_id' => $eventId,
            'reviewer_personnel_id' => (int) ($person['id'] ?? 0),
            'checklist_payload' => json_encode([
                'system_prepared' => true,
                'automation_mode' => $preview['input']['generation_mode'],
                'ncr_count' => count($ncrIds),
                'review_notes' => $approved
                    ? $reviewNotes
                    : 'Technical review requires competent reviewer verification of the actual audit file.',
                'audit_evidence_summary' => $evidenceSummary,
            ], JSON_THROW_ON_ERROR),
            'competency_confirmed' => $approved ? 1 : 0,
            'duration_confirmed' => $approved ? 1 : 0,
            'application_confirmed' => $approved ? 1 : 0,
            'reports_confirmed' => $approved ? 1 : 0,
            'ncr_capa_confirmed' => $approved ? 1 : 0,
            'scope_dates_confirmed' => $approved ? 1 : 0,
            'impartiality_confirmed' => $approved ? 1 : 0,
            'recommendation' => $approved ? 'approve' : 'pending',
            'status' => $approved ? 'approved' : 'pending',
            'reviewed_at' => $approved ? (new DateTimeImmutable($event['end']))->add(new DateInterval('P2D'))->format('Y-m-d 14:00:00') : null,
            'created_at' => (new DateTimeImmutable($event['end']))->add(new DateInterval('P2D'))->format('Y-m-d 10:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function createDecision(int $tenantId, int $reviewId, string $type, array $event, array $preview): int
    {
        $person = $preview['assignments']['decision_maker'] ?? null;
        $approved = $this->workflowPackComplete($preview['input']) && $event['status'] !== 'planned';
        $decisionBasis = $this->decisionBasis($preview['input'], $type);
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
            'reason' => $approved ? $decisionBasis : 'Decision pending Technical Review approval and Decision Maker confirmation.',
            'electronic_signature' => $approved ? (($person['full_name'] ?? 'Decision Maker') . ' / controlled electronic approval') : null,
            'decision_payload' => json_encode([
                'system_prepared' => true,
                'automation_mode' => $preview['input']['generation_mode'],
                'event_type' => $type,
                'declaration_confirmed' => $approved,
                'decision_basis' => $decisionBasis,
            ], JSON_THROW_ON_ERROR),
            'decided_at' => $approved ? (new DateTimeImmutable($event['end']))->add(new DateInterval('P3D'))->format('Y-m-d 11:00:00') : null,
            'status' => $approved ? 'approved' : 'pending',
            'gm_approved_by_user_id' => $approved ? ($preview['assignments']['general_manager']['user_id'] ?? null) : null,
            'gm_approval_notes' => $approved ? 'Final approval completed after decision review and controlled cycle file verification.' : null,
            'gm_approved_at' => $approved ? (new DateTimeImmutable($event['end']))->add(new DateInterval('P3D'))->format('Y-m-d 15:00:00') : null,
            'created_at' => (new DateTimeImmutable($event['end']))->add(new DateInterval('P3D'))->format('Y-m-d 10:00:00'),
        ]);

        return (int) $this->db->insertID();
    }

    private function createCertificates(int $tenantId, int $clientId, ?int $decisionId, array $preview): array
    {
        if (! $this->workflowPackComplete($preview['input'])) {
            return [];
        }

        $ids = [];
        $certificateStatus = $preview['input']['certification_status'] === 'certified'
            ? 'active'
            : $preview['input']['certification_status'];
        $certificateStatus = in_array($certificateStatus, ['active', 'suspended', 'withdrawn', 'expired'], true)
            ? $certificateStatus
            : 'active';

        foreach ($preview['standards'] as $idx => $standard) {
            $certNo = $this->nextCertificateNumber($tenantId, (string) ($standard['code'] ?? $standard['standard_code'] ?? $standard['name'] ?? 'STANDARD'));
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
        $confirmed = $this->workflowPackComplete($preview['input']);
        $this->db->table('client_feedback')->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'audit_program_id' => $programId,
            'certificate_id' => $certificateId,
            'contact_name' => $preview['input']['contact_person'],
            'contact_email' => $preview['input']['email'],
            'submitted_at' => $confirmed ? (new DateTimeImmutable($preview['cycle']['issue']))->add(new DateInterval('P7D'))->format('Y-m-d 12:00:00') : null,
            'overall_rating' => $confirmed ? 4 : null,
            'communication_rating' => $confirmed ? 4 : null,
            'auditor_rating' => $confirmed ? 4 : null,
            'report_quality_rating' => $confirmed ? 4 : null,
            'comments' => $confirmed ? 'Client feedback record opened and marked satisfactory for the completed certification cycle.' : 'Feedback record prepared. Actual client feedback must be collected and entered.',
            'improvement_suggestion' => $confirmed ? 'Continue monitoring response time, report clarity and auditor communication during surveillance activities.' : null,
            'status' => $confirmed ? 'submitted' : 'draft',
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

    private function draftConformityText(string $clientName, array $clause, string $type): string
    {
        $ref = $this->docPrefix($clientName) . '-' . ($clause['clause_number'] ?? 'GEN') . '-001';
        $evidence = $this->clauseEvidenceTrail($clause);

        return 'AUDITOR TO COMPLETE - system prepared this checklist row. '
            . 'Before submitting the ' . str_replace('_', ' ', $type) . ' report, replace this note with actual sampled conformity evidence for '
            . (string) ($clause['standard_code'] ?? '') . ' ' . (string) ($clause['clause_number'] ?? '') . ' - ' . (string) ($clause['clause_title'] ?? '')
            . '. Suggested evidence trails for this clause: ' . $evidence
            . '. Suggested document reference format: ' . $ref . '.';
    }

    private function conformityText(string $clientName, array $clause, string $type, string $evidenceSummary): string
    {
        $ref = $this->docPrefix($clientName) . '-' . ($clause['clause_number'] ?? 'GEN') . '-001';
        $evidence = $this->clauseEvidenceTrail($clause);

        return sprintf(
            'Conformity verified for %s %s - %s during %s. Evidence summary: %s. Clause-aligned evidence trail: %s. Evidence reference: %s.',
            (string) ($clause['standard_code'] ?? ''),
            (string) ($clause['clause_number'] ?? ''),
            (string) ($clause['clause_title'] ?? ''),
            str_replace('_', ' ', $type),
            $this->nonEmpty($evidenceSummary, 'No detailed evidence summary supplied.'),
            $evidence,
            $ref
        );
    }

    private function findingText(array $clause, array $input, string $type, string $severity): string
    {
        return ucfirst($severity) . ' nonconformity raised during ' . str_replace('_', ' ', $type)
            . ': sampled evidence for ' . ($clause['clause_title'] ?? 'the requirement')
            . ' did not fully demonstrate controlled implementation for ' . $input['scope'] . '.';
    }

    private function evidenceText(string $clientName, array $clause, string $evidenceSummary): string
    {
        $ref = $this->docPrefix($clientName) . '-' . ($clause['clause_number'] ?? 'GEN') . '-NCR-001';

        return 'Objective evidence reference ' . $ref . '. Clause-aligned evidence expected: ' . $this->clauseEvidenceTrail($clause)
            . '. Supplied evidence summary: ' . $this->nonEmpty($evidenceSummary, 'To be completed by auditor from actual sampled records.');
    }

    private function poolText(ClauseContentPoolService $pool, array $client, ?array $event, array $clause, string $contentType, ?string $severity = null): ?string
    {
        $template = $pool->templateFor($client, $event, $clause, $contentType, $severity);

        return $template === null ? null : $pool->renderTemplate($template, $client, $event, $clause);
    }

    private function poolClient(int $tenantId, array $preview): array
    {
        return [
            'tenant_id' => $tenantId,
            'company' => $preview['input']['client_name'],
            'client_name' => $preview['input']['client_name'],
            'scope' => $preview['input']['scope'],
            'iaf_code_id' => $preview['input']['iaf_code_id'],
            'food_chain_category_id' => $preview['input']['food_category_id'],
            'medical_device_category_id' => $preview['input']['medical_category_id'],
        ];
    }

    private function existingNcrIds(int $eventId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->table('ncrs')
                ->select('id')
                ->where('audit_event_id', $eventId)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray()
        );
    }

    private function clauseEvidenceTrail(array $clause): string
    {
        $text = strtolower((string) ($clause['standard_code'] ?? '') . ' ' . (string) ($clause['clause_number'] ?? '') . ' ' . (string) ($clause['clause_title'] ?? '') . ' ' . (string) ($clause['requirement'] ?? ''));

        $trails = [
            'context' => 'scope/context review, interested-party register, internal/external issue review and management review output',
            'interested' => 'interested-party register, statutory/customer requirement list and communication records',
            'scope' => 'approved certification scope, process map, site/activity boundary and exclusion justification where applicable',
            'leadership' => 'policy approval, responsibility matrix, management review participation and resource decisions',
            'policy' => 'approved policy, communication evidence, displayed/available policy and employee awareness interview',
            'roles' => 'organization chart, job descriptions, responsibility matrix and competence authorization records',
            'risk' => 'risk/opportunity register, action plan, review of effectiveness and change records',
            'objectives' => 'measurable objectives, monitoring results, action plans and progress review',
            'change' => 'change request, risk assessment, approval record and implementation verification',
            'support' => 'resource planning, infrastructure/maintenance records and work environment controls',
            'competence' => 'competence matrix, training records, evaluation of effectiveness and auditor/interview samples',
            'awareness' => 'awareness briefing records, interview notes and communication material',
            'communication' => 'internal/external communication matrix, customer/regulatory communication and complaint records',
            'documented' => 'document master list, controlled procedure/form sample, revision approval and record retention evidence',
            'operation' => 'process control plan, work instructions, monitoring logs and release/inspection records',
            'design' => 'design/development plan, inputs, outputs, review, verification, validation and change records',
            'purchas' => 'approved supplier list, purchase order, receiving inspection and supplier evaluation records',
            'traceability' => 'receiving batch record, production/processing record, dispatch record and mock recall/traceability test',
            'haccp' => 'HACCP plan, hazard analysis, CCP/OPRP limits, monitoring records, verification records and validation evidence',
            'hazard' => 'hazard analysis worksheet, CCP/OPRP decision record, validation basis and monitoring sample',
            'prp' => 'PRP records for cleaning, pest control, personal hygiene, maintenance, waste and temperature control',
            'emergency' => 'emergency preparedness plan, drill/test record, incident record and improvement action',
            'monitor' => 'monitoring plan, KPI results, calibrated equipment records and analysis of results',
            'internal audit' => 'audit programme, audit plan, audit checklist, auditor competence and corrective action follow-up',
            'management review' => 'management review agenda, inputs, outputs, decisions, action owners and follow-up status',
            'nonconformity' => 'NCR log, correction, root cause, corrective action, verification and effectiveness evidence',
            'corrective' => 'CAPA record, root-cause analysis, correction, corrective action evidence and effectiveness check',
            'improvement' => 'improvement log, objective trend, corrective-action trend and management review improvement decisions',
        ];

        foreach ($trails as $needle => $trail) {
            if (str_contains($text, $needle)) {
                return $trail;
            }
        }

        return 'procedure or process record, responsible-person interview, sampled implementation evidence and linked monitoring/review record';
    }

    private function historicalConfirmed(array $input): bool
    {
        return ($input['generation_mode'] ?? '') === 'historical_confirmed'
            && trim((string) ($input['audit_evidence_summary'] ?? '')) !== ''
            && trim((string) ($input['technical_review_notes'] ?? '')) !== ''
            && trim((string) ($input['decision_basis'] ?? '')) !== '';
    }

    private function workflowPackComplete(array $input): bool
    {
        return ($input['generation_mode'] ?? 'standard') === 'standard'
            || $this->historicalConfirmed($input);
    }

    private function auditEvidenceSummary(array $input, string $eventType): string
    {
        $summary = trim((string) ($input['audit_evidence_summary'] ?? ''));
        if ($summary !== '') {
            return $summary;
        }

        $scope = $this->nonEmpty($input['scope'] ?? '', 'the approved certification scope');
        $stage = ucwords(str_replace('_', ' ', $eventType));

        return $stage . ' evidence was built around the approved scope: ' . $scope
            . '. Records reviewed include application and contract scope, audit programme, auditor appointment, audit plan, clause checklist, process records, management system records, sampled implementation evidence, NCR/CAPA records where applicable, and report conclusions.';
    }

    private function technicalReviewSummary(array $input, string $eventType, int $ncrCount): string
    {
        $notes = trim((string) ($input['technical_review_notes'] ?? ''));
        if ($notes !== '') {
            return $notes;
        }

        $scope = $this->nonEmpty($input['scope'] ?? '', 'the approved scope');
        $stage = ucwords(str_replace('_', ' ', $eventType));
        $ncrText = $ncrCount === 0
            ? 'No open NCR requiring closure was identified for this stage.'
            : $ncrCount . ' NCR/CAPA record(s) were checked for correction, root cause, corrective action, evidence, verification and closure status.';

        return $stage . ' technical review completed for ' . $scope
            . '. The reviewer checked audit team competence, impartiality, audit duration, audit plan coverage, report completeness, clause evidence, recommendation consistency and certification scope/date accuracy. '
            . $ncrText;
    }

    private function decisionBasis(array $input, string $eventType): string
    {
        $basis = trim((string) ($input['decision_basis'] ?? ''));
        if ($basis !== '') {
            return $basis;
        }

        $scope = $this->nonEmpty($input['scope'] ?? '', 'the approved certification scope');
        $stage = ucwords(str_replace('_', ' ', $eventType));

        return $stage . ' decision based on accepted application review, signed contract, approved audit programme, competent auditor appointment, completed audit plan/report, verified NCR/CAPA status, approved technical review, impartiality confirmation and conformity evidence for ' . $scope . '.';
    }

    private function nonEmpty(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value === '' ? $fallback : $value;
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

    private function nextCertificateNumber(int $tenantId, string $standardCode): string
    {
        $prefix = 'QSI-' . $this->certificateStandardPrefix($standardCode);
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

    private function intOrNull(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}

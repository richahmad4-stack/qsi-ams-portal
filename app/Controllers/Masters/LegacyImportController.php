<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Models\LegacyImportBatchModel;
use App\Models\LegacyImportRowModel;
use App\Services\AuditLogger;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class LegacyImportController extends BaseController
{
    private BaseConnection $db;
    private LegacyImportBatchModel $batches;
    private LegacyImportRowModel $rows;
    private ClientModel $clients;
    private AuditLogger $auditLogger;

    private array $aliases = [
        'company' => ['company', 'client', 'client_name', 'organization', 'organisation'],
        'address' => ['address', 'location'],
        'country' => ['country'],
        'city' => ['city'],
        'contact_person' => ['contact', 'contact_person', 'contact_name'],
        'designation' => ['designation', 'position', 'title'],
        'email' => ['email', 'e-mail', 'mail'],
        'phone' => ['phone', 'telephone', 'mobile'],
        'website' => ['website', 'web'],
        'scope' => ['scope', 'certification_scope'],
        'employee_count' => ['employees', 'employee_count', 'total_employees'],
        'number_of_sites' => ['sites', 'number_of_sites', 'site_count'],
        'certification_status' => ['status', 'certification_status'],
        'risk_category' => ['risk', 'risk_category'],
        'certificate_number' => ['certificate', 'certificate_number', 'cert_no'],
        'initial_certification_date' => ['initial_date', 'initial_certification_date'],
        'certificate_issue_date' => ['issue_date', 'certificate_issue_date'],
        'certificate_expiry_date' => ['expiry_date', 'certificate_expiry_date', 'expiry'],
        'notes' => ['notes', 'remarks'],
    ];

    public function __construct()
    {
        $this->db = Database::connect();
        $this->batches = new LegacyImportBatchModel();
        $this->rows = new LegacyImportRowModel();
        $this->clients = new ClientModel();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        return view('masters/imports/index', [
            'title' => 'Legacy Import',
            'pageTitle' => 'Legacy Client Import',
            'pageSubtitle' => 'CSV preview, validation, duplicate detection and rollback',
            'batches' => $this->batches
                ->where('tenant_id', (int) session()->get('tenant_id'))
                ->orderBy('created_at', 'DESC')
                ->findAll(),
        ]);
    }

    public function upload()
    {
        $file = $this->request->getFile('legacy_file');

        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Choose a valid CSV file.');
        }

        if (strtolower($file->getExtension()) !== 'csv') {
            return redirect()->back()->with('error', 'CSV import is available now. Save Excel as CSV before importing.');
        }

        $handle = fopen($file->getTempName(), 'rb');

        if ($handle === false) {
            return redirect()->back()->with('error', 'Could not read the uploaded file.');
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            return redirect()->back()->with('error', 'CSV file is empty.');
        }

        $headers = array_map(static fn ($header) => trim((string) $header), $headers);
        $mapping = $this->autoMap($headers);
        $stats = [
            'total' => 0,
            'valid' => 0,
            'invalid' => 0,
            'duplicate' => 0,
        ];

        $this->db->transStart();
        $this->batches->insert([
            'tenant_id' => (int) session()->get('tenant_id'),
            'source_type' => 'csv',
            'original_filename' => $file->getClientName(),
            'column_mapping' => json_encode($mapping, JSON_THROW_ON_ERROR),
            'status' => 'preview',
            'imported_by' => (int) session()->get('user_id'),
        ]);
        $batchId = (int) $this->batches->getInsertID();

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $raw = $this->combineRow($headers, $row);
            $normalized = $this->normalize($raw, $mapping);
            $errors = $this->validateImportedClient($normalized);
            $duplicate = $this->duplicateKey($normalized);
            $status = 'valid';

            if ($errors !== []) {
                $status = 'invalid';
                $stats['invalid']++;
            } elseif ($duplicate !== null) {
                $status = 'duplicate';
                $stats['duplicate']++;
            } else {
                $stats['valid']++;
            }

            $stats['total']++;

            $this->rows->insert([
                'batch_id' => $batchId,
                'row_number' => $rowNumber,
                'raw_payload' => json_encode($raw, JSON_THROW_ON_ERROR),
                'normalized_payload' => json_encode($normalized, JSON_THROW_ON_ERROR),
                'validation_errors' => $errors === [] ? null : json_encode($errors, JSON_THROW_ON_ERROR),
                'duplicate_key' => $duplicate,
                'status' => $status,
            ]);
        }

        fclose($handle);

        $this->batches->update($batchId, [
            'total_rows' => $stats['total'],
            'valid_rows' => $stats['valid'],
            'invalid_rows' => $stats['invalid'],
            'duplicate_rows' => $stats['duplicate'],
        ]);

        $this->auditLogger->record('create', 'legacy_imports', 'legacy_import_batches', $batchId, null, $stats);
        $this->db->transComplete();

        return redirect()->to('/masters/imports/' . $batchId)->with('success', 'Import preview created.');
    }

    public function show(int $id)
    {
        $batch = $this->tenantBatch($id);

        if ($batch === null) {
            return redirect()->to('/masters/imports')->with('error', 'Import batch not found.');
        }

        return view('masters/imports/show', [
            'title' => 'Import Preview',
            'pageTitle' => 'Import Preview',
            'pageSubtitle' => $batch['original_filename'],
            'batch' => $batch,
            'rows' => $this->rows->where('batch_id', $id)->orderBy('row_number', 'ASC')->findAll(),
            'mapping' => json_decode($batch['column_mapping'], true) ?: [],
        ]);
    }

    public function commit(int $id)
    {
        $batch = $this->tenantBatch($id);

        if ($batch === null || $batch['status'] !== 'preview') {
            return redirect()->to('/masters/imports')->with('error', 'Only preview batches can be committed.');
        }

        $validRows = $this->rows->where('batch_id', $id)->where('status', 'valid')->findAll();
        $this->db->transStart();

        foreach ($validRows as $row) {
            $payload = json_decode($row['normalized_payload'], true) ?: [];
            $payload['tenant_id'] = (int) session()->get('tenant_id');
            $payload['legacy_import_batch_id'] = $id;
            $payload['is_legacy'] = 1;
            $payload['created_by'] = (int) session()->get('user_id');

            $clientId = (int) $this->clients->insert($payload);
            $this->rows->update((int) $row['id'], [
                'client_id' => $clientId,
                'status' => 'imported',
            ]);
        }

        $this->batches->update($id, [
            'status' => 'imported',
            'imported_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->record('approve', 'legacy_imports', 'legacy_import_batches', $id, $batch, ['imported_rows' => count($validRows)]);
        $this->db->transComplete();

        return redirect()->to('/masters/imports/' . $id)->with('success', 'Legacy clients imported.');
    }

    public function rollback(int $id)
    {
        $batch = $this->tenantBatch($id);

        if ($batch === null || $batch['status'] !== 'imported') {
            return redirect()->to('/masters/imports')->with('error', 'Only imported batches can be rolled back.');
        }

        $this->db->transStart();
        $importedClients = $this->clients->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('legacy_import_batch_id', $id)
            ->findAll();

        foreach ($importedClients as $client) {
            $this->clients->delete((int) $client['id']);
        }

        $this->db->table('legacy_import_rows')
            ->where('batch_id', $id)
            ->where('status', 'imported')
            ->update(['status' => 'rolled_back']);
        $this->batches->update($id, ['status' => 'rolled_back']);
        $this->auditLogger->record('reject', 'legacy_imports', 'legacy_import_batches', $id, $batch, ['rolled_back_rows' => count($importedClients)]);
        $this->db->transComplete();

        return redirect()->to('/masters/imports/' . $id)->with('success', 'Imported clients rolled back.');
    }

    private function tenantBatch(int $id): ?array
    {
        $batch = $this->batches->find($id);

        if ($batch === null || (int) $batch['tenant_id'] !== (int) session()->get('tenant_id')) {
            return null;
        }

        return $batch;
    }

    private function autoMap(array $headers): array
    {
        $mapping = [];
        $normalizedHeaders = [];

        foreach ($headers as $header) {
            $normalizedHeaders[$this->normalizeHeader($header)] = $header;
        }

        foreach ($this->aliases as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($normalizedHeaders[$this->normalizeHeader($alias)])) {
                    $mapping[$field] = $normalizedHeaders[$this->normalizeHeader($alias)];
                    break;
                }
            }
        }

        return $mapping;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(str_replace([' ', '-', '.'], '_', trim($header)));
    }

    private function combineRow(array $headers, array $row): array
    {
        $combined = [];

        foreach ($headers as $index => $header) {
            $combined[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $combined;
    }

    private function normalize(array $raw, array $mapping): array
    {
        $normalized = [
            'certification_status' => 'certified',
            'number_of_sites' => 1,
        ];

        foreach ($mapping as $field => $header) {
            $value = trim((string) ($raw[$header] ?? ''));
            if ($value === '') {
                continue;
            }

            $normalized[$field] = in_array($field, ['employee_count', 'number_of_sites'], true) ? (int) $value : $value;
        }

        return $normalized;
    }

    private function validateImportedClient(array $normalized): array
    {
        $errors = [];

        if (($normalized['company'] ?? '') === '') {
            $errors[] = 'Company is required.';
        }

        if (($normalized['email'] ?? '') !== '' && filter_var($normalized['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email is invalid.';
        }

        return $errors;
    }

    private function duplicateKey(array $normalized): ?string
    {
        $tenantId = (int) session()->get('tenant_id');

        if (($normalized['certificate_number'] ?? '') !== '') {
            $exists = $this->clients->where('tenant_id', $tenantId)->where('certificate_number', $normalized['certificate_number'])->first();
            if ($exists !== null) {
                return 'certificate_number:' . $normalized['certificate_number'];
            }
        }

        if (($normalized['company'] ?? '') !== '') {
            $exists = $this->clients->where('tenant_id', $tenantId)->where('company', $normalized['company'])->first();
            if ($exists !== null) {
                return 'company:' . $normalized['company'];
            }
        }

        return null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
